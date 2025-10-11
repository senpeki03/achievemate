<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CogDebugController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['text' => 'required|string']);
        $dir = storage_path('app/cog');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $stamp = now()->format('Y-m-d H:i:s');
        file_put_contents(
            $dir . DIRECTORY_SEPARATOR . 'debug_raw_text.txt',
            "[$stamp]\n" . $request->string('text') . "\n\n",
            FILE_APPEND
        );

        return response()->json(['ok' => true]);
    }
}
