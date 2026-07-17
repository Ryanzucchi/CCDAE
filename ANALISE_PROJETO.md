# Análise técnica consolidada do CCDAE

Esta análise descreve o estado atual do projeto a partir do código-fonte, estrutura de banco, painel administrativo, jobs, comandos e testes.

## 1) Visão geral do sistema

O **CCDAE** é um sistema Laravel 12 para monitoramento urbano, combinando:

- **telemetria climática** (séries temporais),
- **gestão geoespacial de distritos** (GeoJSON + PostGIS),
- **mapeamento de infraestrutura urbana**,
- **monitoramento de trânsito**,
- **operação via painel Filament**.

Stack principal observada:

- Backend: **PHP 8.2+ / Laravel 12**
- Admin: **Filament 5** + Livewire + Alpine
- Banco: **PostgreSQL + TimescaleDB + PostGIS**
- Auditoria: **Spatie Activity Log**
- Frontend build: **Vite + Tailwind**
- Infra local: **Docker (PHP-FPM, Nginx, TimescaleDB)**

## 2) Estrutura funcional (módulos)

### 2.1 Clima e telemetria

- Modelos time-series: `temperatura_registrada`, `vento_registrado`, `chuva_registrada`, `pressao_atmosferica`, `radiacao_solar`, `indice_uv`, `particulas_ar`.
- Jobs:
  - `CollectClimateData`: coleta em lote por distrito, integra com Open-Meteo e faz `upsert` nas tabelas.
  - fallback de simulação quando API externa está indisponível/rate-limited.
- Comando:
  - `climate:sanitize`: consolida leituras redundantes em `regioes_climaticas` com janelas temporais.

### 2.2 Distritos e geoespacial

- Modelo central: `Distrito` com `geojson`, `latitude`, `longitude`, `cidade`.
- Recurso Filament completo para cadastro/edição de distritos com MapPicker.
- Geração de fronteiras:
  - polígonos reais via desenho manual,
  - fallback visual com **Voronoi** no frontend quando não há GeoJSON.
- Endpoint:
  - `GET /api/distritos/geojson` para `FeatureCollection` consumível por mapa.

### 2.3 Trânsito e mobilidade

- Modelo de malha viária: `ViaTransito` (com GeoJSON de linhas e características da via).
- Série temporal de trânsito: `RegistroTransito`.
- Jobs/comandos:
  - `CollectTrafficData`: snapshot sintético periódico por via.
  - `transito:sync-real`: ingestão de vias reais via Overpass/OpenStreetMap.
- Página dedicada no painel: mapa de trânsito com filtro por distrito.

### 2.4 Infraestrutura urbana

- Entidades: `Poste`, `Antena`, `CentralDistribuicao`, `EquipamentoInfraestrutura`, `Cabeamento`.
- Seeder de infraestrutura com regras de domínio para distribuição de ativos ao longo das vias.
- Página no painel com camada combinada de infraestrutura + trânsito.

### 2.5 Observabilidade operacional

- Página `SystemHealth` no Filament com:
  - estado de workers,
  - métricas de memória/disco/load/latência de banco,
  - backlog de filas e failed jobs,
  - últimos registros de clima e trânsito,
  - tabela de eventos no Activity Log.
- Widget de gráfico de volume de coletas nos últimos 7 dias.

## 3) Arquitetura de dados e banco

Estado do banco identificado pelas migrations:

- extensão PostGIS habilitada (`enable_postgis_extension`),
- tabelas de clima convertidas para hypertable Timescale,
- `registro_transitos` também convertido para hypertable,
- índices adicionais em tabelas de telemetria,
- compressão configurada para séries temporais.

Domínio principal:

- núcleo territorial: `distritos`, `regioes_climaticas`, `regiao_climatica_distrito`,
- clima: 7 tabelas de medições + `eventos_climaticos`,
- infraestrutura: postes/antenas/centrais/equipamentos/cabeamentos,
- mobilidade: vias e registros de trânsito.

## 4) Interface administrativa (Filament)

Recursos CRUD e páginas customizadas estão ativos para:

- dados climáticos,
- distritos,
- infraestrutura urbana,
- trânsito e registros,
- logs de atividade.

Páginas custom:

- `Mapa de Distritos`,
- `Mapa da Infraestrutura`,
- `Mapa de Trânsito`,
- `Saúde do Sistema`.

## 5) Rotas, agendamento e execução

Rotas web relevantes:

- `/api/distritos/geojson`,
- `/admin` (painel),
- rotas padrão de autenticação Breeze.

Agendamento:

- existe configuração em **dois pontos**:
  - `bootstrap/app.php` (coletas em horários fixos),
  - `routes/console.php` (coletas por cron variável + sanitização diária).

Isso exige padronização para evitar duplicidade de execução.

## 6) Qualidade e testes

Suite atual de testes cobre majoritariamente:

- autenticação e recuperação de senha,
- perfil de usuário,
- teste básico de resposta HTTP.

Não há cobertura dedicada para:

- jobs de coleta,
- comandos de ingestão/sanitização,
- regras geoespaciais (GeoJSON/PostGIS),
- páginas de mapas e fluxos de domínio urbano.

## 7) Riscos técnicos e pontos de atenção

1. **Agendamento duplicado** (duas fontes de schedule).
2. **Dependência de API externa** (Open-Meteo/Overpass) com necessidade de robustez operacional.
3. **Acoplamento de lógica operacional no painel** (start/stop de workers via shell dentro da página).
4. **Cobertura de testes funcional insuficiente** para partes críticas de negócio.
5. **Divergência de ambiente**: `.env.example` padrão SQLite versus stack Docker PostgreSQL/Timescale.

## 8) Conclusão

O projeto já possui base arquitetural robusta para dados urbanos e telemetria em escala, com painéis visuais operacionais e domínio rico. A próxima evolução recomendada é consolidar governança técnica (schedule, testes, ambiente e observabilidade), reduzindo riscos e elevando previsibilidade de operação.
