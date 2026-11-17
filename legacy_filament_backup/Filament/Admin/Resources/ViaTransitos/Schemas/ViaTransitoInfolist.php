<?php

namespace App\Filament\Admin\Resources\ViaTransitos\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ViaTransitoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('distrito.id')
                    ->label('Distrito')
                    ->placeholder('-'),
                TextEntry::make('nome')
                    ->placeholder('-'),
                TextEntry::make('osm_way_id')
                    ->label('ID OSM')
                    ->placeholder('-'),
                TextEntry::make('tipo')->placeholder('-'),
                TextEntry::make('numero_faixas')->label('Faixas')->placeholder('-'),
                TextEntry::make('sentido')->placeholder('-'),
                TextEntry::make('direcao_fluxo')
                    ->label('Direção do fluxo')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'inicio_fim' => 'Início → fim da geometria',
                        'fim_inicio' => 'Fim → início da geometria',
                        default => 'Ambos os sentidos',
                    }),
                TextEntry::make('limite_velocidade')->label('Limite (km/h)')->placeholder('-'),
                TextEntry::make('estado_pavimento')->label('Pavimento')->placeholder('-'),
                TextEntry::make('inclinacao_pista')->label('Inclinação (%)')->placeholder('Não informada'),
                TextEntry::make('nivel_congestionamento'),
                TextEntry::make('velocidade_media')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('volume_veiculos')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('impacto_manutencao'),
                TextEntry::make('ultima_atualizacao')
                    ->dateTime(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
