# Plano de implementação — MapLibre GL JS, PostGIS e MVT

## 1. Objetivo

Modernizar a plataforma cartográfica 2D do CCDAE, substituindo gradualmente o Leaflet por MapLibre GL JS e preparando os dados geoespaciais para distribuição em tiles vetoriais MVT.

A migração deve ser incremental, preservar os fluxos atuais de cadastro e edição e permitir rollback durante todo o período de transição.

## 2. Situação atual

| Componente | Situação atual |
| --- | --- |
| Motor 2D | Leaflet |
| Dados cartográficos | OpenStreetMap e mapas-base raster da CARTO |
| Tiles | Raster, incluindo uso direto de `tile.openstreetmap.org` em alguns pontos |
| Dados CCDAE | GeoJSON armazenado em colunas JSON/JSONB |
| Banco | PostgreSQL com extensão PostGIS habilitada |
| Consultas espaciais | Conversão de GeoJSON com `ST_GeomFromGeoJSON` durante a consulta |
| Edição cartográfica | Dotswan Filament Map Picker, baseado no ecossistema Leaflet/GeoMan |
| Visualização 3D | Three.js com OrbitControls no mapa de infraestrutura |

Embora o PostGIS esteja habilitado, as geometrias de negócio ainda não são mantidas principalmente em colunas espaciais nativas e indexadas. Essa adequação deve acontecer antes da adoção de MVT.

Também não há implementação de Cesium no código atual. A visualização 3D existente usa Three.js. Uma eventual migração para Cesium deve ser tratada como projeto separado.

## 3. Arquitetura-alvo

| Camada | Decisão recomendada |
| --- | --- |
| Visualização 2D | MapLibre GL JS |
| Dados cartográficos | OpenStreetMap |
| Mapa-base | Tiles vetoriais próprios ou provedor compatível com MapLibre |
| Dados CCDAE durante a transição | Endpoints GeoJSON atuais, adaptados |
| Dados CCDAE em grande volume | MVT gerado pelo PostGIS |
| Persistência geoespacial | Colunas `geometry(..., 4326)` com índices GiST |
| Cadastro e edição | Manter temporariamente o Dotswan Map Picker/Leaflet |
| 3D | Preservar Three.js inicialmente; avaliar Cesium separadamente |
| Empacotamento frontend | Módulos JavaScript via Vite |

Fluxo planejado:

```text
PostGIS
   ├── API GeoJSON ──> MapLibre durante a transição
   └── ST_AsMVT ─────> /api/tiles/{camada}/{z}/{x}/{y}.pbf
                                  │
                                  └── MapLibre GL JS
```

## 4. Princípios da migração

1. Não substituir visualização, edição, persistência e distribuição de tiles ao mesmo tempo.
2. Manter GeoJSON como formato de compatibilidade durante pelo menos um ciclo completo de implantação.
3. Migrar primeiro o mapa funcionalmente mais simples e usar o resultado como padrão para os demais.
4. Introduzir MVT apenas nas camadas cujo volume ou desempenho justifique a mudança.
5. Preservar Leaflet nos formulários enquanto o Map Picker depender dele.
6. Controlar a escolha do motor cartográfico e do provedor de tiles por configuração.
7. Não armazenar tokens de provedores diretamente em arquivos Blade ou JavaScript versionados.

## 5. Fases de implementação

### Fase 0 — Preparação, testes e métricas

**Estimativa:** 2 a 3 dias.

#### Atividades

- Inventariar filtros, camadas, estilos, popups, legendas e ações dos mapas existentes.
- Registrar capturas dos mapas atuais para comparação visual.
- Medir tamanho, tempo de resposta e quantidade de entidades dos endpoints atuais.
- Criar testes automatizados para:
  - `GET /api/distritos/geojson`;
  - `GET /api/infraestrutura/geojson`;
  - filtros por distrito;
  - autenticação e autorização;
  - estrutura das `FeatureCollection`;
  - validade e SRID das geometrias.
