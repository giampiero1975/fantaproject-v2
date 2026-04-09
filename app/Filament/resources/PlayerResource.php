<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlayerResource\Pages;
use App\Filament\Resources\PlayerResource\RelationManagers;
use App\Models\Player;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlayerResource extends Resource
{
    protected static ?string $model = Player::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = '6. Calciatori';
    protected static ?string $navigationGroup = 'Setup Dati';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Anagrafica Calciatore')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Nome Completo'),
                        Forms\Components\TextInput::make('fanta_platform_id')
                            ->numeric()
                            ->label('ID Fantapiattaforma'),
                        Forms\Components\Select::make('role')
                            ->options([
                                'P' => 'Portiere',
                                'D' => 'Difensore',
                                'C' => 'Centrocampista',
                                'A' => 'Attaccante',
                            ])
                            ->label('Ruolo Base'),
                        Forms\Components\Select::make('parent_team_id')
                            ->relationship('parentTeam', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Proprietà (Squadra Madre)'),
                        Forms\Components\TextInput::make('fbref_url')
                            ->url()
                            ->label('URL FBref'),
                        Forms\Components\TextInput::make('fbref_id')
                            ->label('ID FBref (chiaro)'),
                        Forms\Components\DatePicker::make('date_of_birth')
                            ->label('Data di Nascita'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Ruolo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'P' => 'warning',
                        'D' => 'success',
                        'C' => 'primary',
                        'A' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('detailed_position')
                    ->label('Mantra')
                    ->badge()
                    ->color('gray')
                    ->separator(',')
                    ->searchable(),
                
                // Squadra Attuale (via roster più recente)
                Tables\Columns\TextColumn::make('latestRoster.team.name')
                    ->label('Squadra')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->placeholder('-'),

                // Squadra Proprietaria
                Tables\Columns\TextColumn::make('parentTeam.short_name')
                    ->label('Proprietà')
                    ->badge()
                    ->color('warning')
                    ->placeholder('-'),

                // Stato Serie A (Basato su trashed)
                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->state(fn ($record) => $record->trashed() ? 'Ceduto' : 'Attivo')
                    ->color(fn ($state) => $state === 'Attivo' ? 'success' : 'danger'),
                
                Tables\Columns\IconColumn::make('fbref_url')
                    ->label('FBref')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->url(fn ($state) => $state, shouldOpenInNewTab: true)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make()
                    ->label('Solo Ceduti')
                    ->native(false),
                Tables\Filters\SelectFilter::make('role')
                    ->label('Ruolo')
                    ->options([
                        'P' => 'Portiere',
                        'D' => 'Difensore',
                        'C' => 'Centrocampista',
                        'A' => 'Attaccante',
                    ]),
                Tables\Filters\SelectFilter::make('team_id')
                    ->label('Squadra (Attuale)')
                    ->relationship('latestRoster.team', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('season_id')
                    ->label('Presente in Stagione')
                    ->relationship('rosters.season', 'season_year')
                    ->getOptionLabelFromRecordUsing(fn ($record) => \App\Helpers\SeasonHelper::formatYear($record->season_year)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RostersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlayers::route('/'),
            'create' => Pages\CreatePlayer::route('/create'),
            'view' => Pages\ViewPlayer::route('/{record}'),
            'edit' => Pages\EditPlayer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['latestRoster.team', 'rosters.season'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
