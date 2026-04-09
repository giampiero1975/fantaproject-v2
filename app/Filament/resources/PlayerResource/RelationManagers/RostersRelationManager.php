<?php

namespace App\Filament\Resources\PlayerResource\RelationManagers;

use App\Helpers\SeasonHelper;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RostersRelationManager extends RelationManager
{
    protected static string $relationship = 'rosters';

    protected static ?string $title = 'Carriera (Roster per Stagione)';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('season_id')
            ->modifyQueryUsing(fn ($query) => $query->orderBy('season_id', 'desc'))
            ->columns([
                Tables\Columns\TextColumn::make('season.season_year')
                    ->label('Stagione')
                    ->formatStateUsing(fn ($state) => SeasonHelper::formatYear($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('team.name')
                    ->label('Squadra')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Ruolo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'P' => 'warning',
                        'D' => 'success',
                        'C' => 'primary',
                        'A' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('detailed_position')
                    ->label('Mantra')
                    ->badge()
                    ->color('gray')
                    ->separator(','),
                Tables\Columns\TextColumn::make('initial_quotation')
                    ->label('Qt. Iniz.')
                    ->numeric(),
                Tables\Columns\TextColumn::make('current_quotation')
                    ->label('Qt. Att.')
                    ->numeric(),
                Tables\Columns\TextColumn::make('fvm')
                    ->label('FVM')
                    ->numeric(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }
}
