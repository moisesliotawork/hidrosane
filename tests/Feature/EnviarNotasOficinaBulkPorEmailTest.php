<?php

namespace Tests\Feature;

use App\Enums\EstadoTerminal;
use App\Events\NotasEnviadasAOficinaBulk;
use App\Listeners\EnviarNotasOficinaBulkPorEmail;
use App\Mail\NotasEnviadasAOficinaMail;
use App\Models\Customer;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EnviarNotasOficinaBulkPorEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_envia_correo_con_pdf_para_notas_en_sala_no_impresas(): void
    {
        Mail::fake();

        $comercial = User::factory()->create(['name' => 'Comercial Test']);
        $customer = Customer::factory()->create();

        $note = Note::factory()->create([
            'user_id' => $comercial->id,
            'customer_id' => $customer->id,
            'comercial_id' => $comercial->id,
            'estado_terminal' => EstadoTerminal::SALA->value,
            'printed' => false,
        ]);

        app(EnviarNotasOficinaBulkPorEmail::class)->handle(
            new NotasEnviadasAOficinaBulk([$note->id], $comercial)
        );

        Mail::assertSent(NotasEnviadasAOficinaMail::class, function (NotasEnviadasAOficinaMail $mail) use ($note) {
            return $mail->hasTo('info@ohanadistribucion.com')
                && $mail->notes->pluck('id')->contains($note->id)
                && str_ends_with($mail->filename, '.pdf')
                && $mail->pdfContent !== '';
        });
    }

    public function test_no_envia_correo_si_las_notas_ya_estan_impresas(): void
    {
        Mail::fake();

        $comercial = User::factory()->create();
        $customer = Customer::factory()->create();

        $note = Note::factory()->create([
            'user_id' => $comercial->id,
            'customer_id' => $customer->id,
            'comercial_id' => $comercial->id,
            'estado_terminal' => EstadoTerminal::SALA->value,
            'printed' => true,
        ]);

        app(EnviarNotasOficinaBulkPorEmail::class)->handle(
            new NotasEnviadasAOficinaBulk([$note->id], $comercial)
        );

        Mail::assertNothingSent();
    }

    public function test_el_correo_no_marca_las_notas_como_impresas(): void
    {
        Mail::fake();

        $comercial = User::factory()->create();
        $customer = Customer::factory()->create();

        $note = Note::factory()->create([
            'user_id' => $comercial->id,
            'customer_id' => $customer->id,
            'comercial_id' => $comercial->id,
            'estado_terminal' => EstadoTerminal::SALA->value,
            'printed' => false,
        ]);

        app(EnviarNotasOficinaBulkPorEmail::class)->handle(
            new NotasEnviadasAOficinaBulk([$note->id], $comercial)
        );

        $this->assertFalse($note->fresh()->printed);
    }
}
