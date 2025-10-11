<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PsgcProxyController extends Controller
{
    private function http()
    {
        $client = Http::acceptJson()
            ->baseUrl('https://psgc.cloud/api/v2')
            ->retry(2, 200)
            ->timeout(10);

        // Avoid Windows/XAMPP SSL issues locally
        if (app()->isLocal()) {
            $client = $client->withoutVerifying();
        }
        return $client;
    }

    public function regions()
    {
        try {
            $res = $this->http()->get('/regions');
            return response()->json($res->json(), $res->status());
        } catch (\Throwable $e) {
            Log::error('PSGC regions error: '.$e->getMessage());
            return response()->json(['message' => 'psgc regions fetch failed'], 502);
        }
    }

    public function provinces(string $region)
    {
        try {
            $res = $this->http()->get("/regions/{$region}/provinces");
            return response()->json($res->json(), $res->status());
        } catch (\Throwable $e) {
            Log::error('PSGC provinces error: '.$e->getMessage());
            return response()->json(['message' => 'psgc provinces fetch failed'], 502);
        }
    }

    public function cities(string $province)
    {
        try {
            $res = $this->http()->get("/provinces/{$province}/cities-municipalities");
            return response()->json($res->json(), $res->status());
        } catch (\Throwable $e) {
            Log::error('PSGC cities error: '.$e->getMessage());
            return response()->json(['message' => 'psgc cities fetch failed'], 502);
        }
    }

    public function barangays(string $city)
    {
        try {
            $res = $this->http()->get("/cities-municipalities/{$city}/barangays");
            return response()->json($res->json(), $res->status());
        } catch (\Throwable $e) {
            Log::error('PSGC barangays error: '.$e->getMessage());
            return response()->json(['message' => 'psgc barangays fetch failed'], 502);
        }
    }
}
