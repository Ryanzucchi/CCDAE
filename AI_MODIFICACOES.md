# Registro de modificações da IA

Este arquivo deve ser atualizado a cada mudança realizada no projeto.

## 2026-07-17

- Criado `.github/copilot-instructions.md` com regras globais para agentes de IA.
- Criado `AI_MODIFICACOES.md` para histórico obrigatório de alterações.
- Atualizado `.github/copilot-instructions.md` com regra de documentação em camadas por caso de uso.
- Adicionada diretriz de sincronização contínua entre código e documentação.
- Adicionada diretriz de correção de escrita ao editar documentação relacionada.
- Atualizado `ANALISE_PROJETO.md` com análise técnica consolidada do estado atual do sistema.
- Criado `DOCUMENTACAO_CAMADAS_CASOS_DE_USO.md` com documentação em camadas por caso de uso.
- Criado `GUIA_DESENVOLVIMENTO_SOFTWARE.md` com etapas, melhorias priorizadas, ferramentas e próximos passos.
- Atualizado `README.md` com seção de documentação técnica e links para os novos arquivos.
- Atualizado `GUIA_DESENVOLVIMENTO_SOFTWARE.md` detalhando as decisões de design para unificação do Scheduler (em `routes/console.php`) e alinhamento do `.env.example`.
- Otimizado `CollectTrafficData` para atualizar ruas em lote único via transação do banco (bypassando eventos Eloquent).
- Corrigido `SyncTransitoRealCommand` para processar centroides de polígonos distritais em caso de latitude/longitude nula.
- Otimizado `InfraestruturaCaceresSeeder` para usar Bulk Inserts com PostgreSQL RETURNING, limitando memória ao processar cabeamentos e equipamentos em lotes de 1000, e fixando a associação de cabos com o distrito.
- Otimizado `InfraestruturaGeojsonController` para retornar chaves estrangeiras de cabeamento no GeoJSON.
- Atualizado `mapa-infraestrutura.blade.php` para implementar offset perpendicular de cabos paralelos e limitação de carregamento detalhado com base no nível de zoom (zoom >= 15).
- Atualizado `DatabaseSeeder.php` para rodar todas as sementes do projeto (`DistritosExcelSeeder`, `TransitoCaceresSeeder`, `InfraestruturaCaceresSeeder`) em sequência e criar o usuário padrão `admin@example.com`.
- Atualizado `SyncTransitoRealCommand.php` para incluir múltiplos endpoints públicos da Overpass API como fallback de conexão resiliente.
- Otimizado `InfraestruturaGeojsonController.php` para carregar dados detalhados (postes, cabeamentos, antenas e equipamentos) apenas se houver distrito selecionado, reduzindo o payload macro de 25MB+ para <200KB.
- Atualizado `mapa-infraestrutura.blade.php` para incluir uma tela de aviso se a visualização 3D for ativada sem distrito, e otimizada a engine do Three.js para reutilizar geometrias (Mesh scaling) e materiais estáticos (evitando instanciamento repetido na GPU que travava a aba).
- Ajustado o visualizador 3D em `mapa-infraestrutura.blade.php` para remover o efeito de neblina (fog), escurecer o fundo para preto puro e amplificar as luzes ambientais e direcionais, gerando alto contraste de visibilidade de postes e fiação a qualquer distância.
- Implementado Raycasting 3D no visualizador tridimensional (`mapa-infraestrutura.blade.php`) com suporte a tooltips flutuantes interativos que exibem propriedades e links de atalho para "Editar Registro" no Filament.
- Corrigida a interposição de eventos de clique nos botões de troca de modo (2D/3D) adicionando regras explícitas de z-index e pointer-events.
- Adicionados links de atalho para "Editar Registro" nos popups do mapa 2D (Leaflet).
- Estendido o limite de zoom máximo do mapa 2D (Leaflet) para nível 22 (com maxNativeZoom: 19 para esticar as imagens de mapa automaticamente).
- Ampliado o zoom de proximidade máxima da câmera do visualizador 3D (OrbitControls) reduzindo minDistance de 5m para 1m.
- Criada a página do Filament `MapaClima.php` e seu respectivo template Blade `mapa-clima.blade.php`, implementando uma Central de Monitoramento Climático que consulta dados reais do banco (temperatura, radiação, chuva, PM2.5, UV) e os renderiza em mapas coropléticos interativos de distritos com vetores direcionais dinâmicos para ventos.
- Corrigidos os mapeamentos de colunas da consulta climática em `MapaClima.php` (precipitacao_mm, pm25, uv, pressao_hpa), preenchendo todos os dados reais e fazendo funcionar os heatmaps de chuva, ar, UV e pressão.
- Corrigida a variável de dados do clima no template Blade (`clima_data` para `climaData`), permitindo que o Livewire serializasse corretamente o payload JSON para o Alpine.js e ativasse os heatmaps.
- Redesenhada a camada de ventos em `mapa-clima.blade.php` para gerar setas contínuas e paralelas de fluxo aerodinâmico (linhas contínuas com ponteiros afilados de fluxo) com dimensionamento proporcional à velocidade e distribuídas em múltiplos pontos dentro de cada distrito.
