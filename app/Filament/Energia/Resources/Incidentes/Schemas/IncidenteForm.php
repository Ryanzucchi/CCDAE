<?php
namespace App\Filament\Energia\Resources\Incidentes\Schemas;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
class IncidenteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ocorrência')->schema([
                Select::make('tipo')->options(['surto'=>'Surto de energia','rompimento_cabo'=>'Rompimento de cabo','explosao_transformador'=>'Explosão de transformador','queda_poste'=>'Queda de poste','curto_circuito'=>'Curto-circuito','interrupcao'=>'Interrupção de fornecimento','outro'=>'Outro'])->required(),
                Select::make('causa')->options(['clima'=>'Evento climático','vegetacao'=>'Vegetação','acidente'=>'Acidente','sobrecarga'=>'Sobrecarga','falha_equipamento'=>'Falha de equipamento','vandalismo'=>'Vandalismo','manutencao'=>'Manutenção','desconhecida'=>'Desconhecida','outra'=>'Outra'])->searchable(),
                DateTimePicker::make('ocorrido_em')->label('Data e hora')->default(now())->required(),
                DateTimePicker::make('resolvido_em')->label('Resolvido em'),
                Select::make('severidade')->options(['baixa'=>'Baixa','media'=>'Média','alta'=>'Alta','critica'=>'Crítica'])->default('media')->required(),
                Select::make('situacao')->label('Situação')->options(['registrado'=>'Registrado','em_atendimento'=>'Em atendimento','resolvido'=>'Resolvido','cancelado'=>'Cancelado'])->default('registrado')->required(),
                Textarea::make('descricao')->label('Descrição')->columnSpanFull(),
            ])->columns(2),
            Section::make('Ativo afetado')->description('Relacione o incidente ao ativo danificado quando ele estiver cadastrado.')->schema([
                Select::make('distrito_id')->label('Distrito')->relationship('distrito','nome')->searchable()->preload(),
                Select::make('poste_id')->label('Poste')->relationship('poste','codigo_patrimonio')->searchable()->preload(),
                Select::make('cabeamento_id')->label('Cabeamento')->relationship('cabeamento','nome')->searchable()->preload(),
                Select::make('equipamento_id')->label('Equipamento')->relationship('equipamento','nome')->searchable()->preload(),
                Select::make('central_id')->label('Central')->relationship('central','nome')->searchable()->preload(),
            ])->columns(2),
            Section::make('Localização do dano')->schema([
                Hidden::make('latitude')->required(), Hidden::make('longitude')->required(),
                Map::make('location')->label('Ponto afetado')->defaultLocation(latitude:-16.07083, longitude:-57.68750)
                    ->afterStateUpdated(function(Set $set, ?array $state): void { $set('latitude',$state['lat']??null); $set('longitude',$state['lng']??null); })
                    ->afterStateHydrated(function(Map $component, Get $get): void { if($get('latitude')&&$get('longitude')) $component->state(['lat'=>(float)$get('latitude'),'lng'=>(float)$get('longitude')]); })->columnSpanFull(),
            ]),
        ]);
    }
}
