<?php

namespace App\Http\Controllers\Student;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;

class CogParseController extends Controller
{
    // in public disk: storage/app/public/cog/...
    private string $pubQr  = 'cog/parse_qr_output.txt';
    private string $pubOcr = 'cog/parse_ocr_output.txt';

    // common non-public fallbacks: storage/app/cog/... or storage/app/app/cog/...
    private array $fallbackRoots = ['app/cog', 'app/app/cog'];

    public function qr(): Response  { return $this->textResponse($this->pubQr, 'parse_qr_output.txt'); }
    public function ocr(): Response { return $this->textResponse($this->pubOcr, 'parse_ocr_output.txt'); }

    private function textResponse(string $publicPath, string $fileOnly): Response
    {
        // Try public disk first
        if (Storage::disk('public')->exists($publicPath)) {
            $txt = Storage::disk('public')->get($publicPath);
            return $this->ok($txt);
        }

        // Try non-public fallbacks (storage_path)
        foreach ($this->fallbackRoots as $root) {
            $abs = storage_path($root . '/' . $fileOnly);  // e.g., storage/app/cog/parse_qr_output.txt
            if (is_file($abs)) {
                return $this->ok(file_get_contents($abs));
            }
        }

        return response("File not found: {$publicPath}\n", 404)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    private function ok(string $txt): Response
    {
        return response($txt, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
}
