<?php

namespace App\Filament\Admin\Resources\ActivityLogs\Schemas;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\KeyValueEntry;
class ActivityLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->components([
                        Section::make('Detalhes da Ação')
                            ->columnSpan(2)
                            ->components([
                                TextEntry::make('description')
                                    ->label('Ação')
                                    ->badge(),
                                TextEntry::make('subject_type')
                                    ->label('Modelo')
                                    ->formatStateUsing(fn ($state) => class_basename($state)),
                                TextEntry::make('causer.name')
                                    ->label('Usuário'),
                            ]),

                        Section::make('Timestamp')
                            ->columnSpan(1)
                            ->components([
                                TextEntry::make('created_at')
                                    ->label('Data/Hora')
                                    ->dateTime('d/m/Y H:i:s'),
                            ]),
                    ]),

                Section::make('Mudanças de Dados')
                    ->components([
                        Grid::make(2)
                            ->components([
                                KeyValueEntry::make('properties.old')
                                    ->label('Antes'),
                                KeyValueEntry::make('properties.attributes')
                                    ->label('Depois'),
                            ]),
                    ]),
            ]);
    }
}
