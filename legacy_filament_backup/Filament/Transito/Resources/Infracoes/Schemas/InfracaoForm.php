<?php

namespace App\Filament\Transito\Resources\Infracoes\Schemas;

use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class InfracaoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dados da infração')->schema([
                TextInput::make('tipo')->required()->maxLength(80),
                TextInput::make('codigo')->label('Código')->maxLength(40),
                TextInput::make('placa')->maxLength(10),
                DateTimePicker::make('ocorrido_em')->label('Data e hora')->required()->default(now()),
                Select::make('via_transito_id')->label('Via')->relationship('via', 'nome')->searchable()->preload(),
                Select::make('distrito_id')->label('Distrito')->relationship('distrito', 'nome')->searchable()->preload(),
                TextInput::make('valor')->numeric()->prefix('R$')->minValue(0),
                TextInput::make('pontos')->numeric()->default(0)->minValue(0)->maxValue(20),
                Select::make('situacao')->label('Situação')->options([
                    'registrada' => 'Registrada',
                    'notificada' => 'Notificada',
                    'cancelada' => 'Cancelada',
                    'paga' => 'Paga',
                ])->default('registrada')->required(),
                Textarea::make('descricao')->label('Descrição')->columnSpanFull()->rows(4)->maxLength(500),
            ])->columns(2),
            Section::make('Localização')->description('Marque no mapa o local exato da ocorrência.')->schema([
                Hidden::make('latitude')->required(),
                Hidden::make('longitude')->required(),
                Map::make('location')
                    ->label('Local da infração')
                    ->defaultLocation(latitude: -16.07083, longitude: -57.68750)
                    ->afterStateUpdated(function (Set $set, ?array $state): void {
                        $set('latitude', $state['lat'] ?? null);
                        $set('longitude', $state['lng'] ?? null);
                    })
                    ->afterStateHydrated(function (Map $component, Get $get): void {
                        if ($get('latitude') && $get('longitude')) {
                            $component->state(['lat' => (float) $get('latitude'), 'lng' => (float) $get('longitude')]);
                        }
                    })
                    ->columnSpanFull(),
            ]),
        ]);
    }
}
