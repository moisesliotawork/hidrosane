<?php

namespace App\Filament\Gerente\Pages;

use App\Models\AppSetting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class PoliticasYComisiones extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Políticas y Comisiones';
    protected static ?string $title = 'Políticas y Comisiones en Rigor';
    protected static ?string $slug = 'politicas-comisiones';
    protected static string $view = 'filament.gerente.pages.politicas-comisiones';

    /** Estado del formulario */
    public ?array $data = [];

    /** Normaliza el valor del FileUpload a una ruta string (o null). */
    private function normalizePath(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            // Casos comunes
            if (array_key_exists('path', $value)) {
                return is_array($value['path']) ? $this->normalizePath($value['path']) : $value['path'];
            }
            if (array_key_exists(0, $value)) {
                return $this->normalizePath($value[0]);
            }
        }
        return null;
    }

    public function mount(): void
    {
        $current = AppSetting::get('politicas_comisiones_pdf') ?? ['path' => null];

        $this->form->fill([
            'pdf' => $this->normalizePath($current['path'] ?? null),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('pdf')
                    ->label('Documento PDF (Políticas y Comisiones)')
                    ->acceptedFileTypes(['application/pdf'])
                    ->directory('politicas')   // storage/app/public/politicas
                    ->disk('public')
                    ->visibility('public')
                    ->preserveFilenames()
                    ->enableOpen()
                    ->enableDownload()
                    // requerido solo si aún no hay archivo guardado
                    ->required(fn() => blank(
                        $this->normalizePath(data_get(AppSetting::get('politicas_comisiones_pdf'), 'path'))
                    ))
                    ->helperText('Sube el PDF oficial. Si ya existe, al subir uno nuevo lo reemplazarás.'),
            ])
            ->statePath('data');
    }

    /** Guardar / Reemplazar */
    public function save(): void
    {
        // Tomamos el estado actual del formulario (evita valores temporales)
        $state = $this->form->getState();
        $newPath = $this->normalizePath($state['pdf'] ?? null);

        // Ruta anterior (si había)
        $old = AppSetting::get('politicas_comisiones_pdf') ?? ['path' => null];
        $oldPath = $this->normalizePath($old['path'] ?? null);

        // Si subimos uno nuevo y existe uno anterior distinto -> borrar anterior
        if ($newPath && $oldPath && $newPath !== $oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        // Guardar SIEMPRE el string (no arrays) en el setting
        AppSetting::set('politicas_comisiones_pdf', ['path' => $newPath]);

        Notification::make()
            ->title('Documento actualizado correctamente.')
            ->success()
            ->send();
    }

    /** Enlace directo para ver/descargar en el blade */
    public function getCurrentPublicUrl(): ?string
    {
        $path = data_get(AppSetting::get('politicas_comisiones_pdf'), 'path');
        if (!$path)
            return null;

        // Usar la URL del disco "public" (coincide con lo que usa FileUpload)
        return Storage::disk('public')->url(ltrim($path, '/'));
    }
}
