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
                ->maxLength(255),
                Forms\Components\TextInput::make('api_football_data_id')
                ->numeric()
                ->label('ID API'),
                Forms\Components\TextInput::make('fbref_url')
                ->url()
                ->label('URL FBref'),
            ])->columns(2),
        ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
        ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->reorder()->orderBy('tier', 'asc')->orderBy('posizione_media', 'asc'))
        ->defaultSort('tier', 'asc')
        ->columns([
            Tables\Columns\ImageColumn::make('crest_url')
            ->label('Logo')
            ->circular(),
            Tables\Columns\TextColumn::make('name')
            ->searchable()
            ->sortable(),
            Tables\Columns\TextColumn::make('tier')
            ->label('Tier')
            ->sortable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $direction): \Illuminate\Database\Eloquent\Builder {
                return $query
                    ->orderBy('tier', $direction)
                    ->orderBy('posizione_media', $direction);
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
            Tables\Columns\TextColumn::make('posizione_media')
            ->label('Pos. Media')
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
            // AGGIUNTA: Colonna Stagione
            Tables\Columns\TextColumn::make('season_year')
            ->label('Stagione')
            ->sortable()
            ->badge(),
            // AGGIUNTA: Stato Serie A con Icona
            Tables\Columns\IconColumn::make('serie_a_team')
            ->label('In A')
            ->boolean()
            ->sortable(),
        ])
        ->filters([
            // AGGIUNTA: Filtro per Stagione
            Tables\Filters\SelectFilter::make('season_year')
            ->label('Filtra per Anno')
            ->options([
                2023 => '2023',
                2024 => '2024',
                2025 => '2025',
            ]),
            // AGGIUNTA: Filtro per Serie A/B
            Tables\Filters\TernaryFilter::make('serie_a_team')
            ->label('Solo Serie A'),
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