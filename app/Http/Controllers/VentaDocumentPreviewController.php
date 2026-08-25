<?php

namespace App\Http\Controllers;

use App\Support\Filament\VentaDocumentUpload;
use App\Support\Storage\DocumentStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VentaDocumentPreviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $path = DocumentStorage::normalize($request->query('path'));

        abort_unless(
            is_string($path) && str_starts_with($path, 'ventas/'),
            404
        );

        $contents = DocumentStorage::get($path);
        abort_unless($contents !== null, 404);

        $mime = VentaDocumentUpload::mimeFromPath($path) ?? 'application/octet-stream';

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            'Cache-Control' => 'private, max-age=120',
        ]);
    }
}
