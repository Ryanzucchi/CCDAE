<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE EXTENSION IF NOT EXISTS postgis;

            ALTER TABLE distritos ADD COLUMN IF NOT EXISTS geom geometry(MultiPolygon, 4326);
            ALTER TABLE via_transitos ADD COLUMN IF NOT EXISTS geom geometry(MultiLineString, 4326);
            ALTER TABLE cabeamentos ADD COLUMN IF NOT EXISTS geom geometry(MultiLineString, 4326);
            ALTER TABLE centrais_distribuicao ADD COLUMN IF NOT EXISTS geom geometry(Geometry, 4326);
            ALTER TABLE postes ADD COLUMN IF NOT EXISTS geom geometry(Point, 4326);
            ALTER TABLE antenas ADD COLUMN IF NOT EXISTS geom geometry(Point, 4326);
            ALTER TABLE equipamentos_infraestrutura ADD COLUMN IF NOT EXISTS geom geometry(Point, 4326);

            CREATE OR REPLACE FUNCTION ccdae_safe_geom_from_geojson(payload jsonb)
            RETURNS geometry
            LANGUAGE plpgsql
            IMMUTABLE
            AS $$
            DECLARE
                normalized jsonb := payload;
                parsed geometry;
            BEGIN
                IF normalized IS NULL OR normalized = 'null'::jsonb THEN
                    RETURN NULL;
                END IF;

                IF normalized->>'type' = 'Feature' THEN
                    normalized := normalized->'geometry';
                ELSIF normalized->>'type' = 'FeatureCollection' THEN
                    normalized := normalized->'features'->0->'geometry';
                END IF;

                IF normalized IS NULL OR normalized = 'null'::jsonb THEN
                    RETURN NULL;
                END IF;

                parsed := ST_SetSRID(ST_GeomFromGeoJSON(normalized::text), 4326);
                IF NOT ST_IsValid(parsed) THEN
                    parsed := ST_MakeValid(parsed);
                END IF;

                RETURN parsed;
            EXCEPTION WHEN OTHERS THEN
                RETURN NULL;
            END;
            $$;

            CREATE OR REPLACE FUNCTION ccdae_sync_polygon_geom()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                parsed geometry;
            BEGIN
                parsed := ccdae_safe_geom_from_geojson(NEW.geojson::jsonb);
                parsed := ST_Multi(ST_CollectionExtract(parsed, 3));
                NEW.geom := CASE WHEN parsed IS NULL OR ST_IsEmpty(parsed) THEN NULL ELSE parsed END;
                RETURN NEW;
            END;
            $$;

            CREATE OR REPLACE FUNCTION ccdae_sync_line_geom()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                parsed geometry;
            BEGIN
                parsed := ccdae_safe_geom_from_geojson(NEW.geojson::jsonb);
                parsed := ST_Multi(ST_CollectionExtract(parsed, 2));
                NEW.geom := CASE WHEN parsed IS NULL OR ST_IsEmpty(parsed) THEN NULL ELSE parsed END;
                RETURN NEW;
            END;
            $$;

            CREATE OR REPLACE FUNCTION ccdae_sync_point_geom()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                NEW.geom := CASE
                    WHEN NEW.latitude IS NULL OR NEW.longitude IS NULL THEN NULL
                    ELSE ST_SetSRID(ST_MakePoint(NEW.longitude::double precision, NEW.latitude::double precision), 4326)
                END;
                RETURN NEW;
            END;
            $$;

            CREATE OR REPLACE FUNCTION ccdae_sync_central_geom()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                NEW.geom := ccdae_safe_geom_from_geojson(NEW.geojson::jsonb);
                IF NEW.geom IS NULL AND NEW.latitude IS NOT NULL AND NEW.longitude IS NOT NULL THEN
                    NEW.geom := ST_SetSRID(ST_MakePoint(NEW.longitude::double precision, NEW.latitude::double precision), 4326);
                END IF;
                RETURN NEW;
            END;
            $$;

            DROP TRIGGER IF EXISTS distritos_sync_geom ON distritos;
            CREATE TRIGGER distritos_sync_geom
                BEFORE INSERT OR UPDATE OF geojson ON distritos
                FOR EACH ROW EXECUTE FUNCTION ccdae_sync_polygon_geom();

            DROP TRIGGER IF EXISTS via_transitos_sync_geom ON via_transitos;
            CREATE TRIGGER via_transitos_sync_geom
                BEFORE INSERT OR UPDATE OF geojson ON via_transitos
                FOR EACH ROW EXECUTE FUNCTION ccdae_sync_line_geom();

            DROP TRIGGER IF EXISTS cabeamentos_sync_geom ON cabeamentos;
            CREATE TRIGGER cabeamentos_sync_geom
                BEFORE INSERT OR UPDATE OF geojson ON cabeamentos
                FOR EACH ROW EXECUTE FUNCTION ccdae_sync_line_geom();

            DROP TRIGGER IF EXISTS centrais_distribuicao_sync_geom ON centrais_distribuicao;
            CREATE TRIGGER centrais_distribuicao_sync_geom
                BEFORE INSERT OR UPDATE OF geojson, latitude, longitude ON centrais_distribuicao
                FOR EACH ROW EXECUTE FUNCTION ccdae_sync_central_geom();

            DROP TRIGGER IF EXISTS postes_sync_geom ON postes;
            CREATE TRIGGER postes_sync_geom
                BEFORE INSERT OR UPDATE OF latitude, longitude ON postes
                FOR EACH ROW EXECUTE FUNCTION ccdae_sync_point_geom();

            DROP TRIGGER IF EXISTS antenas_sync_geom ON antenas;
            CREATE TRIGGER antenas_sync_geom
                BEFORE INSERT OR UPDATE OF latitude, longitude ON antenas
                FOR EACH ROW EXECUTE FUNCTION ccdae_sync_point_geom();

            DROP TRIGGER IF EXISTS equipamentos_infraestrutura_sync_geom ON equipamentos_infraestrutura;
            CREATE TRIGGER equipamentos_infraestrutura_sync_geom
                BEFORE INSERT OR UPDATE OF latitude, longitude ON equipamentos_infraestrutura
                FOR EACH ROW EXECUTE FUNCTION ccdae_sync_point_geom();

            UPDATE distritos SET geojson = geojson;
            UPDATE via_transitos SET geojson = geojson;
            UPDATE cabeamentos SET geojson = geojson;
            UPDATE centrais_distribuicao SET geojson = geojson;
            UPDATE postes SET latitude = latitude, longitude = longitude;
            UPDATE antenas SET latitude = latitude, longitude = longitude;
            UPDATE equipamentos_infraestrutura SET latitude = latitude, longitude = longitude;

            CREATE INDEX IF NOT EXISTS distritos_geom_gist ON distritos USING GIST (geom);
            CREATE INDEX IF NOT EXISTS via_transitos_geom_gist ON via_transitos USING GIST (geom);
            CREATE INDEX IF NOT EXISTS cabeamentos_geom_gist ON cabeamentos USING GIST (geom);
            CREATE INDEX IF NOT EXISTS centrais_distribuicao_geom_gist ON centrais_distribuicao USING GIST (geom);
            CREATE INDEX IF NOT EXISTS postes_geom_gist ON postes USING GIST (geom);
            CREATE INDEX IF NOT EXISTS antenas_geom_gist ON antenas USING GIST (geom);
            CREATE INDEX IF NOT EXISTS equipamentos_infraestrutura_geom_gist ON equipamentos_infraestrutura USING GIST (geom);
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS distritos_sync_geom ON distritos;
            DROP TRIGGER IF EXISTS via_transitos_sync_geom ON via_transitos;
            DROP TRIGGER IF EXISTS cabeamentos_sync_geom ON cabeamentos;
            DROP TRIGGER IF EXISTS centrais_distribuicao_sync_geom ON centrais_distribuicao;
            DROP TRIGGER IF EXISTS postes_sync_geom ON postes;
            DROP TRIGGER IF EXISTS antenas_sync_geom ON antenas;
            DROP TRIGGER IF EXISTS equipamentos_infraestrutura_sync_geom ON equipamentos_infraestrutura;

            DROP INDEX IF EXISTS distritos_geom_gist;
            DROP INDEX IF EXISTS via_transitos_geom_gist;
            DROP INDEX IF EXISTS cabeamentos_geom_gist;
            DROP INDEX IF EXISTS centrais_distribuicao_geom_gist;
            DROP INDEX IF EXISTS postes_geom_gist;
            DROP INDEX IF EXISTS antenas_geom_gist;
            DROP INDEX IF EXISTS equipamentos_infraestrutura_geom_gist;

            ALTER TABLE distritos DROP COLUMN IF EXISTS geom;
            ALTER TABLE via_transitos DROP COLUMN IF EXISTS geom;
            ALTER TABLE cabeamentos DROP COLUMN IF EXISTS geom;
            ALTER TABLE centrais_distribuicao DROP COLUMN IF EXISTS geom;
            ALTER TABLE postes DROP COLUMN IF EXISTS geom;
            ALTER TABLE antenas DROP COLUMN IF EXISTS geom;
            ALTER TABLE equipamentos_infraestrutura DROP COLUMN IF EXISTS geom;

            DROP FUNCTION IF EXISTS ccdae_sync_polygon_geom();
            DROP FUNCTION IF EXISTS ccdae_sync_line_geom();
            DROP FUNCTION IF EXISTS ccdae_sync_point_geom();
            DROP FUNCTION IF EXISTS ccdae_sync_central_geom();
            DROP FUNCTION IF EXISTS ccdae_safe_geom_from_geojson(jsonb);
            SQL);
    }
};