- Definir uma configuração de ativação gradual, por exemplo:

```env
MAP_ENGINE=leaflet
MAP_BASE_STYLE_URL=
MAP_TILE_TOKEN=
```

#### Critérios de aceite

- Métricas de referência registradas.
- Comportamento funcional dos mapas documentado.
- Endpoints geoespaciais principais cobertos por testes.
- Possibilidade de selecionar o motor cartográfico por configuração.

### Fase 1 — Fundação MapLibre

**Estimativa:** 3 a 5 dias.

#### Atividades

- Instalar `maplibre-gl` pelo npm.
- Importar JavaScript e CSS do MapLibre pelo Vite.
- Criar módulos reutilizáveis, por exemplo:

```text
resources/js/maps/
├── config.js
└── maplibre/
    ├── create-map.js
    ├── layers.js
    ├── popups.js
    └── sources.js
```

- Centralizar:
  - centro e zoom padrão de Cáceres;
  - URL do estilo cartográfico;
  - atribuições obrigatórias;
  - cores e estilos por estado;
  - controles comuns;
  - tratamento de falhas;
  - integração de ciclo de vida com Alpine e Livewire.
- Definir uma interface comum de inicialização e destruição do mapa.
- Evitar múltiplas instâncias quando o usuário navegar por páginas Livewire.

#### Critérios de aceite

- MapLibre empacotado pelo Vite, sem depender de CDN para seu código principal.
- Componente mínimo renderizado dentro do Filament.
- Instância corretamente destruída e recriada durante a navegação.
- Provedor ou estilo do mapa-base configurável por ambiente.

### Fase 2 — Projeto-piloto: mapa de distritos

**Estimativa:** 4 a 6 dias.

O mapa de distritos será o primeiro a ser migrado por concentrar polígonos, pontos, popups, enquadramento e fallback de geometria sem possuir o volume do mapa de infraestrutura.

#### Conversões principais

| Leaflet | MapLibre |
| --- | --- |
| `L.map` | `new maplibregl.Map` |
| `L.geoJSON` | `addSource({ type: 'geojson' })` |
| Polígono com estilo | Camadas `fill` e `line` |
| `L.circleMarker` | Camada `circle` |
| Ícone ou marcador | Camada `symbol` ou `Marker` quando necessário |
| Popup do Leaflet | `maplibregl.Popup` |
| `fitBounds` | `map.fitBounds` |
| Evento por objeto | Evento delegado por camada |
| Destaque manual | `feature-state` |

#### Atividades

- Manter o endpoint GeoJSON atual como fonte inicial.
- Migrar polígonos cadastrados para fontes e camadas MapLibre.
- Migrar pontos de distritos sem polígono.
- Preservar o fallback Voronoi com Turf no primeiro momento.
- Reproduzir popups e links administrativos.
- Implementar hover, seleção, cursor e enquadramento.
- Validar responsividade e compatibilidade com tema claro/escuro.
- Disponibilizar Leaflet e MapLibre por flag durante a homologação.

#### Critérios de aceite

- Informações e ações equivalentes às do mapa atual.
- Geometrias e pontos renderizados corretamente.
- Enquadramento correto dos dados.
- Navegação Livewire sem mapa duplicado ou container corrompido.
- Atribuição cartográfica visível.
- Desempenho igual ou superior ao Leaflet.

### Fase 3 — Migração dos demais mapas 2D

**Estimativa:** 2 a 3 semanas.

#### Ordem recomendada

1. Widget de distritos e injetor de mapas em formulários.
2. Mapa de trânsito.
3. Mapa de infraestrutura.
4. Mapa climático.

#### 3.1 Widget e injetor de distritos

- Reutilizar o módulo criado no projeto-piloto.
- Evitar que cada componente carregue sua própria cópia da biblioteca.
- Confirmar convivência com o Map Picker usado nos formulários.

