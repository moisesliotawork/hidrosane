<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Filament\VentaDocumentUpload;
use Mockery;
use Tests\TestCase;

class VentaDocumentUploadTest extends TestCase
{
    protected function mockCommercialUser(string $empleadoId, string $email): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->empleado_id = $empleadoId;
        $user->email = $email;
        $user->shouldReceive('hasRole')->with('gerente')->andReturn(false);
        $user->shouldReceive('hasRole')->with('commercial')->andReturn(true);
        $user->shouldReceive('hasAnyRole')->with(['commercial', 'team_leader'])->andReturn(true);

        return $user;
    }
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

    public function test_precontractual_and_foto_sorteo_allow_pdf(): void
    {
        foreach (['precontractual', 'foto_sorteo', 'contrato_firmado', 'otros_documentos'] as $field) {
            $this->assertTrue(VentaDocumentUpload::fieldAllowsPdf($field));

            $types = VentaDocumentUpload::acceptedDocumentMimeTypes(true);
            $this->assertContains('application/pdf', $types);

            $accept = VentaDocumentUpload::acceptAttribute(true);
            $this->assertStringContainsString('application/pdf', $accept);
            $this->assertStringContainsString('.pdf', $accept);
        }
    }

    public function test_other_document_fields_do_not_allow_pdf_by_default(): void
    {
        filament()->setCurrentPanel(filament()->getPanel('comercial'));

        $this->assertFalse(VentaDocumentUpload::fieldAllowsPdf('dni_anverso', $this->mockCommercialUser('123', 'comercial@example.com')));
        $this->assertNotContains(
            'application/pdf',
            VentaDocumentUpload::acceptedDocumentMimeTypes(false),
        );
    }

    public function test_commercial_911_can_upload_pdf_in_any_document_field(): void
    {
        $user = $this->mockCommercialUser('911', 'contratos@gmail.com');

        foreach ([
            'precontractual',
            'foto_sorteo',
            'dni_anverso',
            'dni_reverso',
            'documento_titularidad',
            'nomina',
            'pension',
            'otros_documentos',
        ] as $field) {
            $this->assertTrue(
                VentaDocumentUpload::fieldAllowsPdf($field, $user),
                "El comercial 911 debería poder subir PDF en {$field}",
            );
        }
    }

    public function test_office_panels_allow_pdf_on_any_document_field(): void
    {
        foreach (['admin', 'gerente', 'superAdmin'] as $panelId) {
            filament()->setCurrentPanel(filament()->getPanel($panelId));

            $this->assertTrue(VentaDocumentUpload::isOfficeContractsPanel());
            $this->assertTrue(VentaDocumentUpload::fieldAllowsPdf('dni_anverso'));
            $this->assertTrue(VentaDocumentUpload::fieldAllowsPdf('otros_documentos'));
            $this->assertTrue(VentaDocumentUpload::fieldAllowsPdf('nomina'));
        }

        filament()->setCurrentPanel(filament()->getPanel('comercial'));
        $this->assertFalse(VentaDocumentUpload::isOfficeContractsPanel());
    }

    public function test_image_preview_skips_pdf_and_empty_state(): void
    {
        $this->assertSame('', VentaDocumentUpload::imagePreviewMarkup(null));
        $this->assertSame('', VentaDocumentUpload::imagePreviewMarkup(''));
        $this->assertSame('', VentaDocumentUpload::imagePreviewMarkup('ventas/contrato.pdf'));
        $this->assertSame('', VentaDocumentUpload::imagePreviewMarkup(['ventas/contrato.pdf']));
    }

    public function test_image_preview_tag_shows_a_piece_of_the_image(): void
    {
        $html = VentaDocumentUpload::imagePreviewTag('https://cdn.example/foto.jpg');

        $this->assertStringContainsString('<img src="https://cdn.example/foto.jpg"', $html);
        $this->assertStringContainsString('max-h-48', $html);
        $this->assertStringContainsString('object-contain', $html);
    }
}
