# Construções OpenStreetMap no mapa de infraestrutura

## Fluxo atual

`BuildingProvider` → `OverpassBuildingProvider` → `BuildingImportService` → `OsmBuilding`/PostGIS → `InfrastructureBuildingsController` → source GeoJSON `buildings` → layer MapLibre `3d-buildings`.

A Overpass API é usada somente durante o comando de sincronização. A navegação do mapa consulta exclusivamente o PostgreSQL/PostGIS local.

## Sincronização

```bash
php artisan osm:sync-buildings --list
php artisan osm:sync-buildings --district=15
php artisan osm:sync-buildings --bbox="-57.70,-16.09,-57.67,-16.06"
```

As áreas são limitadas pela configuração `osm.buildings.max_bbox_area_degrees`. A identidade única é `osm_type + osm_id`; sincronizações posteriores atualizam o mesmo registro.

## Alturas

`height` guarda somente altura real informada no OSM. `render_height` segue a prioridade:

1. `height`;
2. `building:levels × OSM_BUILDING_METERS_PER_LEVEL`;
3. `OSM_BUILDING_DEFAULT_HEIGHT`.

`height_source` permite distinguir `osm_height`, `levels` e `default`.

## Evolução para MVT

Quando GeoJSON por viewport deixar de ser suficiente, preservar o contrato de propriedades e substituir somente a entrega:

PostGIS → `ST_AsMVTGeom`/`ST_AsMVT` → endpoint de tiles → vector source MapLibre.

O provider e o importador não precisam mudar. Para grandes cargas, implementar outro `BuildingProvider` baseado em `.osm.pbf` com osmium/osm2pgsql.
