<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class AttachmentsController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:20480', // 20MB
            'kind' => 'required|in:cor,cog',
        ]);

        $file = $request->file('file');
        $kind = $request->input('kind');
        $userId = Auth::id() ?? 'guest';

        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $basename = Str::slug($basename);
        $ext = strtolower($file->getClientOriginalExtension());

        $relative = "uploads/{$userId}/{$kind}/" . now()->format('Ymd_His') . "_{$basename}.{$ext}";
        Storage::disk('public')->putFileAs(dirname($relative), $file, basename($relative));

        return response()->json([
            // absolute path (Blade expects this for PDF generation)
            'path'       => Storage::disk('public')->path($relative),
            // relative (optional)
            'relative'   => $relative,
            // public URL for previews
            'public_url' => Storage::url($relative),
        ]);
    }
}
