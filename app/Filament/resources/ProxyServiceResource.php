<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProxyServiceResource\Pages;
use App\Models\ProxyService;
use App\Services\ProxyManagerService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Support\HtmlString;

class ProxyServiceResource extends Resource
{
    protected static ?string $model = ProxyService::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';
    
    protected static ?string $navigationGroup = 'Impostazioni di Sistema';
    
    protected static ?string $label = 'Proxy Service';
    protected static ?string $pluralLabel = 'Proxy Services';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('📘 Manuale Operativo Centrale Proxy')
                    ->collapsible()
                    ->compact()
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\Placeholder::make('manual_content')
                            ->hiddenLabel()
                            ->content(new HtmlString('
                                <div class="prose prose-sm max-w-none dark:prose-invert">
                                    <p><strong>Priorità:</strong> Il sistema usa il proxy con valore più basso (es: 1). Gestisce ScraperAPI, WebScraping.ai, Crawlbase e ScrapingBee.</p>
                                    <p><strong>Test Connessione:</strong> Verifica se la API Key è valida contattando <em>example.com</em>.</p>
                                    <p><strong>Sincronizza Saldo (Sync):</strong> Allinea i crediti mostrati nel database con quelli reali del portale (ScraperAPI, WebScraping.ai, Crawlbase, ScrapingBee).</p>
                                    <p><strong>is_active:</strong> Interruttore generale. Se disattivato, il proxy viene rimosso dalla rotazione.</p>
                                    <p><strong>Logs:</strong> Diagnostica in <code>storage/logs/Proxy_Services/[provider].log</code>.</p>
                                </div>
                            ')),
                    ]),
                Forms\Components\Section::make('Anagrafica Provider')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Es: ScraperAPI'),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('Es: scraperapi'),
                        Forms\Components\TextInput::make('base_url')
                            ->required()
                            ->url()
                            ->maxLength(255)
                            ->placeholder('Es: http://api.scraperapi.com'),
                    ])->columns(2),

                Forms\Components\Section::make('Credenziali (Criptate)')
                    ->schema([
                        Forms\Components\TextInput::make('api_key')
                            ->password()
                            ->revealable()
                            ->required()
                            ->columnSpanFull(),
                    ])->columns(1),

                Forms\Components\Section::make('Configurazione & Limiti')
                    ->schema([
                        Forms\Components\TextInput::make('limit_monthly')
                            ->numeric()
                            ->default(1000)
                            ->required(),
                        Forms\Components\TextInput::make('current_usage')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Forms\Components\TextInput::make('priority')
                            ->numeric()
                            ->default(1)
                            ->required(),
                        Forms\Components\TextInput::make('js_cost')
                            ->label('Costo JS')
                            ->numeric()
                            ->default(1)
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('priority')
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('limit_monthly')
                    ->label('Crediti Totali')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\ViewColumn::make('usage_stats')
                    ->label('Utilizzo')
                    ->view('filament.resources.proxy-service.columns.usage-stats'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Stato Attivo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('sync_balance')
                    ->label('Sync')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->action(function (ProxyService $record) {
                        try {
                            app(ProxyManagerService::class)->syncBalance($record);
                            Notification::make()
                                ->title('Sincronizzazione completata')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Errore sincronizzazione')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('test_connection')
                    ->label('Test')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->action(function (ProxyService $record) {
                        $success = app(ProxyManagerService::class)->testConnection($record);
                        if ($success) {
                            Notification::make()
                                ->title('Connessione riuscita!')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Connessione fallita')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProxyServices::route('/'),
            'create' => Pages\CreateProxyService::route('/create'),
            'edit' => Pages\EditProxyService::route('/{record}/edit'),
        ];
    }
}
