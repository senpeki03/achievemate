<?php

namespace App\Http\Controllers\Student;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class QrResolverController extends Controller
{
  public function resolve(Request $request)
  {
    $qrUrl = trim((string) $request->input('payload', ''));
    if ($qrUrl === '' || !preg_match('~^https?://~i', $qrUrl)) {
      return response()->json([
        'ok' => false,
        'error' => 'bad_payload',
        'message' => 'Payload must be a full http(s) URL.',
      ], 422);
    }

    $client = new Client([
      'timeout' => 20,
      'allow_redirects' => ['max' => 10, 'strict' => false, 'referer' => true],
      'headers' => [
        'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118 Safari/537.36',
        'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.9',
      ],
      'http_errors' => false,
      'verify' => true,
    ]);

    try {
      $res = $client->get($qrUrl);
    } catch (RequestException $e) {
      Log::warning('QR fetch failed', ['url' => $qrUrl, 'err' => $e->getMessage()]);
      return response()->json([
        'ok' => false,
        'error' => 'fetch_failed',
        'message' => $e->getMessage(),
        'qr_url' => $qrUrl,
      ], 200);
    }

    $status = $res->getStatusCode();
    $ct     = strtolower($res->getHeaderLine('Content-Type') ?: '');
    $body   = (string) $res->getBody();

    if ($status >= 400 || $body === '') {
      return response()->json([
        'ok' => false,
        'error' => 'empty_or_error',
        'message' => "Remote returned {$status} and an empty/blocked body. Use a full JWT QR and ensure it’s public.",
        'qr_url' => $qrUrl,
        'content_type' => $ct,
      ], 200);
    }

    // Normalize content-type (strip charset)
    if (($p = strpos($ct, ';')) !== false) $ct = substr($ct, 0, $p);

    // If it’s a PDF, dump and run pdftotext
    if ($ct === 'application/pdf' || preg_match('~%PDF-~', substr($body, 0, 8))) {
      $pdfPath = storage_path('app/cog/qr_fetch.pdf');
      $txtPath = storage_path('app/cog/cog_output.txt');

      if (!is_dir(dirname($pdfPath))) @mkdir(dirname($pdfPath), 0775, true);
      file_put_contents($pdfPath, $body);

      // Requires xpdf-utils or poppler; you already have pdftotext in your env
      $bin = trim(shell_exec('command -v pdftotext') ?? '');
      if ($bin === '') {
        return response()->json([
          'ok' => false,
          'error' => 'pdftotext_missing',
          'message' => 'pdftotext not found on server path.',
        ], 200);
      }

      $cmd = escapeshellcmd($bin).' -layout -nopgbrk '.escapeshellarg($pdfPath).' '.escapeshellarg($txtPath).' 2>&1';
      $out = shell_exec($cmd);
      $text = is_file($txtPath) ? file_get_contents($txtPath) : '';

      if ($text === '') {
        return response()->json([
          'ok' => false,
          'error' => 'pdftotext_empty',
          'message' => 'PDF fetched but produced no text.',
          'stderr' => trim((string)$out),
        ], 200);
      }

      return response()->json([
        'ok' => true,
        'source' => 'pdf',
        'qr_url' => $qrUrl,
        'text' => $text,
      ]);
    }

    // If it looks like JSON, see if server gave the text/html inside JSON
    if ($ct === 'application/json') {
      $json = json_decode($body, true);
      if (is_array($json)) {
        $text = $json['text'] ?? $json['html'] ?? null;
        if (is_string($text) && $text !== '') {
          return response()->json(['ok' => true, 'source' => 'json', 'qr_url' => $qrUrl, 'text' => strip_tags($text)]);
        }
      }
    }

    // Default: treat as HTML
    $html = $body;
    // Some servers compress without header—try to detect gzip
    if (substr($html, 0, 2) === "\x1f\x8b") $html = gzdecode($html);

    $html = trim($html);
    if ($html === '') {
      return response()->json([
        'ok' => false,
        'error' => 'html_empty',
        'message' => 'HTML response is empty after decode — likely a protected page or SPA shell. Use the PDF endpoint if available.',
        'qr_url' => $qrUrl,
      ], 200);
    }

    // Extract visible text (simple fallback)
    libxml_use_internal_errors(true);
    $dom = new \DOMDocument();
    $dom->loadHTML($html);
    $xpath = new \DOMXPath($dom);
    foreach ($xpath->query('//script|//style|//noscript') as $n) { $n->parentNode->removeChild($n); }
    $textNodes = $xpath->query('//text()[normalize-space()]');
    $text = '';
    foreach ($textNodes as $n) { $text .= preg_replace('/\s+/', ' ', $n->nodeValue)." \n"; }
    $text = trim($text);

    // Save for debugging like your current flow
    Storage::disk('local')->put('cog/cog_output.txt', $text."\n".$qrUrl."\n");

    return response()->json([
      'ok' => true,
      'source' => 'html',
      'qr_url' => $qrUrl,
      'text' => $text,
    ]);
  }
}
