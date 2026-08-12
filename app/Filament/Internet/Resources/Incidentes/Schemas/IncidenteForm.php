<?php
namespace App\Filament\Internet\Resources\Incidentes\Schemas;
use Dotswan\MapPicker\Fields\Map; use Filament\Forms\Components\DateTimePicker; use Filament\Forms\Components\Hidden; use Filament\Forms\Components\Select; use Filament\Forms\Components\Textarea; use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section; use Filament\Schemas\Components\Utilities\Get; use Filament\Schemas\Components\Utilities\Set; use Filament\Schemas\Schema;
class IncidenteForm
{
    public static function configure(Schema $schema): Schema { return $schema->components([
        Section::make('Ocorrência')->schema([
            Select::make('tipo')->options(['rompimento_fibra'=>'Rompimento de fibra','queda_servico'=>'Queda de serviço','degradacao'=>'Lentidão ou degradação','falha_antena'=>'Falha de antena','falha_equipamento'=>'Falha de equipamento','interferencia'=>'Interferência de sinal','vandalismo'=>'Vandalismo','outro'=>'Outro'])->required(),
            Select::make('causa')->options(['clima'=>'Evento climático','obra'=>'Obra ou escavação','acidente'=>'Acidente','energia'=>'Falha de energia','equipamento'=>'Falha de equipamento','configuracao'=>'Configuração','sobrecarga'=>'Sobrecarga','vandalismo'=>'Vandalismo','desconhecida'=>'Desconhecida','outra'=>'Outra'])->searchable(),
            DateTimePicker::make('ocorrido_em')->label('Data e hora')->default(now())->required(), DateTimePicker::make('resolvido_em')->label('Resolvido em'),
            Select::make('severidade')->options(['baixa'=>'Baixa','media'=>'Média','alta'=>'Alta','critica'=>'Crítica'])->default('media')->required(), Select::make('situacao')->label('Situação')->options(['registrado'=>'Registrado','em_atendimento'=>'Em atendimento','resolvido'=>'Resolvido','cancelado'=>'Cancelado'])->default('registrado')->required(),
            TextInput::make('usuarios_afetados')->label('Usuários afetados')->numeric()->minValue(0), Textarea::make('descricao')->label('Descrição')->columnSpanFull(),])->columns(2),
        Section::make('Ativo afetado')->description('Relacione a falha ao ativo de telecomunicações quando cadastrado.')->schema([
            Select::make('distrito_id')->label('Distrito')->relationship('distrito','nome')->searchable()->preload(), Select::make('antena_id')->label('Antena')->relationship('antena','codigo_patrimonio')->searchable()->preload(),
            Select::make('cabeamento_id')->label('Cabeamento')->relationship('cabeamento','nome')->searchable()->preload()->modifyQueryUsing(fn ($query) => $query->whereIn('tipo_cabo',['fibra_optica','fibra_backbone','cobre_telefonia'])),
            Select::make('equipamento_id')->label('Equipamento de rede')->relationship('equipamento','nome')->searchable()->preload(),])->columns(2),
        Section::make('Localização')->schema([Hidden::make('latitude')->required(),Hidden::make('longitude')->required(),Map::make('location')->label('Ponto afetado')->defaultLocation(latitude:-16.07083,longitude:-57.68750)->afterStateUpdated(function(Set $set,?array $state):void{$set('latitude',$state['lat']??null);$set('longitude',$state['lng']??null);})->afterStateHydrated(function(Map $component,Get $get):void{if($get('latitude')&&$get('longitude'))$component->state(['lat'=>(float)$get('latitude'),'lng'=>(float)$get('longitude')]);})->columnSpanFull()]),
    ]); }
}
