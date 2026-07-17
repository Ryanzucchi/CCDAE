# Documentação em camadas por caso de uso

Este documento organiza o comportamento do sistema por caso de uso, separando responsabilidades em camadas:

- **Camada de Apresentação** (Filament, Blade, Livewire, rotas),
- **Camada de Aplicação** (jobs, comandos, orquestração),
- **Camada de Domínio** (modelos e regras),
- **Camada de Dados/Infra** (PostgreSQL, Timescale, PostGIS, Docker e integrações externas).

---

## Caso de uso 1: Gestão de distritos geoespaciais

### Apresentação
- Recurso Filament: `Distritos/DistritoResource`.
- Página de gestão com mapa: `Distritos/Pages/ManageDistritos` + `resources/views/filament/admin/pages/mapa-distritos.blade.php`.
- Filtro por cidade, renderização de polígonos e fallback visual para pontos.

### Aplicação
- Ações de criação/edição com normalização de `geojson`.
- Cálculo de centróide após desenho de polígono para preencher latitude/longitude.
- Disparo de eventos Livewire para atualizar mapa em tempo real.

### Domínio
- Modelo `Distrito` com `geojson` castado para array e relacionamento com clima/regiões.
- Regra de ajuste automático de geometria (`autoShrinkGeojson`) para evitar sobreposição.

### Dados/Infra
- Tabela `distritos` com suporte a GeoJSON.
- PostGIS habilitado por migration.
- Endpoint `GET /api/distritos/geojson` expõe `FeatureCollection`.

---

## Caso de uso 2: Coleta e persistência de dados climáticos

### Apresentação
- Monitoramento operacional em `SystemHealth` (status de coleta, último registro e histórico de eventos).

### Aplicação
- Job `CollectClimateData`:
  - coleta em lotes,
  - chama Open-Meteo (clima + qualidade do ar),
  - aplica fallback de simulação quando a API falha,
  - persiste em múltiplas tabelas com `upsert`.

### Domínio
- Modelos de telemetria climática por grandeza (`TemperaturaRegistrada`, `ChuvaRegistrada`, etc.).
- Relações 1:N a partir de `Distrito`.

### Dados/Infra
- Tabelas time-series convertidas para hypertables.
- Índices e compressão configurados em migrations.
- Dependência externa: Open-Meteo.

---

## Caso de uso 3: Sanitização de dados climáticos redundantes

### Apresentação
- Execução via comando agendado, com resultado auditável no log.

### Aplicação
- Comando `climate:sanitize` invoca `ClimateSanitizerService`.
- Serviço identifica leituras idênticas em distritos vizinhos e consolida por janelas temporais.

### Domínio
- Modelo `RegiaoClimatica` e pivô `regiao_climatica_distrito` com `start_time`/`end_time`.
- Busca inteligente no modelo `Distrito::getClimaAt()` para manter consistência da consulta após sanitização.

### Dados/Infra
- Requer geometrias válidas para cálculo de adjacência (`ST_Touches`).
- Processo depende de consistência temporal entre tabelas de clima.

---

## Caso de uso 4: Mapeamento de trânsito urbano

### Apresentação
- Página `MapaTransito` + view `mapa-transito.blade.php`.
- Filtro por distrito e visualização de vias por nível de congestionamento.

### Aplicação
- Job `CollectTrafficData` gera snapshots de tráfego por via.
- Comando `transito:sync-real` importa malha viária via Overpass/OpenStreetMap.

### Domínio
- Modelo `ViaTransito` com atributos estruturais da via.
- Modelo `RegistroTransito` para histórico temporal do tráfego.

### Dados/Infra
- Tabela `registro_transitos` em hypertable Timescale.
- Dependência externa: Overpass API para ingestão real.

---

## Caso de uso 5: Mapeamento de infraestrutura urbana

### Apresentação
- Página `MapaInfraestrutura` + view `mapa-infraestrutura.blade.php`.
- Camadas simultâneas: vias, centrais, cabeamentos, postes, antenas e equipamentos.

### Aplicação
- Seeders específicos geram cenário inicial realista de infraestrutura com vínculos espaciais.
- Atualização dinâmica por filtro de distrito.

### Domínio
- Entidades: `Poste`, `Antena`, `CentralDistribuicao`, `EquipamentoInfraestrutura`, `Cabeamento`.
- Relacionamentos orientados a rede física e ativos.

### Dados/Infra
- Tabelas geoespaciais com coordenadas e GeoJSON.
- Estrutura preparada para evolução para análises de desgaste e risco.

---

## Caso de uso 6: Operação e saúde do sistema

### Apresentação
- Página `SystemHealth` com métricas operacionais e ações administrativas.

### Aplicação
- Ações de iniciar/parar workers, disparo manual de coleta e limpeza de fila.
- Consulta periódica de métricas de banco, sistema e logs.

### Domínio
- Eventos de coleta registrados no `activity_log`.
- Estado operacional baseado em heartbeat em cache e registros recentes.

### Dados/Infra
- Dependência de `jobs`, `failed_jobs`, `activity_log` e tabelas de telemetria.
- Logs de worker em `storage/logs`.

---

## Observações de manutenção documental

1. Ao alterar qualquer caso de uso, atualizar a seção correspondente neste arquivo.
2. Ao alterar comportamento técnico, atualizar também `ANALISE_PROJETO.md`.
3. Corrigir escrita e consistência textual sempre que tocar em uma seção.