#### 3.2 Mapa de trânsito

- Converter vias em camadas `line`.
- Usar expressões MapLibre para cor e espessura conforme congestionamento.
- Manter distritos como camadas de contexto.
- Preservar legenda, filtros e popups.

#### 3.3 Mapa de infraestrutura

- Criar uma fonte ou tileset lógico por família de entidade.
- Converter vias e cabeamentos para camadas `line`.
- Converter centrais, postes, antenas e equipamentos para camadas `circle` ou `symbol`.
- Substituir milhares de marcadores DOM por camadas WebGL.
- Aplicar visibilidade por nível de zoom.
- Manter carregamento detalhado por distrito durante a fase GeoJSON.
- Preservar links de edição e relacionamentos exibidos nos popups.

Este mapa deverá apresentar o maior ganho imediato, pois hoje pode carregar dezenas de milhares de registros por categoria.

#### 3.4 Mapa climático

- Migrar polígonos temáticos para camadas `fill` e `line`.
- Implementar estilos orientados por propriedades e expressões MapLibre.
- Reproduzir setas, blobs, legenda e linha temporal.
- Avaliar se as setas serão uma camada `symbol` com rotação ou uma camada customizada.
- Manter a lógica temporal separada da implementação do motor cartográfico.

#### Critérios de aceite da fase

- Paridade funcional documentada por mapa.
- Nenhum mapa de visualização dependente de APIs globais do Leaflet.
- Camadas volumosas renderizadas via WebGL.
- Filtros, legendas, popups e links administrativos preservados.
- Testes manuais executados em desktop e viewport móvel.

### Fase 4 — PostGIS como fonte geoespacial nativa

**Estimativa:** 1 a 2 semanas.

#### Estrutura proposta

Adicionar colunas espaciais sem remover imediatamente os campos GeoJSON:

```sql
geom geometry(Geometry, 4326)
```

Usar tipos específicos sempre que o domínio permitir:

| Entidade | Tipo sugerido |
| --- | --- |
| Distritos | `MultiPolygon` |
| Vias | `MultiLineString` |
| Cabeamentos | `MultiLineString` |
| Postes | `Point` |
| Antenas | `Point` |
| Equipamentos | `Point` |
| Centrais | `Point` ou geometria específica, conforme regra atual |

#### Atividades

- Criar migrations adicionando as colunas `geom`.
- Converter os GeoJSON existentes para geometrias PostGIS.
- Aplicar `ST_MakeValid` onde necessário.
- Normalizar tipos simples e múltiplos.
- Garantir SRID 4326.
- Criar índices GiST.
- Migrar consultas espaciais para as novas colunas.
- Implementar sincronização temporária entre `geojson` e `geom`.
- Criar auditoria para identificar registros divergentes.
- Definir a coluna espacial como fonte de verdade após a homologação.

Exemplo de índice:

```sql
CREATE INDEX distritos_geom_gist
ON distritos
USING GIST (geom);
```

#### Estratégia de compatibilidade

1. Criar a coluna espacial como opcional.
2. Executar backfill e produzir relatório de falhas.
3. Implementar escrita dupla temporária.
4. Comparar GeoJSON e geometria espacial.
5. Migrar leituras e regras para `geom`.
6. Tornar `geom` obrigatória onde aplicável.
7. Remover a escrita dupla somente após estabilização.

O campo GeoJSON deve permanecer durante pelo menos um ciclo de implantação para rollback e compatibilidade com o Map Picker.

#### Critérios de aceite

- Todas as geometrias válidas e no SRID correto.
- Índices GiST utilizados pelas consultas espaciais relevantes.
- Nenhuma perda de geometria ou propriedade durante o backfill.
- Regras de sobreposição e adjacência usando colunas espaciais.
- Relatório de divergência vazio ou com exceções formalmente aceitas.

### Fase 5 — MVT para dados volumosos

**Estimativa:** 1 a 2 semanas.

