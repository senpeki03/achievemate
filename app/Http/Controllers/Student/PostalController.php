<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;

class PostalController extends Controller
{
    /** ------------ utils ------------ */
    private static function norm(?string $s): string
    {
        $s = (string) $s;
        $s = preg_replace("/[’'`]/u", '', $s);          // Normalize apostrophes
        $s = preg_replace('/\s+/', ' ', $s);             // Normalize spaces
        $s = preg_replace('/^\s*City of\s+/i', '', $s);  // Remove "City of " prefix
        $s = preg_replace('/\s*\(.*?\)\s*/', '', $s);    // Remove anything in parentheses
        return strtolower(trim($s));                     // Convert to lowercase
    }

    /** Load & cache the use-postal-ph dataset from CDN */
    private function loadDataset(): array
    {
        return Cache::remember('postal-ph-data', 86400, function () {
            foreach ([
                'https://unpkg.com/use-postal-ph@latest/dist/data.json',
                'https://cdn.jsdelivr.net/npm/use-postal-ph/dist/data.json',
            ] as $url) {
                try {
                    $response = Http::timeout(10)->get($url);
                    if ($response->ok() && is_array($response->json())) {
                        return $response->json();
                    }
                } catch (\Throwable $e) {
                    // Try next source if the current one fails
                }
            }
            return [];  // Return empty array if both sources fail
        });
    }

    /** For client-side fallback/diagnostics */
    public function data(): JsonResponse
    {
        return response()->json(['data' => $this->loadDataset()]);
    }

    /**
     * Resolve ZIP code based on province, city, and optional barangay.
     * Example: GET /postal-ph/zip?province=Batangas&city=Nasugbu&barangay=Papaya
     */
    public function zip(Request $req): JsonResponse
    {
        // Fetch query parameters
        $province = (string) $req->query('province', '');
        $city     = (string) $req->query('city', '');
        $barangay = (string) $req->query('barangay', '');

        // Validation check for required province and city
        if ($province === '' || $city === '') {
            return response()->json(['zip' => null], 400);
        }

        // Normalize the inputs
        $normProvince = self::norm($province);
        $normCity     = self::norm($city);
        $normBarangay = self::norm($barangay);

        // Load postal data from cache
        $rows = $this->loadDataset();

        // 1) Match province and city/municipality (exact or partial match)
        $matches = [];
        foreach ($rows as $row) {
            $prov = self::norm($row['province'] ?? $row['Province'] ?? '');
            $muni = self::norm($row['municipality'] ?? $row['city'] ?? $row['City'] ?? '');
            if ($prov === $normProvince && ($muni === $normCity || str_contains($muni, $normCity))) {
                $matches[] = $row;
            }
        }

        // 2) If there are multiple matches, try to narrow down using barangay
        if (count($matches) > 1 && $normBarangay !== '') {
            foreach ($matches as $row) {
                if (self::norm($row['barangay'] ?? '') === $normBarangay) {
                    $zip = $row['postalCode'] ?? $row['postal_code'] ?? $row['zip'] ?? null;
                    return response()->json(['zip' => $zip]);
                }
            }
        }

        // 3) Return the first match if available
        if (!empty($matches)) {
            $zip = $matches[0]['postalCode'] ?? $matches[0]['postal_code'] ?? $matches[0]['zip'] ?? null;
            return response()->json(['zip' => $zip]);
        }

        // 4) Return a 404 if no matches found
        return response()->json(['zip' => null], 404);
    }
}
