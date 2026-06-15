<div align="center">
  <img src="https://laravel.com/img/logomark.min.svg" alt="Laravel Logo" width="100">
  <img src="https://raw.githubusercontent.com/filamentphp/filament/3.x/art/logo.svg" alt="Filament Logo" width="100" style="margin-left: 20px;">
  
  # CCDAE - Centro de Coleta de Dados Ambientais e Espaciais
  
  **Plataforma de Monitoramento Climático e Análise Geoespacial com IoT**
</div>

---

O **CCDAE** é uma plataforma avançada construída para coleta, processamento de alta performance e visualização interativa de dados meteorológicos e ambientais coletados via sensores IoT. O sistema oferece monitoramento em tempo real de distritos urbanos e painéis analíticos para acompanhamento de métricas climáticas vitais.

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

### Entidades Espaciais
- `Distrito`: Guarda informações geográficas (latitude, longitude, geojson).
- `RegiaoClimatica`, `EstacaoMeteorologica`: Estruturas de organização física.

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