MVT deve ser introduzido primeiro nas camadas com maior volume:

- vias;
- cabeamentos;
- postes;
- equipamentos;
- distritos, se o volume ou a complexidade crescerem.

#### API proposta

```http
GET /api/tiles/{layer}/{z}/{x}/{y}.pbf
```

#### Processamento PostGIS

As consultas deverão usar:

- `ST_TileEnvelope` para obter o envelope do tile;
- operador espacial indexado para filtrar as entidades;
- `ST_AsMVTGeom` para recortar e transformar a geometria;
- `ST_AsMVT` para gerar o tile vetorial.

#### Requisitos de segurança e desempenho

- Validar o nome da camada por allowlist.
- Nunca interpolar nomes arbitrários de tabela ou coluna enviados pelo cliente.
- Respeitar autenticação, autorização e escopo por distrito.
- Incluir apenas propriedades necessárias para renderização e interação.
- Definir limites e simplificação por nível de zoom.
- Cachear respostas por camada, zoom e coordenada.
- Usar `ETag` ou cabeçalhos de cache apropriados.
- Invalidar ou versionar tiles após alterações geoespaciais.
- Monitorar tempo de geração e taxa de acerto do cache.

#### Estratégia de detalhe por zoom

| Zoom | Conteúdo sugerido |
| --- | --- |
| 0–9 | Distritos e vias principais |
| 10–13 | Vias e centrais |
| 14–16 | Cabeamentos e infraestrutura agregada |
| 17 ou mais | Postes, antenas e equipamentos detalhados |

GeoJSON continuará adequado para:

- edição de uma entidade;
- retorno de uma seleção pequena;
- detalhes sob demanda;
- exportação e integrações;
- funcionalidades administrativas que dependam do formato.

#### Critérios de aceite

- Tiles válidos e consumidos pelo MapLibre.
- Consultas usando o índice espacial.
- Propriedades sensíveis ou desnecessárias ausentes do tile.
- Cache e invalidação testados.
- Melhor desempenho que o endpoint GeoJSON equivalente nos cenários volumosos.

### Fase 6 — Edição cartográfica

**Estimativa:** 1 a 2 semanas, opcional para a primeira entrega.

O Dotswan Map Picker usado pelos formulários depende do ecossistema Leaflet. Existem duas alternativas:

1. Manter Leaflet apenas nos formulários de edição.
2. Substituir posteriormente o editor por MapLibre com Mapbox GL Draw, Terra Draw ou solução equivalente.

#### Recomendação

Manter o Map Picker durante a migração dos mapas de visualização. A substituição do editor deve ocorrer apenas depois que os mapas MapLibre e as colunas PostGIS estiverem estabilizados.

Uma futura troca deverá reproduzir:

- criação e edição de pontos, linhas e polígonos;
- hidratação e desidratação dos campos Filament;
- cálculo de centroide;
- validação de sobreposição;
- normalização para SRID 4326;
- comportamento durante re-renderizações Livewire;
- acessibilidade e uso em dispositivos móveis.

### Fase 7 — Remoção do legado

**Estimativa:** 3 a 5 dias.

#### Atividades

- Remover CSS `.leaflet-*` dos mapas migrados.
- Remover scripts Leaflet carregados por CDN onde não forem mais usados.
- Eliminar inicializações e estilos duplicados.
- Manter Leaflet apenas nos fluxos de edição que ainda dependam dele.
- Atualizar README e documentação arquitetural.
- Documentar convenções de fontes, camadas, IDs e propriedades.
- Remover a flag do motor antigo somente após o período de estabilização.

#### Critérios de aceite

- Nenhuma referência Leaflet residual nos mapas de visualização migrados.
- Dependências restantes justificadas pelos formulários de edição.
- Build de produção e testes automatizados aprovados.
- Documentação coerente com a arquitetura implantada.

## 6. Estratégia para o mapa-base e tiles OSM

