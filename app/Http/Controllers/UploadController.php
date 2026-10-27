<?php

namespace App\Http\Controllers;

use App\Support\ChunkedUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives one slice of a large file. Movie and song uploads send their files
 * here piece by piece, then finish with a normal request that names the
 * upload_id so the pieces can be stitched together.
 */
class UploadController extends Controller
{
    public function chunk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'upload_id' => ['required', 'string', 'regex:/^[a-f0-9-]{36}$/'],
            'index' => ['required', 'integer', 'min:0', 'max:'.(ChunkedUpload::MAX_CHUNKS - 1)],
            'chunk' => ['required', 'file', 'max:'.(int) ceil(ChunkedUpload::CHUNK_BYTES / 1024 * 1.1)],
        ]);

        ChunkedUpload::storeChunk($request->user()->id, $data['upload_id'], (int) $data['index'], $request->file('chunk'));

        return response()->json(['ok' => true]);
    }
}
