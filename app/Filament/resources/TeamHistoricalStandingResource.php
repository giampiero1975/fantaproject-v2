<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamHistoricalStandingResource\Pages;
use App\Filament\Resources\TeamHistoricalStandingResource\RelationManagers;
use App\Models\TeamHistoricalStanding;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TeamHistoricalStandingResource extends Resource
{
    protected static ?string $model = TeamHistoricalStanding::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days'; // Cambiamo icona per allinearla
    protected static ?string $navigationLabel = '3. Storico Classifiche';
    protected static ?string $navigationGroup = 'Setup Dati';
    protected static ?int $navigationSort = 3;
    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    // In TeamHistoricalStandingResource.php
    public static function table(Table $table): Table
    {
        return $table
        ->columns([
            Tables\Columns\TextColumn::make('season_year')->label('Anno')->sortable(),
            Tables\Columns\TextColumn::make('team.name')->label('Squadra')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('league_name')
                ->label('Serie')
                ->badge()
                ->sortable()
                ->searchable()
                ->color(fn (?string $state): string => match($state) {
                    'Serie A' => 'success',
                    'Serie B' => 'warning',
                    default   => 'gray',
                }),
            Tables\Columns\TextColumn::make('position')->label('Pos.')->sortable(),
            Tables\Columns\TextColumn::make('points')->label('Punti')->sortable(),
            Tables\Columns\TextColumn::make('data_source')
            ->label('Fonte')
            ->badge()
            ->color(fn (string $state): string => match ($state) {
                'API' => 'success',
                'SCRAPER' => 'warning',
                default => 'gray',
        }),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('season_year')
                ->label('Stagione')
                ->options(TeamHistoricalStanding::query()->distinct()->pluck('season_year', 'season_year')->toArray()),
            Tables\Filters\SelectFilter::make('league_name')
                ->label('Serie')
                ->options([
                    'Serie A' => 'Serie A',
                    'Serie B' => 'Serie B',
                ]),
        ]);
    }
    
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getWidgets(): array
    {
        return [
            TeamHistoricalStandingResource\Widgets\CoverageGuideWidget::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeamHistoricalStandings::route('/'),
            'coverage' => Pages\CoverageStandings::route('/coverage'), // <--- QUESTA
        ];
    }
}