Os dados do OpenStreetMap devem continuar sendo utilizados, mas o servidor público `tile.openstreetmap.org` não deve ser tratado como infraestrutura principal de produção.

Existem duas estratégias possíveis:

### Opção A — Provedor compatível com MapLibre

Vantagens:

- implantação mais rápida;
- estilo vetorial já disponível;
- menor esforço operacional;
- CDN e atualização dos dados administradas pelo provedor.

Desvantagens:

- custo recorrente;
- dependência externa;
- limites de uso;
- necessidade de gerenciar tokens.

### Opção B — Tiles vetoriais próprios

Vantagens:

- maior controle sobre dados, estilo e disponibilidade;
- independência de limites comerciais;
- possibilidade de operação em rede restrita.

Desvantagens:

- geração e atualização dos tiles;
- armazenamento e distribuição;
- necessidade de cache ou CDN;
- monitoramento e manutenção operacional;
- maior investimento inicial.

### Recomendação

Iniciar com um provedor configurável para acelerar a migração do frontend. Tratar a hospedagem de tiles próprios como projeto de infraestrutura separado, depois que o MapLibre estiver estabilizado.

## 7. Estratégia para 3D

A troca do motor 2D não exige alteração imediata do mapa 3D atual.

### Curto prazo

- Preservar Three.js e OrbitControls.
- Separar melhor o código 2D e 3D do mapa de infraestrutura.
- Evitar duplicação de carregamento e transformação dos dados.

### Avaliação futura de Cesium

Cesium será apropriado caso o projeto necessite de:

- globo 3D;
- terreno e elevação;
- 3D Tiles;
- grandes volumes de edificações ou modelos georreferenciados;
- visualização temporal geoespacial avançada;
- integração com imagens ou serviços próprios do ecossistema Cesium.

Caso o 3D continue sendo uma representação local ou esquemática da infraestrutura, Three.js pode continuar sendo suficiente e mais simples.

## 8. Testes e garantia de qualidade

### Testes de backend

- Resposta e contrato dos endpoints GeoJSON.
- Validade das geometrias.
- SRID correto.
- Conversão e backfill sem perda de dados.
- Regras de sobreposição e adjacência.
- Filtros espaciais e por distrito.
- Autorização dos endpoints de tiles.
- Validação de parâmetros `z`, `x`, `y` e `layer`.
- Cache e invalidação de MVT.

### Testes de frontend

- Inicialização e destruição com Livewire.
- Renderização de cada tipo de camada.
- Hover, clique, seleção e popup.
- Filtros e alternância de visibilidade.
- Legendas e controles.
- Enquadramento e navegação.
- Tema claro e escuro.
- Desktop e viewport móvel.
- Falha do mapa-base ou da API sem travamento da página.

### Testes de desempenho

- Tempo de resposta dos endpoints.
- Tamanho transferido por viewport.
- Tempo até primeira renderização útil.
- Fluidez de pan e zoom.
- Uso de memória depois de navegar repetidamente entre páginas.
- Comparação GeoJSON versus MVT em camadas volumosas.

## 9. Observabilidade

- Registrar tempo de geração de cada tile.
- Registrar falhas de conversão ou geometria inválida.
- Medir taxa de acerto do cache.
- Monitorar tamanho médio das respostas GeoJSON e MVT.
- Alertar para endpoints com consultas espaciais lentas.
- Usar `EXPLAIN ANALYZE` durante a homologação das consultas críticas.

## 10. Riscos e mitigações

