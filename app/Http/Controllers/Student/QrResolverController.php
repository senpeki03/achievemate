<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QrResolverController extends Controller
{
    public function resolve(Request $request)
    {
        $payload = trim((string)$request->input('payload', ''));
        if ($payload === '') {
            return response()->json(['error' => 'Empty payload'], 422);
        }

        // 1) If payload is base64 JSON → decode directly
        $decoded = $this->tryDecodeBase64Json($payload);
        if ($decoded) {
            return response()->json($decoded);
        }

        // 2) If raw JSON → return as-is
        $json = json_decode($payload, true);
        if (is_array($json)) {
            return response()->json($json);
        }

        // 3) If URL (typical: dione viewer) → fetch server-side and parse HTML
        if (!preg_match('~^https?://~i', $payload)) {
            return response()->json(['error' => 'Unsupported QR payload'], 422);
        }

        // --- Guardrail vs SSRF
        $u = parse_url($payload);
        if (!isset($u['scheme'], $u['host']) || !in_array($u['scheme'], ['http','https'])) {
            return response()->json(['error' => 'Invalid URL'], 422);
        }
        // Allow only BatStateU domains (adjust if needed)
        if (!preg_match('/(\.|^)batstate-u\.edu\.ph$/i', $u['host'])) {
            return response()->json(['error' => 'Blocked host'], 422);
        }

        try {
            $resp = Http::timeout(20)
                ->retry(2, 500)
                ->withHeaders([
                    'User-Agent' => 'AchieveMate/QRResolver (+https://your.domain)',
                    'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->get($payload);

            if (!$resp->ok()) {
                return response()->json(['error' => 'Upstream returned '.$resp->status()], 502);
            }

            $html = (string)$resp->body();

            $parsed = $this->parseGradesHtml($html);
            if (empty($parsed['grades'])) {
                return response()->json(['error' => 'Could not parse grades from page'], 422);
            }

            // optional audit
            $name = 'cog/qr_resolved_'.now()->format('Ymd_His').'_'.Str::random(4).'.json';
            Storage::put($name, json_encode($parsed, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

            return response()->json($parsed);

        } catch (\Throwable $e) {
            Log::error('QR resolve failed', ['err'=>$e->getMessage()]);
            return response()->json(['error' => 'Resolve failed', 'message'=>$e->getMessage()], 500);
        }
    }

    private function tryDecodeBase64Json(string $s): ?array
    {
        // tolerate URL-safe base64
        $s2 = strtr($s, '-_', '+/');
        if (preg_match('~^[A-Za-z0-9+/=]+$~', $s2)) {
            $bin = base64_decode($s2, true);
            if ($bin !== false) {
                $j = json_decode($bin, true);
                if (is_array($j)) return $j;
            }
        }
        return null;
    }

    private function parseGradesHtml(string $html): array
    {
        // Quick plain-text capture for header + totals
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));

        $grab = function(string $rx) use ($text) {
            if (preg_match($rx, $text, $m)) return trim($m[1]);
            return '';
        };

        $header = [
            'Fullname'      => $grab('/Fullname\s*:\s*(.+?)\s+SRCODE/i'),
            'SRCODE'        => $grab('/SRCODE\s*:\s*([0-9\-]+)/i'),
            'College'       => $grab('/College\s*:\s*(.+?)\s+Academic\s+Year/i'),
            'Academic Year' => $grab('/Academic\s*Year\s*:\s*([0-9\-]+)/i'),
            'Program'       => $grab('/Program\s*:\s*(.+?)\s+Semester/i'),
            'Semester'      => $grab('/Semester\s*:\s*([A-Z ]+)/i'),
            'Year Level'    => $grab('/Year\s*Level\s*:\s*([A-Za-z0-9]+)/i'),
        ];

        $gwa         = $grab('/General\s*Weighted\s*Average\s*\(GWA\)\s*([0-9.]+)/i');
        $totalUnits  = $grab('/Total\s*no\s*of\s*Units\s*([0-9.]+)/i');
        $totalCourse = $grab('/Total\s*no\s*of\s*Course\s*([0-9]+)/i');

        // DOM parse for rows
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $trs = $xpath->query('//tr');

        $grades = [];
        foreach ($trs as $tr) {
            /** @var \DOMElement $tr */
            $tds = [];
            foreach ($tr->getElementsByTagName('td') as $td) {
                $tds[] = trim(preg_replace('/\s+/', ' ', $td->textContent));
            }
            if (count($tds) < 6) continue;

            // Heuristic: [#, code, title, units, grade, section, instructor]
            // Sometimes first col is '#'
            $cols = $tds;

            // detect header/total rows
            $joined = strtoupper(implode(' ', $cols));
            if (str_contains($joined, 'COURSE CODE') || str_contains($joined, 'NOTHING FOLLOWS') ||
                str_contains($joined, 'TOTAL NO OF UNITS') || str_contains($joined, 'GENERAL WEIGHTED AVERAGE')) {
                continue;
            }

            // map
            if (count($cols) >= 7) {
                [$idx, $code, $title, $units, $grade, $section, $instructor] = array_pad($cols, 7, '');
            } else {
                // some variants merge idx/code → adjust
                $idx = $cols[0] ?? '';
                $code = $cols[1] ?? '';
                $title = $cols[2] ?? '';
                $units = $cols[3] ?? '';
                $grade = $cols[4] ?? '';
                $section = $cols[5] ?? '';
                $instructor = $cols[6] ?? '';
            }

            // sanity: valid course code
            if (!preg_match('/^[A-Z]{2,}\s*-?[A-Z]{0,3}\s*\d{2,4}[A-Z]?$/', $code)) {
                // try to split if code + title stuck
                if (preg_match('/^([A-Z]{2,}\s*-?[A-Z]{0,3}\s*\d{2,4}[A-Z]?)\s+(.+)$/', $code, $m)) {
                    $title = trim($m[2].' '.$title);
                    $code  = $m[1];
                } else {
                    continue;
                }
            }

            $grades[] = [
                'name'       => trim($code.' '.$title),
                'units'      => (float)preg_replace('/[^\d.]/','',$units),
                'grade'      => is_numeric($grade) ? (string)$grade : preg_replace('/[^\d.]/','',$grade),
                'section'    => $section,
                'instructor' => $instructor,
            ];
        }

        // compute totals if missing
        if ($totalUnits === '' && count($grades)) {
            $totalUnits = array_reduce($grades, fn($a,$g)=>$a + (float)$g['units'], 0);
        }
        if ($totalCourse === '' && count($grades)) {
            $totalCourse = count($grades);
        }

        return [
            'header'       => $header,
            'grades'       => $grades,
            'total_units'  => $totalUnits === '' ? null : (float)$totalUnits,
            'total_courses'=> $totalCourse === '' ? null : (int)$totalCourse,
            'gwa'          => $gwa === '' ? null : $gwa,
        ];
    }
}
