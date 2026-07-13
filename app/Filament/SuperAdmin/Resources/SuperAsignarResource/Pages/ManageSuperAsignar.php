<?php

namespace App\Filament\SuperAdmin\Resources\SuperAsignarResource\Pages;

use App\Filament\SuperAdmin\Resources\SuperAsignarResource;
use App\Models\Note;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ManageSuperAsignar extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SuperAsignarResource::class;

    protected static string $view = 'filament.superAdmin.resources.super-asignar-resource.pages.manage-super-asignar';

    protected static ?string $title = 'Super_Asignar';

    public string $searchNroNota = '';

    public bool $searched = false;

    public ?int $foundNoteId = null;

    public ?string $notFoundMessage = null;

    /** @var array<string, mixed> */
    public array $assignmentData = [];

    public function getFoundNoteProperty(): ?Note
    {
        if ($this->foundNoteId === null) {
            return null;
        }

        return Note::query()
            ->with([
                'customer:id,first_names,last_names,phone,phone1_commercial,postal_code',
                'comercial:id,name,last_name,empleado_id',
                'user:id,empleado_id,name,last_name',
            ])
            ->find($this->foundNoteId);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(SuperAsignarResource::assignmentFormSchema())
            ->statePath('assignmentData');
    }

    public function searchNote(): void
    {
        $value = trim($this->searchNroNota);

        if ($value === '') {
            Notification::make()
                ->title('Introduce un número de nota')
                ->warning()
                ->send();

            return;
        }

        $normalized = SuperAsignarResource::normalizeNroNota($value);

        /** @var Note|null $note */
        $note = Note::query()
            ->with([
                'customer:id,first_names,last_names,phone,phone1_commercial,postal_code',
                'comercial:id,name,last_name,empleado_id',
                'user:id,empleado_id,name,last_name',
            ])
            ->where('nro_nota', $normalized)
            ->first();

        $this->searched = true;
        $this->searchNroNota = $normalized;

        if (! $note) {
            $this->foundNoteId = null;
            $this->notFoundMessage = "No se encontró ninguna nota con el número {$normalized}.";
            $this->assignmentData = [];
            $this->form->fill([]);

            return;
        }

        $this->foundNoteId = $note->id;
        $this->notFoundMessage = null;
        $this->assignmentData = [
            'comercial_id' => $note->reten
                ? '__RETEN__'
                : ($note->comercial_id ?: null),
            'assignment_date' => $note->assignment_date?->format('Y-m-d'),
        ];
        $this->form->fill($this->assignmentData);
    }

    public function assignNote(): void
    {
        $note = $this->foundNote;

        if (! $note instanceof Note) {
            Notification::make()
                ->title('Busca una nota antes de asignar')
                ->warning()
                ->send();

            return;
        }

        $data = $this->form->getState();

        SuperAsignarResource::applyAssignment($note, $data);

        $note->refresh();
        $this->foundNoteId = $note->id;
        $this->assignmentData = [
            'comercial_id' => $note->reten
                ? '__RETEN__'
                : ($note->comercial_id ?: null),
            'assignment_date' => $note->assignment_date?->format('Y-m-d'),
        ];
        $this->form->fill($this->assignmentData);
    }

    public function clearSearch(): void
    {
        $this->searchNroNota = '';
        $this->searched = false;
        $this->foundNoteId = null;
        $this->notFoundMessage = null;
        $this->assignmentData = [];
        $this->form->fill([]);
    }

    protected function getForms(): array
    {
        return ['form'];
    }
}
