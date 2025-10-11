<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExtractedController extends Controller
{
    public function store(Request $request)
    {
        // Save whatever the page sends (meta/rows/totals/raw_text) for auditing
        $payload = $request->all();

        $dir = storage_path('app/cog');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $file = $dir . DIRECTORY_SEPARATOR . 'parsed_' . now()->format('Ymd_His') . '.json';
        file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

        return response()->json(['ok' => true, 'saved' => $file]);
    }
}
