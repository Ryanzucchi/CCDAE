# Construções 3D com fontes compatíveis com uso comercial

## Fontes

O sistema combina:

1. OpenStreetMap via Overpass, priorizado por fornecer nomes, tipos, `height` e `building:levels` sob ODbL;
2. `GBA.ODbLPolygon`, usado somente para complementar footprints ausentes no OSM.

Não são usados `GBA.LoD1`, `GBA.Polygon` complementar nem `GBA.Height`, pois essas distribuições são CC BY-NC 4.0 e não são adequadas quando existe possibilidade de uso comercial.

O provider composto elimina footprints do atlas cujo centro esteja muito próximo de uma construção OSM. O resultado é armazenado no PostGIS e o mapa consulta apenas o banco local.

## Tile de Cáceres

Cáceres/MT está no tile:

```text
southamerica/w060_s15_w055_s20.geojson
```

Fonte oficial ODbL:

https://huggingface.co/datasets/zhu-xlab/GBA.ODbLPolygon

O arquivo deve ficar em:

```text
/var/www/html/storage/app/global-building-atlas/w060_s15_w055_s20.geojson
```

## Comandos

```bash
php artisan gba:check-buildings
php artisan gba:sync-buildings --list
php artisan gba:sync-buildings --district=15
```

O alias `osm:sync-buildings` permanece disponível para scripts antigos.

## Alturas

A altura segue esta prioridade:

1. `height` do OpenStreetMap;
2. `building:levels` do OpenStreetMap × metros configurados por nível;
3. altura visual padrão.

Nenhuma altura do GlobalBuildingAtlas CC BY-NC é importada.

## Obrigações

OpenStreetMap e `GBA.ODbLPolygon` exigem atribuição e cumprimento da ODbL, inclusive avaliação das obrigações aplicáveis à base derivada e ao compartilhamento de alterações. Recomenda-se revisão jurídica antes da exploração comercial.
