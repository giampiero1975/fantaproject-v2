<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamResource\Pages;
use App\Models\Team;
use App\Services\TeamDataService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use App\Helpers\SeasonHelper;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = '1. Squadre'; // Rinominiamolo per coerenza
    protected static ?string $navigationGroup = 'Setup Dati';
    protected static ?int $navigationSort = 1;
    
    public static function form(Form $form): Form
    {
        return $form
        ->schema([
            Forms\Components\Section::make('Dettagli Squadra')
            ->schema([
                Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->label('Nome Completo'),
                Forms\Components\TextInput::make('short_name')
                ->maxLength(255)
                ->label('Nome Breve (per matching)'),
                Forms\Components\TextInput::make('api_id')
                ->numeric()
                ->label('ID API'),
                Forms\Components\TextInput::make('fbref_url')
                ->url()
                ->label('URL FBref'),
                Forms\Components\TextInput::make('fbref_id')
                ->maxLength(20)
                ->label('ID FBref'),
            ])->columns(2),
        ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
        ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->reorder()->orderBy('tier_globale', 'asc')->orderBy('posizione_media_storica', 'asc'))
        ->defaultSort('tier_globale', 'asc')
        ->columns([
            Tables\Columns\ImageColumn::make('logo_url')
            ->label('Logo')
            ->circular(),
            Tables\Columns\TextColumn::make('name')
            ->searchable()
            ->sortable(),
            Tables\Columns\TextColumn::make('short_name')
            ->label('Nome Breve')
            ->searchable()
            ->sortable(),
            Tables\Columns\TextColumn::make('tier_globale')
            ->label('Tier Globale')
            ->sortable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $direction): \Illuminate\Database\Eloquent\Builder {
                return $query
                    ->orderBy('tier_globale', $direction)
                    ->orderBy('posizione_media_storica', $direction);
            })
            ->badge()
            ->color(fn (?string $state): string => match ((string) $state) {
                '1' => 'warning',
                '2' => 'primary',
                '3' => 'gray',
                '4' => 'danger',
                '5' => 'danger',
                default => 'gray',
            }),
            // AGGIUNTA: Colonna Posizione Media
            Tables\Columns\TextColumn::make('posizione_media_storica')
            ->label('Pos. Media Storica')
            ->numeric(
                decimalPlaces: 4,
                decimalSeparator: ',',
                thousandsSeparator: '.',
            )
            ->sortable(),
            // AGGIUNTA: Colonna Posizione Reale 2025
            Tables\Columns\TextColumn::make('pos_reale_2025')
            ->label('Pos. Reale 25')
            ->badge()
            ->color('success')
            ->state(function (\App\Models\Team $record) {
                return \Illuminate\Support\Facades\DB::table('team_historical_standings')
                    ->where('team_id', $record->id)
                    ->where('season_year', 2025)
                    ->value('position') ?? '-';
            }),
            // AGGIUNTA: Stato Serie A Corrente (InA)
            Tables\Columns\IconColumn::make('is_active_current')
            ->label('In Serie A')
            ->boolean()
            ->state(function (\App\Models\Team $record) {
                $currentSeason = \App\Models\Season::where('is_current', true)->first();
                if (!$currentSeason) return false;
                return $record->teamSeasons()
                    ->where('season_id', $currentSeason->id)
                    ->exists();
            }),
        ])
        ->filters([
            // NUOVO: Filtro Stagione (Dinamico)
            Tables\Filters\SelectFilter::make('season')
                ->label('Stagione')
                ->options(fn () => \App\Helpers\SeasonHelper::getPresentSeasons())
                ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
                    if (empty($data['value'])) return $query;
                    return $query->whereHas('teamSeasons', fn ($q) => $q->where('season_id', $data['value']));
                }),

            // AGGIUNTA: Filtro "Serie A Corrente"
            Tables\Filters\TernaryFilter::make('in_serie_a')
            ->label('Sola Serie A Corrente')
            ->queries(
                true: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->whereHas('teamSeasons', function($q) {
                    $currentSeason = \App\Models\Season::where('is_current', true)->first();
                    $q->where('season_id', $currentSeason?->id);
                }),
                false: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->whereDoesntHave('teamSeasons', function($q) {
                    $currentSeason = \App\Models\Season::where('is_current', true)->first();
                    $q->where('season_id', $currentSeason?->id);
                }),
            ),
            // AGGIUNTA: Filtro "Cerca-Buchi" FBref
            Tables\Filters\TernaryFilter::make('fbref_status')
            ->label('Stato FBref')
            ->placeholder('Tutti i record')
            ->trueLabel('Mappate')
            ->falseLabel('Da Allineare')
            ->queries(
                true: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->whereNotNull('fbref_id')->where('fbref_id', '!=', ''),
                false: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where(function($q) {
                    $q->whereNull('fbref_id')->orWhere('fbref_id', '');
                }),
            ),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('align_fbref')
                ->label('Allinea FBref')
                ->color('danger')
                ->icon('heroicon-o-link')
                ->hidden(fn ($record) => !empty($record->fbref_id))
                ->form([
                    Forms\Components\TextInput::make('fbref_url')
                        ->label('URL FBref')
                        ->helperText('https://fbref.com/en/squads/dc56fe14/Milan-Stats')
                        ->url()
                        ->required()
                        ->default(fn ($record) => $record->fbref_url),
                    Forms\Components\TextInput::make('fbref_id')
                        ->label('ID FBref')
                        ->helperText('Es: dc56fe14')
                        ->default(fn ($record) => $record->fbref_id),
                ])
                ->action(function (Team $record, array $data) {
                    try {
                        $service = app(\App\Services\TeamFbrefAlignmentService::class);
                        $success = $service->alignManual($record, $data['fbref_url'], $data['fbref_id'] ?? null);

                        if ($success) {

                            Notification::make()
                                ->title('Allineamento Riuscito')
                                ->body("Squadra '{$record->name}' mappata con successo.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Validazione Fallita')
                                ->body("L'URL fornito non è stato validato (possibile errore proxy o 404).")
                                ->danger()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Errore Tecnico')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ]);

    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
        ];
    }
    
    public static function getWidgets(): array
    {
        return [
            TeamResource\Widgets\TeamGuideWidget::class,
        ];
    }
}