<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ReceiptController
 *
 * Serves private receipt files (deposit / withdrawal proof-of-transfer images)
 * via a Laravel signed URL, since the 'private' disk is local — not S3 — and
 * therefore has no built-in temporary-URL mechanism of its own.
 *
 * GET /api/v1/receipts/{path}  (must have a valid signature query string)
 */
class ReceiptController extends Controller
{
    public function show(Request $request, string $path): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'This link has expired or is invalid.');

        if (! Storage::disk('private')->exists($path)) {
            abort(404, 'Receipt not found.');
        }

        return Storage::disk('private')->response($path);
    }
}