| Risco | Mitigação |
| --- | --- |
| Quebra da integração Alpine/Livewire | Componente comum de ciclo de vida e testes de navegação |
| Paridade visual incompleta | Capturas de referência e checklist por mapa |
| Geometrias inválidas no backfill | Auditoria, `ST_IsValid`, `ST_MakeValid` e relatório de exceções |
| Divergência entre `geojson` e `geom` | Escrita dupla temporária e rotina de comparação |
| Sobrecarga do banco ao gerar MVT | Índice GiST, cache, simplificação e limite de propriedades |
| Vazamento de dados em tiles | Autorização, allowlist e seleção explícita de atributos |
| Dependência de provedor externo | Configuração por ambiente e plano posterior de tiles próprios |
| Escopo excessivo ao trocar o editor | Manter Map Picker/Leaflet durante a primeira migração |
| Regressão no mapa climático | Migrá-lo por último e isolar lógica temporal do motor do mapa |
| Introdução prematura de Cesium | Avaliação separada baseada em requisitos 3D concretos |

## 11. Cronograma sugerido

| Marco | Entrega | Estimativa |
| --- | --- | ---: |
| M1 | Base de testes, métricas e MapLibre configurado | 1 semana |
| M2 | Mapa de distritos migrado e homologado | 1 semana |
| M3 | Trânsito, infraestrutura e clima migrados | 2 a 3 semanas |
| M4 | Colunas PostGIS nativas, backfill e índices | 1 a 2 semanas |
| M5 | Primeiro endpoint MVT, cache e integração | 1 a 2 semanas |
| M6 | Limpeza, documentação e estabilização | 1 semana |

Estimativa total: **7 a 10 semanas para uma pessoa**, variando conforme a quantidade de ajustes exigidos pelos mapas climático e de infraestrutura e conforme o escopo da primeira entrega de MVT.

## 12. Entregas sugeridas por versão

### Versão 1 — Fundação

- Testes e métricas.
- MapLibre empacotado pelo Vite.
- Configuração de provedor.
- Componente base integrado ao Filament/Livewire.

### Versão 2 — Piloto

- Mapa de distritos em MapLibre.
- Widget e injetor adaptados.
- Flag de rollback para Leaflet.

### Versão 3 — Mapas operacionais

- Trânsito em MapLibre.
- Infraestrutura em MapLibre.
- Clima em MapLibre.

### Versão 4 — Persistência espacial

- Colunas PostGIS nativas.
- Backfill auditado.
- Índices espaciais.
- Consultas e regras migradas.

### Versão 5 — Escala

- Endpoint MVT.
- Cache e invalidação.
- Primeiras camadas volumosas servidas como tiles vetoriais.

### Versão 6 — Consolidação

- Remoção do Leaflet dos mapas de visualização.
- Documentação atualizada.
- Decisão sobre editor MapLibre e sobre eventual adoção de Cesium.

## 13. Ordem executiva recomendada

1. Implantar MapLibre consumindo os GeoJSON atuais.
2. Validar o padrão no mapa de distritos.
3. Migrar os demais mapas de visualização.
4. Criar e popular colunas PostGIS nativas.
5. Migrar regras e consultas espaciais para as novas colunas.
6. Introduzir MVT nas camadas volumosas.
7. Manter Leaflet apenas no editor enquanto necessário.
8. Avaliar tiles próprios, novo editor e Cesium como decisões posteriores.

Essa sequência mantém a aplicação utilizável durante toda a evolução, reduz o risco de regressão e permite medir os ganhos de cada mudança antes de avançar para a etapa seguinte.

## 14. Estado da implementação

Atualizado em 10 de agosto de 2026.

### Concluído

