<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\DocumentoResource\Pages;
use App\Models\Documento;
use App\Support\Storage\DocumentStorage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DocumentoResource extends Resource
{
    protected static ?string $model = Documento::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationLabel = 'DOCUMENTOS';

    protected static ?string $modelLabel = 'Documento';

    protected static ?string $pluralModelLabel = 'Documentos';

    protected static ?string $breadcrumb = 'Documentos';

    protected static ?string $slug = 'documentos';

    protected static ?string $navigationGroup = 'OTROS';

    protected static ?int $navigationSort = 101;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('file_path')
                    ->label('Archivo (imagen o PDF)')
                    ->required()
                    ->disk(DocumentStorage::diskName())
                    ->directory('documentos-superadmin')
                    ->visibility(DocumentStorage::uploadVisibility())
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'image/gif',
                    ])
                    ->maxSize(20480)
                    ->downloadable()
                    ->openable()
                    ->storeFileNamesIn('original_name')
                    ->afterStateUpdated(function ($state, Forms\Set $set): void {
                        if ($state instanceof TemporaryUploadedFile) {
                            $set('mime_type', $state->getMimeType());
                            $set('original_name', $state->getClientOriginalName());
                        }
                    })
                    ->columnSpanFull(),

                Forms\Components\Hidden::make('mime_type'),
                Forms\Components\Hidden::make('original_name'),

                Forms\Components\Textarea::make('description')
                    ->label('Descripción / Info')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Documento')
                    ->schema([
                        Infolists\Components\TextEntry::make('original_name')
                            ->label('Documento')
                            ->state(fn (Documento $record): string => $record->displayName()),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Info')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('mime_type')
                            ->label('Tipo')
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Fecha de ingreso')
                            ->dateTime('d/m/Y H:i'),

                        Infolists\Components\TextEntry::make('uploadedBy')
                            ->label('Subido por')
                            ->state(function (Documento $record): string {
                                $user = $record->uploadedBy;
                                if (! $user) {
                                    return '—';
                                }

                                return trim($user->empleado_id.' '.$user->name.' '.$user->last_name);
                            }),

                        Infolists\Components\TextEntry::make('ver_archivo')
                            ->label('Archivo')
                            ->state('Abrir documento')
                            ->url(fn (Documento $record): ?string => $record->publicUrl())
                            ->openUrlInNewTab()
                            ->color('warning')
                            ->badge(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('documento')
                    ->label('Documento')
                    ->state(fn (Documento $record): string => $record->displayName())
                    ->searchable(query: function ($query, string $search) {
                        return $query->where(function ($q) use ($search) {
                            $q->where('original_name', 'like', "%{$search}%")
                                ->orWhere('file_path', 'like', "%{$search}%");
                        });
                    })
                    ->wrap()
                    ->limit(40),

                Tables\Columns\TextColumn::make('info')
                    ->label('Info')
                    ->state(fn (Documento $record): string => $record->infoPreview(20))
                    ->tooltip(fn (Documento $record): ?string => filled($record->description) ? $record->description : null)
                    ->searchable(query: function ($query, string $search) {
                        return $query->where('description', 'like', "%{$search}%");
                    }),

                Tables\Columns\TextColumn::make('ver')
                    ->label('Ver')
                    ->state('Ver')
                    ->badge()
                    ->color('warning')
                    ->url(fn (Documento $record): ?string => $record->publicUrl())
                    ->openUrlInNewTab()
                    ->tooltip('Abrir documento'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver')
                    ->icon('heroicon-o-eye'),
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square'),
                Tables\Actions\DeleteAction::make()
                    ->label('Borrar')
                    ->icon('heroicon-o-trash')
                    ->modalHeading('Borrar documento')
                    ->modalDescription(fn (Documento $record): string => 'Se archivará «'.$record->displayName().'». Podrás recuperarlo desde la papelera del sistema si hace falta.')
                    ->successNotificationTitle('Documento borrado'),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Sin documentos')
            ->emptyStateDescription('Sube una imagen o PDF con su descripción.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Subir documento'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentos::route('/'),
            'create' => Pages\CreateDocumento::route('/create'),
            'view' => Pages\ViewDocumento::route('/{record}'),
            'edit' => Pages\EditDocumento::route('/{record}/edit'),
        ];
    }
}
