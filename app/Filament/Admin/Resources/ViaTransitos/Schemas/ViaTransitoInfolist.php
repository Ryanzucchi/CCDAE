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
