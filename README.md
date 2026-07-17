<div align="center">
  <img src="https://laravel.com/img/logomark.min.svg" alt="Laravel Logo" width="100">
  
  # CCDAE - Centro de Coleta de Dados Ambientais e Espaciais
  
  **Plataforma de Gestão Urbana e Manutenibilidade de Infraestruturas**
</div>

---

O **CCDAE** possui um objetivo central muito claro: **Prover visualização avançada de atributos geoespaciais e análise de dados para garantir a melhoria contínua e a manutenibilidade de infraestruturas urbanas vitais** (como energia, internet e telefonia). 

Através da convergência entre IoT, análise temporal e mapeamento georreferenciado, a plataforma permite a identificação proativa de falhas, análise de desgaste e mitigação de riscos estruturais nas cidades.

## 📚 Documentação Técnica do Projeto

- `ANALISE_PROJETO.md` — análise técnica consolidada do estado atual.
- `DOCUMENTACAO_CAMADAS_CASOS_DE_USO.md` — documentação em camadas por caso de uso.
- `GUIA_DESENVOLVIMENTO_SOFTWARE.md` — etapas de desenvolvimento, melhorias, ferramentas e próximos passos.
- `AI_MODIFICACOES.md` — histórico de mudanças realizadas por IA.

## 🗺️ Roadmap e Módulos do Sistema V1

O projeto é concebido em formato modular, garantindo expansão contínua para abranger todos os aspectos da infraestrutura urbana:

- [x] **Módulo de Clima**: Monitoramento de alta frequência de variáveis climáticas via IoT (Temperatura, Chuvas, Raios UV, Pressão, Radiação e Partículas de Ar). *(Concluído)*
- [x] **Módulo de Distritos**: Gestão territorial inteligente com geração dinâmica de fronteiras via Diagramas de Voronoi e renderização de alta performance. *(Concluído)*
- [X] **Mapeamento de Infraestrutura**: Cadastro e visualização espacial de postes, antenas, centrais de distribuição e cabeamentos. *(Concluído)*
- [ ] **Mapeamento de Desgaste**: Análise analítica e visual do estado de conservação física dos ativos mapeados. *(Em andamento)*
- [ ] **Mapeamento de Fios**: Rastreamento da teia de cabeamentos ópticos e elétricos cruzando os distritos. *(Em breve)*
- [ ] **Mapeamento de Trânsito**: Integração com fluxos viários para entender impactos logísticos e gargalos de manutenção. *(Em breve)*
- [ ] **Sugestões de Melhorias**: Sistema automatizado (ou manual) para propor *upgrades* estruturais baseado na inteligência dos dados coletados. *(Em breve)*
- [ ] **Risco por Envelhecimento**: Previsão de falhas baseada em algoritmos preditivos sobre a vida útil e exposição climática da infraestrutura. *(Em breve)*

---

## 🚀 Arquitetura e Tecnologias

A aplicação utiliza uma stack moderna focada em escalabilidade para grande volume de séries temporais (IoT) e renderização reativa:

### ⚙️ Back-end & Infraestrutura
- **PHP 8.2+** / **Laravel 12.0**: Framework base, robusto para construção da API de ingestão e lógica de negócios.
- **PostgreSQL + TimescaleDB**: Banco de dados relacional otimizado para *Time-Series Data*. Essencial para suportar a alta volumetria de coletas de sensores IoT simultâneos (Temperatura, Chuva, UV, Pressão, etc).
- **Docker**: Containerização completa da aplicação e banco de dados para garantir consistência de ambiente.

### 🖥️ Front-end & Painel Administrativo
- **Filament V5**: Framework TALL (Tailwind, Alpine, Laravel, Livewire) utilizado para criar todo o painel de administração e gráficos (ChartWidgets).
- **Livewire 3 & Alpine.js**: Reatividade full-stack sem a necessidade de APIs pesadas, gerenciando os estados do frontend.
- **Tailwind CSS**: Estilização utilitária e design system da aplicação.

### 🗺️ Inteligência Geoespacial
- **Leaflet.js**: Motor principal de renderização de mapas, usando `Canvas` puro para altíssima performance visual.
- **GeoJSON**: Padrão de dados para transporte e armazenamento estruturado de polígonos no banco.
- **Turf.js (Voronoi)**: Algoritmo matemático avançado utilizado no frontend para *geração dinâmica de fronteiras*. Quando um distrito possui apenas coordenadas de centro (lat/lng), o Turf calcula as áreas adjacentes (Diagrama de Voronoi) e desenha "fronteiras inteligentes" que não se sobrepõem, parecendo um quebra-cabeça perfeito em tempo real.

