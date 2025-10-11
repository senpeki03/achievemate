<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Application;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class StudentApplicationController extends Controller
{
    public function finalizeApplication(Request $request)
    {
        try {
            $studentId = session('Student_id');
            $pdfUrl    = $request->input('pdf_url');      // optional hint from client
            $type      = trim((string) $request->input('type', 'DeanLister')); // <-- ensure Type

            if (!$studentId) {
                return response()->json(['success' => false, 'message' => 'Student not logged in.'], 403);
            }

            // ---- (A) Locate generated PDF on the public disk ----
            $relativePdfPath = 'pdf_output/filled_dean_form.pdf';

            // If the client passed a URL, try to derive a safe file name from it
            if ($pdfUrl) {
                $pathFromUrl = parse_url($pdfUrl, PHP_URL_PATH);
                $candidate   = basename($pathFromUrl ?: '');
                if ($candidate && preg_match('/^[A-Za-z0-9._-]+\.pdf$/', $candidate)) {
                    $relativePdfPath = "pdf_output/{$candidate}";
                }
            }

            $disk = Storage::disk('public');
            if (!$disk->exists($relativePdfPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Generated PDF not found.'
                ], 422);
            }

            $fileBytes = $disk->get($relativePdfPath);
            $fileName  = basename($relativePdfPath);

            // ---- (B) Parse GWA from the canonical cog_output.txt ----
            $cogTextPath = storage_path('app/cog/cog_output.txt');
            if (!file_exists($cogTextPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'cog_output.txt not found. Save/regenerate before finalizing.'
                ], 422);
            }

            [$gwaMaybe, $rankMaybe] = $this->extractGwaAndRank($cogTextPath);

            // Enforce NOT NULL constraints (your schema marks these as NOT NULL)
            $gwa  = is_null($gwaMaybe)  ? 0.0 : (float)$gwaMaybe;     // float NOT NULL
            $rank = is_null($rankMaybe) ? ''   : (string)$rankMaybe;  // varchar NOT NULL

            // ---- (C) Insert into `application` table ----
            $app = new Application();
            $app->Student_id = $studentId;
            $app->Type       = $type;        // <-- REQUIRED (NOT NULL)
            $app->File_name  = $fileName;
            $app->File_data  = $fileBytes;   // BLOB stored in DB; DO NOT return in JSON
            $app->GWA        = $gwa;         // NEVER null
            $app->Rank       = $rank;        // NEVER null
            $app->Status     = 'Pending';    // NOT NULL
            $app->save();

            // ---- (D) Return only safe scalars (no blob, no model dump) ----
            return response()->json([
                'success'        => true,
                'application_id' => $app->Application_id,
                'status'         => $app->Status,
                'gwa'            => $app->GWA,
                'rank'           => $app->Rank,
                'type'           => $app->Type,
                'file_name'      => $app->File_name,
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            Log::error($e);
            $msg = $e->getMessage();
            if (!mb_check_encoding($msg, 'UTF-8')) {
                $msg = 'Unexpected error.';
            }
            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Read cog_output.txt, extract numeric GWA and compute rank.
     * Rank rules:
     *  - 1.0000–1.2500  => Tech Savant
     *  - 1.2501–1.5000  => Tech Virtuoso
     *  - 1.5001–1.7500  => Tech Prodigy
     * Otherwise null
     *
     * @return array{0: float|null, 1: string|null}
     */
    private function extractGwaAndRank(string $path): array
    {
        $text = file_get_contents($path);

        if ($text === false) {
            return [null, null];
        }

        // Force to UTF-8 to avoid weird encodings from OCR
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }

        $gwa = null;
        if (preg_match('/\bGWA\b[^\d]*([0-9]+(?:\.[0-9]+)?)/i', $text, $m)) {
            $gwa = (float) $m[1];
        }

        $rank = null;
        if (!is_null($gwa)) {
            if ($gwa >= 1.0000 && $gwa <= 1.2500) {
                $rank = 'Tech Savant';
            } elseif ($gwa > 1.2500 && $gwa <= 1.5000) {
                $rank = 'Tech Virtuoso';
            } elseif ($gwa > 1.5000 && $gwa <= 1.7500) {
                $rank = 'Tech Prodigy';
            }
        }

        return [$gwa, $rank];
    }
}
