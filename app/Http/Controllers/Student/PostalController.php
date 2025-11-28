<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class PostalController extends Controller
{
    /* ========== helpers ========== */
    private static function norm(?string $s): string
    {
        $s = (string)$s;
        $s = preg_replace("/[’'`]/u", '', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        $s = preg_replace('/^\s*(city of|municipality of)\s+/i', '', $s);
        $s = preg_replace('/\s*\(.*?\)\s*/', '', $s);
        $s = preg_replace('/\s+city$/i', '', $s);
        $s = str_replace('ñ', 'n', $s);
        return strtolower(trim($s));
    }
    private static function score(string $needle, string $hay): int
    {
        if ($needle === '' || $hay === '') return 0;
        if ($needle === $hay) return 3;                 // exact
        if (Str::startsWith($hay, $needle)) return 2;   // prefix
        if (str_contains($hay, $needle)) return 1;      // contains
        return 0;
    }
    private static function uniqueByZip(array $rows): array
    {
        $seen = []; $out = [];
        foreach ($rows as $r) {
            $z = (string)($r['zip'] ?? '');
            if ($z === '' || isset($seen[$z])) continue;
            $seen[$z] = true; $out[] = $r;
        }
        return $out;
    }

    /* ===== seed for totally-offline dev (expand as you like) ===== */
    private static function seedFallback(): array
    {
        return [
            // province, city/municipality, barangay(optional), zip
            ['province' => 'Batangas', 'city' => 'Tuy', 'barangay' => '', 'postalCode' => '4214'],
        ];
    }

    /* ===== dataset loaders ===== */
    private function loadDatasetRaw(): array
    {
        return Cache::remember('postal-ph:raw:v2', now()->addDay(), function () {
            // 1) CDN (often fails on dev boxes with SSL)
            foreach ([
                'https://unpkg.com/use-postal-ph@latest/dist/data.json',
                'https://cdn.jsdelivr.net/npm/use-postal-ph/dist/data.json',
            ] as $url) {
                try {
                    $res = Http::withoutVerifying()->timeout(10)->acceptJson()->get($url);
                    if ($res->ok()) {
                        $json = $res->json();
                        if (is_array($json) || is_object($json)) return $json;
                    }
                } catch (\Throwable $e) { /* try next */ }
            }

            // 2) Local copy (place a file if you want full coverage offline)
            foreach ([
                resource_path('data/use-postal-ph.json'),
                base_path('use-postal-ph.json'),
                storage_path('app/use-postal-ph.json'),
            ] as $local) {
                if (is_readable($local)) {
                    try {
                        $json = json_decode(file_get_contents($local), true);
                        if (is_array($json) || is_object($json)) return $json;
                    } catch (\Throwable $e) {}
                }
            }

            // 3) Minimal seed so things still work
            return self::seedFallback();
        });
    }

    private function loadDatasetFlat(): array
    {
        return Cache::remember('postal-ph:flat:v2', now()->addDay(), function () {
            $raw = $this->loadDatasetRaw();
            $rows = [];
            $regions = is_array($raw) ? $raw : (array)$raw;

            // Dataset may already be "flat" (our seed)
            $looksFlat = isset($regions[0]['province']) && isset($regions[0]['city']);
            if ($looksFlat) return $regions;

            foreach ($regions as $region) {
                if (!$region) continue;
                $provinces = $region['provinces'] ?? $region['Provinces'] ?? [];
                if (!is_array($provinces)) continue;

                foreach ($provinces as $prov) {
                    $provinceName = $prov['name'] ?? $prov['Name'] ?? $prov['province'] ?? $prov['Province'] ?? '';
                    if ($provinceName === '') continue;

                    $municipalities = $prov['municipalities'] ?? $prov['Municipalities'] ?? $prov['cities'] ?? $prov['Cities'] ?? [];
                    if (!is_array($municipalities)) continue;

                    foreach ($municipalities as $muni) {
                        $cityName = $muni['name'] ?? $muni['Name'] ?? $muni['municipality'] ?? $muni['city'] ?? $muni['City'] ?? '';
                        if ($cityName === '') continue;

                        $muniPostal = $muni['postalCode'] ?? $muni['postal_code'] ?? $muni['zip'] ?? null;
                        $barangays  = $muni['barangays'] ?? $muni['Barangays'] ?? $muni['brgys'] ?? [];

                        if (is_array($barangays) && !empty($barangays)) {
                            foreach ($barangays as $brgy) {
                                if (is_string($brgy)) {
                                    $rows[] = [
                                        'province'   => $provinceName,
                                        'city'       => $cityName,
                                        'barangay'   => $brgy,
                                        'postalCode' => $muniPostal,
                                    ];
                                } elseif (is_array($brgy)) {
                                    $bName = $brgy['name'] ?? $brgy['Name'] ?? $brgy['barangay'] ?? '';
                                    $bZip  = $brgy['postalCode'] ?? $brgy['postal_code'] ?? $brgy['zip'] ?? $muniPostal;
                                    if ($bName !== '') {
                                        $rows[] = [
                                            'province'   => $provinceName,
                                            'city'       => $cityName,
                                            'barangay'   => $bName,
                                            'postalCode' => $bZip,
                                        ];
                                    }
                                }
                            }
                        } else {
                            if ($muniPostal) {
                                $rows[] = [
                                    'province'   => $provinceName,
                                    'city'       => $cityName,
                                    'barangay'   => '',
                                    'postalCode' => $muniPostal,
                                ];
                            }
                        }
                    }
                }
            }
            return $rows;
        });
    }

    public function data(): JsonResponse
    {
        return response()->json(['data' => $this->loadDatasetFlat()]);
    }

    /* ========== /postal-ph/zip endpoint ========== */
    public function zip(Request $req): JsonResponse
    {
        $province = (string)$req->query('province', '');
        $city     = (string)$req->query('city', '');
        $barangay = (string)$req->query('barangay', '');

        // Ignore placeholders like "Select Barangay"
        if (preg_match('/^select (region|province|city|municipality|barangay)$/i', trim($barangay))) {
            $barangay = '';
        }

        if ($province === '' || $city === '') {
            return response()->json(['zip' => null, 'candidates' => [], 'message' => 'province and city are required']);
        }

        $np = self::norm($province);
        $nc = self::norm($city);
        $nb = self::norm($barangay);

        $rows = $this->loadDatasetFlat();
        $candidates = [];

        foreach ($rows as $row) {
            $rp = self::norm($row['province'] ?? '');
            $rc = self::norm($row['city'] ?? '');
            $rb = self::norm($row['barangay'] ?? '');
            $rz = $row['postalCode'] ?? $row['postal_code'] ?? $row['zip'] ?? null;
            if (!$rz) continue;

            $score = self::score($np, $rp) + self::score($nc, $rc);
            if ($score < 2) continue;            // must roughly match province+city
            if ($nb !== '') $score += self::score($nb, $rb);

            $candidates[] = [
                'zip' => (string)$rz,
                'province' => $row['province'] ?? '',
                'city'     => $row['city'] ?? '',
                'barangay' => $row['barangay'] ?? '',
                'score'    => $score,
                'source'   => 'dataset',
            ];
        }

        if (empty($candidates)) {
            // last resort: exact match in seed (covers your case)
            foreach (self::seedFallback() as $r) {
                if (self::norm($r['province']) === $np && self::norm($r['city']) === $nc) {
                    $zip = (string)($r['postalCode'] ?? '');
                    if ($zip !== '') {
                        return response()->json([
                            'zip' => $zip,
                            'candidates' => [['zip' => $zip, 'province' => $r['province'], 'city' => $r['city'], 'barangay' => '', 'score' => 9, 'source' => 'seed']],
                            'source' => 'seed'
                        ]);
                    }
                }
            }
            return response()->json(['zip' => null, 'candidates' => [], 'message' => 'No postal code found']);
        }

        usort($candidates, function ($a, $b) use ($nb) {
            if ($a['score'] !== $b['score']) return $b['score'] <=> $a['score'];
            if ($nb === '') return 0;
            return levenshtein(self::norm($a['barangay']), $nb)
                 <=> levenshtein(self::norm($b['barangay']), $nb);
        });

        $uniq = self::uniqueByZip($candidates);

        return response()->json([
            'zip' => $uniq[0]['zip'] ?? null,
            'candidates' => $uniq,
            'source' => $uniq[0]['source'] ?? 'dataset',
        ]);
    }
}
