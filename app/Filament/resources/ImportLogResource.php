<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImportLogResource\Pages;
use App\Filament\Resources\ImportLogResource\RelationManagers;
use App\Models\ImportLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ImportLogResource extends Resource
{
    protected static ?string $model = ImportLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list'; // Icona che richiama i log
    protected static ?string $navigationLabel = 'Log Importazioni';
    protected static ?string $navigationGroup = 'Setup Dati'; // Stesso gruppo degli altri
    protected static ?int $navigationSort = 99; // Lo mettiamo per ultimo nel gruppo

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->columns([
            Tables\Columns\TextColumn::make('created_at')
            ->label('Data/Ora')
            ->dateTime('d/m/Y H:i')
            ->sortable(),
            Tables\Columns\TextColumn::make('import_type')
            ->label('Tipo')
            ->badge()
            ->color(fn (string $state): string => match ($state) {
                'teams_api' => 'warning',
                'giocatori' => 'info',
                'storico_classifiche' => 'success',
                default => 'gray',
        }),
        Tables\Columns\TextColumn::make('status')
        ->label('Status')
        ->badge()
        ->color(fn (string $state): string => match ($state) {
            'success', 'completed' => 'success',
            'failed', 'error' => 'danger',
            'processing' => 'warning',
            default => 'gray',
        }),
        Tables\Columns\TextColumn::make('rows_created') // NOME ESATTO DB
        ->label('Creati'),
        Tables\Columns\TextColumn::make('rows_updated') // NOME ESATTO DB
        ->label('Aggiornati'),
        Tables\Columns\TextColumn::make('details') // NOME ESATTO DB PER L'ESITO
        ->label('Esito Operazione')
        ->wrap(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('import_type')
            ->label('Tipo Importazione')
            ->options([
                'teams_api' => 'Teams API',
                'giocatori' => 'Giocatori',
                'storico_classifiche' => 'Storico Classifiche',
            ]),
        ])
        ->defaultSort('created_at', 'desc');
    }
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImportLogs::route('/'),
            // 'create' e 'edit' non servono per i Log, meglio non averli
        ];
    }

    }