---

## 📊 Modelagem de Dados

A arquitetura de banco de dados do CCDAE divide-se em metadados geográficos e tabelas de alta frequência (IoT):

### Entidades Espaciais (Arquitetura do Modelo)

#### 📍 Distrito
O modelo central da gestão territorial inteligente. Sua arquitetura atual é composta por:
- **Atributos Principais**: `nome`, `cidade`, `latitude`, `longitude` (decimais com precisão 7) e `geojson` (armazenado e castado dinamicamente como `array` para fácil manipulação e serialização).
- **Auditoria e Rastreabilidade**: Implementa o trait `LogsActivity` do pacote Spatie para registrar automaticamente um log imutável de todas as modificações no banco de dados, crucial para governança urbana.
- **Filament Admin & MapPicker**: A interface de administração (`DistritoResource`) é equipada com o **Dotswan MapPicker**. Isso permite que os gestores não apenas cliquem para definir as coordenadas centrais, mas também ativem o modo de desenho (via biblioteca **GeoMan** acoplada) para delinear e editar fronteiras poligonais diretamente no mapa. O shape gerado é convertido automaticamente em `geojson` e hidratado no formulário.
- **Relações (1:N)**: É o nó raiz de diversas captações de IoT. Um distrito `hasMany`: `estacoes`, `temperaturas`, `ventos`, `chuvas`, `pressoes`, `radiacoes`, `uv`, `particulas` e `eventos` climáticos.
- **Relações (N:N)**: Estabelece ligação `belongsToMany` com `RegiaoClimatica`, abrindo margem para interseções de biomas ou macro-regiões climáticas.

#### Outras Estruturas
- `RegiaoClimatica`, `EstacaoMeteorologica`: Estruturas de organização física complementares.

### Telemetria (Time-Series)
O sistema capta "1 payload de coleta que salva em 7 tabelas simultaneamente". Cada grandeza possui sua tabela otimizada:
- `TemperaturaRegistrada`
- `ChuvaRegistrada`
- `IndiceUV`
- `ParticulaAr`
- `PressaoAtmosferica`
- `RadiacaoSolar`
- `VentoRegistrado`

---

## 🧠 Soluções Técnicas de Destaque

### Renderização Avançada de Mapas e Tratamento de Z-Index
Devido ao alto volume de distritos renderizados no mapa interativo (`mapa-distritos.blade.php`), a aplicação utiliza a API nativa de `L.canvas` em preferência ao SVG padrão, mitigando colisões de estilo e aumentando a performance no navegador.
Foi aplicado um isolamento meticuloso de reatividade: **O objeto do mapa do Leaflet foi removido do "Proxy Reativo" do Alpine.js**, evitando a corrupção interna do DOM pelo Livewire e garantindo uma estabilidade impecável da interface ao navegar.

### Geração "On-The-Fly" de Polígonos
Em distritos onde as fronteiras exatas (`geojson`) não foram cadastradas, o frontend utiliza o motor matemático `Turf.js` para processar a nuvem de pontos e calcular dinamicamente suas áreas usando o particionamento de **Voronoi**. Se o cálculo for impossível (ex: coordenadas idênticas), o sistema realiza um *fallback* inteligente desenhando um pino ou círculo no mapa.

---

## 🛠️ Como Executar o Projeto Localmente

1. **Clone o repositório:**
   ```bash
   git clone <url-do-repositorio> ccdae
   cd ccdae
   ```

2. **Instale as dependências PHP via Composer:**
   ```bash
   composer install
   ```

3. **Inicie os containers (Sail / Docker):**
   ```bash
   ./vendor/bin/sail up -d
   ```

4. **Prepare o ambiente e o Banco de Dados:**
   Copie o `.env.example` para `.env` (se já não estiver feito), configure e suba as migrações:
   ```bash
   ./vendor/bin/sail artisan key:generate
   ./vendor/bin/sail artisan migrate
   ```

5. **Inicie o servidor de compilação Front-end:**
   ```bash
   npm install
   npm run dev
   ```

6. **Acesso:**
   Acesse o painel administratido do CCDAE pelo navegador:
   `http://localhost` (ou o domínio mapeado localmente).

---

> **Desenvolvido para monitoramento crítico e performance analítica.**
