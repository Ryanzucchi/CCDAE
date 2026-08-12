# Organização dos schemas do PostgreSQL

O banco está separado por domínio, mantendo no schema `public` somente as tabelas operacionais do Laravel e pacotes instalados.

| Schema | Responsabilidade | Principais tabelas |
| --- | --- | --- |
| `geo` | limites e referências geográficas | `distritos` |
| `climate` | estações, medições e eventos climáticos | `estacoes_meteorologicas`, séries `*_registrada`, `eventos_climaticos` |
| `infrastructure` | rede elétrica e construções | `postes`, `cabeamentos`, `antenas`, `centrais_distribuicao`, `equipamentos_infraestrutura`, `osm_buildings` |
| `mobility` | trânsito, vias e ocorrências | `via_transitos`, `registro_transitos`, `infracoes_transito` |
| `energy` | eventos operacionais de energia | `incidentes_energia` |
| `tiles` | views geoespaciais publicadas pelo Martin | views de distritos, vias e infraestrutura |
| `public` | aplicação e autenticação | usuários, cache, filas, sessões e migrations |

O Laravel usa `DB_SEARCH_PATH=public,geo,climate,infrastructure,mobility`. Assim, models e relacionamentos existentes continuam usando nomes de tabela sem schema, enquanto os dados ficam fisicamente isolados por domínio.

As views do schema `tiles` apontam explicitamente para as tabelas de domínio. A migration de organização também preserva hypertables do TimescaleDB e recria essas views após mover as tabelas.

Antes da aplicação inicial da migration foi criado um backup PostgreSQL no contêiner em `/tmp/ccdae-before-schemas.dump`.
