<?php

namespace Tests\Unit;

use App\Support\Filament\VentaDocumentUpload;
use Tests\TestCase;

class VentaDocumentUploadTest extends TestCase
{
    public function test_accepts_heic_and_heif_for_iphone_photos(): void
    {
        $types = VentaDocumentUpload::acceptedImageMimeTypes();

        $this->assertContains('image/png', $types);
        $this->assertContains('image/x-png', $types);
        $this->assertContains('image/heic', $types);
        $this->assertContains('image/heif', $types);
        $this->assertContains('image/jpeg', $types);
    }

    public function test_accept_attribute_includes_png_explicitly(): void
    {
        $accept = VentaDocumentUpload::acceptAttribute();

        $this->assertStringContainsString('image/png', $accept);
        $this->assertStringContainsString('.png', $accept);
    }
}
