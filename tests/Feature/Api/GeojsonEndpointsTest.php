<?php

namespace Tests\Feature\Api;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GeojsonEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createGeoTables();
    }

    protected function tearDown(): void
    {
        foreach ([
            'equipamentos_infraestrutura',
            'antenas',
            'postes',
            'cabeamentos',
            'centrais_distribuicao',
            'via_transitos',
            'distritos',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_districts_endpoint_returns_a_valid_feature_collection(): void
    {
        DB::table('distritos')->insert([
            'id' => 10,
            'nome' => 'Centro',
            'latitude' => -16.0764,
            'longitude' => -57.6818,
            'cidade' => 'Cáceres',
            'geojson' => json_encode([
                'type' => 'Polygon',
                'coordinates' => [[
                    [-57.69, -16.08],
                    [-57.67, -16.08],
                    [-57.67, -16.07],
                    [-57.69, -16.08],
                ]],
            ]),
        ]);

        $this->getJson(route('api.distritos.geojson'))
            ->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.type', 'Feature')
            ->assertJsonPath('features.0.properties.id', 10)
            ->assertJsonPath('features.0.properties.nome', 'Centro')
            ->assertJsonPath('features.0.geometry.type', 'Polygon');
    }

    public function test_districts_endpoint_does_not_return_records_without_geometry(): void
    {
        DB::table('distritos')->insert([
            'nome' => 'Sem geometria',
            'latitude' => -16.0,
            'longitude' => -57.0,
            'cidade' => 'Cáceres',
            'geojson' => null,
        ]);

        $this->getJson(route('api.distritos.geojson'))
            ->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonCount(0, 'features');
    }

    public function test_infrastructure_endpoint_preserves_its_layer_contract(): void
    {
        DB::table('distritos')->insert([
            'id' => 1,
            'nome' => 'Centro',
            'latitude' => -16.0764,
            'longitude' => -57.6818,
            'cidade' => 'Cáceres',
        ]);

        DB::table('via_transitos')->insert([
            ['id' => 1, 'distrito_id' => 1, 'nome' => 'Via do Centro', 'geojson' => json_encode(['type' => 'LineString', 'coordinates' => [[-57.68, -16.07], [-57.67, -16.06]]])],
            ['id' => 2, 'distrito_id' => null, 'nome' => 'Outra Via', 'geojson' => json_encode(['type' => 'LineString', 'coordinates' => [[-57.7, -16.1], [-57.69, -16.09]]])],
        ]);

        $this->getJson(route('api.infraestrutura.geojson', ['distrito_id' => 1]))
            ->assertOk()
            ->assertJsonStructure([
                'cabeamentos',
                'vias',
                'postes',
                'antenas',
                'centrais',
                'equipamentos',
            ])
            ->assertJsonCount(1, 'vias')
            ->assertJsonPath('vias.0.nome', 'Via do Centro');
    }

    public function test_infrastructure_endpoint_can_skip_geojson_already_served_by_martin(): void
    {
        DB::table('via_transitos')->insert([
            'id' => 1,
            'nome' => 'Via vetorial',
            'geojson' => json_encode(['type' => 'LineString', 'coordinates' => [[-57.68, -16.07], [-57.67, -16.06]]]),
        ]);

        $this->getJson(route('api.infraestrutura.geojson', ['include_features' => 0]))
            ->assertOk()
            ->assertJsonCount(0, 'vias')
            ->assertJsonCount(0, 'centrais')
            ->assertJsonCount(0, 'cabeamentos')
            ->assertJsonPath('meta.features_included', false);
    }

    private function createGeoTables(): void
    {
        Schema::create('distritos', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('cidade')->nullable();
            $table->json('geojson')->nullable();
            $table->timestamps();
        });

        Schema::create('via_transitos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('distrito_id')->nullable();
            $table->json('geojson')->nullable();
            $table->string('nome')->nullable();
            $table->string('nivel_congestionamento')->default('livre');
            $table->integer('velocidade_media')->nullable();
            $table->timestamps();
        });

        Schema::create('centrais_distribuicao', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('distrito_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('geojson')->nullable();
            $table->string('nome')->nullable();
            $table->string('tipo')->nullable();
            $table->string('estado_conservacao')->nullable();
            $table->timestamps();
        });

        Schema::create('cabeamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('distrito_id')->nullable();
            $table->json('geojson')->nullable();
            $table->string('nome')->nullable();
            $table->string('tipo_cabo')->nullable();
            $table->string('estado_conservacao')->nullable();
            $table->unsignedBigInteger('poste_origem_id')->nullable();
            $table->unsignedBigInteger('poste_destino_id')->nullable();
            $table->unsignedBigInteger('central_origem_id')->nullable();
            $table->unsignedBigInteger('central_destino_id')->nullable();
            $table->timestamps();
        });

        Schema::create('postes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('distrito_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('codigo_patrimonio')->nullable();
            $table->string('material')->nullable();
            $table->string('estado_conservacao')->nullable();
            $table->decimal('altura_metros', 8, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('antenas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('distrito_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('codigo_patrimonio')->nullable();
            $table->string('tipo_sinal')->nullable();
            $table->string('estado_conservacao')->nullable();
            $table->timestamps();
        });

        Schema::create('equipamentos_infraestrutura', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('distrito_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('nome')->nullable();
            $table->string('tipo')->nullable();
            $table->string('estado_conservacao')->nullable();
            $table->unsignedBigInteger('poste_id')->nullable();
            $table->unsignedBigInteger('central_id')->nullable();
            $table->timestamps();
        });
    }
}
