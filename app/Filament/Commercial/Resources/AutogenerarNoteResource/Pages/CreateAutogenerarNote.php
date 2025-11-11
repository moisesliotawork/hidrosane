<?php

namespace App\Filament\Commercial\Resources\AutogenerarNoteResource\Pages;

use App\Filament\Commercial\Resources\AutogenerarNoteResource;
use App\Filament\Commercial\Resources\NoteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use App\Enums\NoteStatus;
use App\Enums\FuenteNotas;
use Illuminate\Support\Str;
use Carbon\Carbon;


class CreateAutogenerarNote extends CreateRecord
{
    protected static string $resource = AutogenerarNoteResource::class;

    public function getTitle(): string
    {
        return 'Autogenerar nota';
    }

    protected function getRedirectUrl(): string
    {
        return NoteResource::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Guardar')
                ->action('create'),

            Actions\CreateAction::make('createAnother')
                ->label('Guardar y crear otro')
                ->color('gray')
                ->action('createAnother'),

            Actions\Action::make('cancel')
                ->label('Cancelar')
                ->color('danger')
                ->url(NoteResource::getUrl('index')),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        $data['phone'] = preg_replace('/\D+/', '', (string) ($data['phone'] ?? ''));
        $sec = preg_replace('/\D+/', '', (string) ($data['secondary_phone'] ?? ''));
        $thr = preg_replace('/\D+/', '', (string) ($data['third_phone'] ?? ''));

        $data['secondary_phone'] = $sec === '' ? null : $sec;
        $data['third_phone'] = $thr === '' ? null : $thr;


        $normalizedFirstName = Str::slug(Str::lower($data['first_names']), '');
        $normalizedLastName = Str::slug(Str::lower($data['last_names']), '');


        $customer = Customer::query()
            ->whereRaw("LOWER(REPLACE(first_names, ' ', '')) = ?", [$normalizedFirstName])
            ->whereRaw("LOWER(REPLACE(last_names, ' ', '')) = ?", [$normalizedLastName])
            ->where('phone', $data['phone'])
            ->first();

        // Calcular edad desde fecha_nac (si viene)
        $fechaNac = $data['fecha_nac'] ?? null;
        $computedAge = null;
        if ($fechaNac) {
            try {
                $computedAge = Carbon::parse($fechaNac)->age;
            } catch (\Throwable $e) {
                $computedAge = null;
            }
        }


        if ($customer) {
            $customer->update([
                'secondary_phone' => $data['secondary_phone'] ?? $customer->secondary_phone,
                'email' => $data['email'] ?? $customer->email,
                'postal_code' => $data['postal_code'],
                'ciudad' => $data['ciudad'],
                'nro_piso' => $data['nro_piso'],
                'provincia' => $data['provincia'],
                'primary_address' => $data['primary_address'] ?? $customer->primary_address,
                'secondary_address' => $data['secondary_address'] ?? $customer->secondary_address,
                'edadTelOp' => $data['edadTelOp'] ?? $customer->edadTelOp,
            ]);
        } else {
            $customer = Customer::create([
                'first_names' => $data['first_names'],
                'last_names' => $data['last_names'],
                'phone' => $data['phone'],
                'secondary_phone' => $data['secondary_phone'] ?? null,
                'email' => $data['email'] ?? null,
                'postal_code' => $data['postal_code'],
                'ciudad' => $data['ciudad'],
                'nro_piso' => $data['nro_piso'],
                'provincia' => $data['provincia'],
                'primary_address' => $data['primary_address'] ?? null,
                'secondary_address' => $data['secondary_address'] ?? null,
                'parish' => null,
                'edadTelOp' => $data['edadTelOp'] ?? null,
            ]);
        }


        $data['user_id'] = Auth::id();      
        $data['customer_id'] = $customer->id;  


        $data['comercial_id'] = Auth::id();
        $data["assignment_date"] = now();


        $data['fuente'] = $data['fuente'] ?? FuenteNotas::CALLE->value;
        $data['status'] = $data['status'] ?? NoteStatus::CONTACTED->value;


        unset($data['edadTelOp']);

        return $data;
    }
}