- MapLibre GL JS e Turf instalados como dependências npm.
- Entrada dedicada `resources/js/maps/districts-map.js` configurada no Vite.
- Configuração cartográfica central criada em `config/maps.php`.
- Variáveis de ambiente cartográficas documentadas em `.env.example`.
- Mapa de distritos migrado de Leaflet para MapLibre.
- Widget geral de distritos migrado de Leaflet para MapLibre.
- Mapa de trânsito migrado de Leaflet para MapLibre.
- Visualização 2D do mapa de infraestrutura migrada de Leaflet para MapLibre.
- Mapa climático migrado de Leaflet para MapLibre, preservando a timeline horária e a união entre geometria estática e leituras temporais.
- Fundação compartilhada de criação, configuração, enquadramento e ciclo de vida MapLibre extraída para reutilização.
- Fonte GeoJSON, camadas de preenchimento, contorno e pontos implementadas.
- Hover com `feature-state`, popup seguro e ação de edição preservados.
- Vias de trânsito renderizadas por camadas WebGL, com cores por congestionamento, hover, popup e legenda preservados.
- Vias, centrais, cabeamentos, postes, antenas e equipamentos de infraestrutura renderizados em camadas WebGL.
- Detalhes volumosos da infraestrutura condicionados ao zoom 15, mantendo o aviso de aproximação.
- Linhas paralelas de cabeamento preservadas por deslocamento de linha no MapLibre.
- Visualização 3D Three.js preservada e desacoplada do novo renderizador 2D por eventos.
- Métricas climáticas renderizadas por heatmap WebGL, contornos de distrito e campo direcional de vento.
- Legendas climáticas atualizadas dinamicamente pelo módulo MapLibre.
- Colunas PostGIS nativas adicionadas a distritos, vias, cabeamentos, centrais, postes, antenas e equipamentos.
- Backfill espacial e sincronização automática por triggers implementados.
- Sete índices GiST criados e validados.
- Martin 1.13.0 adicionado ao Docker Compose como servidor MVT conectado diretamente ao PostGIS.
- Schema `tiles` criado com sete views de publicação controlada, sem expor as tabelas operacionais completas.
- Catálogo e TileJSON do Martin publicados sob `/tiles` pelo Nginx.
- Cache de tiles configurado no Martin e no Nginx, com proteção contra requisições concorrentes.
- Cabeamentos, postes, antenas e equipamentos da visualização 2D migrados para MVT a partir do zoom 15.
- Vias do mapa de trânsito migradas para MVT, preservando cores por congestionamento, hover, popup e filtro por distrito.
- Vias e centrais do mapa de infraestrutura migradas para MVT, sem desenho GeoJSON duplicado no modo 2D.
- Filtro por distrito aplicado às fontes MVT de vias, centrais, cabeamentos, postes, antenas e equipamentos.
- Widget geral de distritos migrado para MVT, mantendo GeoJSON para enquadramento e como fallback configurável.
- Resposta GeoJSON macro da infraestrutura reduzida para um contrato mínimo de 139 bytes; vias e centrais deixam de ser transferidas em duplicidade quando já são atendidas pelo Martin.
- Parâmetro `include_features` adicionado ao endpoint de infraestrutura, preservando a resposta completa para distritos selecionados e para o modo 3D.
- Detalhes GeoJSON do distrito carregados sob demanda somente ao abrir o modo 3D.
- Enquadramento do distrito no modo 2D passou a usar um `bounds` compacto calculado pelo PostGIS, sem transferir as feições completas.
- Painel de saúde passou a monitorar catálogo Martin, latência e quantidade de fontes MVT, com alerta operacional e cache de 15 segundos.
- Expiração dos caches Martin e Nginx reduzida para um minuto, alinhada aos dados dinâmicos de trânsito e evitando mapas desatualizados por até uma hora.
- Chave do cache TileJSON isolada por `Host`, evitando que acessos internos do Docker publiquem URLs `nginx-ccdae` para o navegador.
- Camadas MVT sem distrito selecionado passaram a omitir `filter` na criação, evitando rejeição do Style Spec por `filter: null` e interrupção do carregamento WebGL.
- Mapa-base raster padronizado na URL oficial do OpenStreetMap, com atribuição visível e configuração central por ambiente.
- `MAP_RASTER_TILE_URL` permite trocar para provedor OSM, CDN ou tiles próprios sem recompilar os módulos MapLibre; `MAP_BASE_STYLE_URL` continua tendo precedência para estilos vetoriais completos.
- Implementações Leaflet legadas removidas das páginas de clima e infraestrutura, reduzindo os Blades em 641 linhas; Leaflet permanece somente no fluxo de edição que depende do Map Picker.
- Tile MVT real validada com `application/x-protobuf` e cache Nginx validado em sequência `MISS` → `HIT`.
- Worker modular do MapLibre empacotado pelo Vite, evitando requisição 404 no navegador.
- Comando `php artisan geo:audit-native` adicionado para auditoria de sincronização, validade, SRID e tipo geométrico.
- Fallback Voronoi preservado para distritos sem polígono cadastrado.
- Integração de inicialização e destruição preparada para navegação Livewire.
- Três testes de contrato adicionados para os endpoints GeoJSON, com 19 asserções.
- Build de produção executado com sucesso no container da aplicação.
- Visualizador 3D enriquecido com altura, material, resistência, iluminação e conservação reais dos postes; cruzetas, isoladores, luminárias, sombras e paleta adaptativa aos temas claro/escuro foram adicionados.
- Relevo 3D integrado a tiles DEM Terrarium, com postes apoiados na elevação amostrada, cores hipsométricas e vegetação procedural explicitamente ilustrativa.
- Ruas 3D renderizadas como faixas de asfalto apoiadas no relevo, com bordas, guia central por congestionamento e inspeção de atributos ao clicar.
- Mapa de trânsito recebeu timeline reproduzível das últimas 24 horas; velocidade, volume, congestionamento e ocorrências são aplicados às vias MVT via `feature-state`, com payload compacto, cache e índice temporal.
- Payload temporal removido do HTML Livewire e transferido para carregamento assíncrono por `/api/transito/timeline`, evitando estouro do analisador de elementos raiz e o erro de `DOMDocument::loadHTML()` com fonte vazia.
- Tema cartográfico centralizado na fundação MapLibre: raster, fundo, controles, atribuição e popups acompanham automaticamente os modos claro e escuro, inclusive durante troca de tema em tempo real.
- Páginas cartográficas e painel de saúde receberam superfícies, painéis, bordas e sombras mais discretos e consistentes; emojis foram removidos dos arquivos da aplicação e documentação versionada.

### Pendente para homologação do piloto

- Validar visualmente o mapa de distritos em sessão autenticada do Filament.
- Confirmar seleção de cidade, popup, hover e abertura do formulário de edição.
- Comparar enquadramento e cores com o mapa Leaflet anterior.
- Medir tempo de renderização com o conjunto real de distritos.
- Sanear dois cabeamentos de cruzamento com origem e destino na mesma coordenada; eles foram preservados com `geom = NULL` para evitar fabricação ou perda silenciosa de dados.

### Próxima etapa planejada

Concluir a homologação visual e as medições de desempenho com dados reais. O mapa administrativo de distritos permanece em GeoJSON para que edições apareçam imediatamente, sem aguardar a expiração do cache MVT. O Map Picker e seu injetor permanecem deliberadamente em Leaflet porque fazem parte do fluxo de edição de geometrias.

## 15. Operação do Martin

O serviço é iniciado junto com o ambiente:

```bash
docker compose up -d martin nginx
```

Endpoints locais principais:

- catálogo: `http://ccdae.local/tiles/catalog`;
- TileJSON: `http://ccdae.local/tiles/{fonte}`;
- tile MVT: `http://ccdae.local/tiles/{fonte}/{z}/{x}/{y}`.

Fontes publicadas: `distritos`, `vias`, `cabeamentos`, `centrais`, `postes`, `antenas` e `equipamentos`.

A configuração fica em `docker/martin/config.yaml`. A conexão pode ser alterada por `MARTIN_DATABASE_URL`; no ambiente Docker, o padrão aponta para o serviço `postgres`. Novas views no schema `tiles` são descobertas no próximo reinício do Martin.

Para desativar o consumo MVT no frontend sem remover o serviço, use `MAP_MARTIN_ENABLED=false` e limpe o cache de configuração do Laravel.
