# Guia de desenvolvimento de software (CCDAE)

Este guia define etapas práticas para evolução contínua do projeto, com melhorias, ferramentas recomendadas e próximos passos.

---

## 1) Etapas de desenvolvimento adotadas

## Etapa 1 — Entendimento do caso de uso

Objetivo: alinhar escopo, resultado esperado e impacto urbano/técnico.

Saídas mínimas:
- problema e objetivo funcional;
- atores e fluxo principal;
- critérios de aceitação;
- restrições (dados, performance, integrações externas).

## Etapa 2 — Arquitetura antes do código

Objetivo: desenhar a solução por camadas antes da implementação.

Checklist:
- componentes impactados (UI, aplicação, domínio, banco);
- impacto em jobs/comandos/schedules;
- estratégia de dados e migração;
- risco operacional e rollback.

## Etapa 3 — Pesquisa de exemplos

Objetivo: reduzir retrabalho e manter padrão do projeto.

Fontes:
- exemplos internos já existentes no repositório;
- documentação oficial (Laravel, Filament, Timescale, PostGIS);
- referências de integração (Open-Meteo, OSM/Overpass).

## Etapa 4 — Implementação incremental

Objetivo: entregar valor em incrementos pequenos, coerentes e revisáveis.

Prática:
- alterações coesas por caso de uso;
- sem mistura de refatorações não relacionadas;
- preservar convenções existentes.

## Etapa 5 — Validação técnica

Objetivo: garantir comportamento correto sem regressão.

Aplicar testes/lint/build de forma direcionada ao escopo alterado.

## Etapa 6 — Documentação e rastreabilidade

Objetivo: manter documentação viva e aderente ao código.

Obrigatório:
- registrar alteração em `AI_MODIFICACOES.md`;
- atualizar `DOCUMENTACAO_CAMADAS_CASOS_DE_USO.md` quando o comportamento mudar;
- atualizar `ANALISE_PROJETO.md` em mudanças arquiteturais.

---

## 2) Melhorias recomendadas (priorizadas)

| Prioridade | Melhoria | Impacto | Esforço |
|---|---|---|---|
| Alta | Unificar o agendamento (hoje existe em `bootstrap/app.php` e `routes/console.php`) | Evita execução duplicada de jobs e inconsistências | Médio |
| Alta | Expandir testes para jobs/comandos de clima, trânsito e geoespacial | Reduz risco em produção | Médio/Alto |
| Alta | Padronizar ambiente (`.env.example` alinhado com PostgreSQL/Timescale do projeto) | Onboarding mais previsível | Baixo |
| Média | Extrair regras de negócio longas dos jobs para serviços especializados | Melhor manutenção e testabilidade | Médio |
| Média | Criar monitoração operacional externa (Sentry + métricas) além do painel | Observabilidade em produção | Médio |
| Média | Fortalecer validações de GeoJSON e coordenadas de entrada | Integridade geoespacial | Médio |
| Baixa | Cobertura de testes de interface para fluxos críticos no painel | Segurança de UX | Médio |

---

## 3) Ferramentas sugeridas

## Qualidade e segurança
- **Laravel Pint** (já presente): padronização de estilo.
- **PHPStan/Larastan**: análise estática para reduzir bugs.
- **Rector** (opcional): modernização assistida de código PHP.
- **Dependabot/Renovate**: atualização de dependências contínua.

## Observabilidade
- **Sentry**: rastreamento de exceções.
- **OpenTelemetry**: trilhas distribuídas para jobs e integrações.
- **Grafana + Prometheus** (ou stack compatível): métricas de operação.

## Banco e desempenho
- **Timescale Toolkit / queries de observabilidade** para retenção, compressão e performance.
- **EXPLAIN ANALYZE** contínuo para consultas de mapas e séries temporais.

## Produto e entrega
- **GitHub Actions** para pipeline CI (teste + lint + análise estática).
- **Conventional Commits** e PR templates para rastreabilidade.

---

## 4) Próximos passos objetivos

## Curto prazo (1-2 semanas)
1. Unificar agendamento de jobs em uma única fonte de verdade.
2. Atualizar `.env.example` para o banco padrão do projeto.
3. Criar testes para `CollectClimateData` e `CollectTrafficData`.
4. Criar teste do endpoint `/api/distritos/geojson`.

## Médio prazo (3-6 semanas)
1. Refatorar serviços de coleta para separar integração externa e transformação de payload.
2. Adicionar validações robustas de geometrias e dados de trânsito.
3. Implantar monitoramento de erros e métricas fora do painel administrativo.

## Longo prazo (7-12 semanas)
1. Evoluir para análises preditivas de desgaste e risco de ativos.
2. Integrar fontes reais adicionais de mobilidade e clima.
3. Consolidar indicadores executivos para manutenção urbana orientada por dados.

---

## 5) Critérios de pronto (Definition of Done)

Uma entrega é considerada pronta quando:

1. Caso de uso implementado e funcional.
2. Arquitetura e impactos registrados.
3. Testes relevantes executados para o escopo.
4. Documentação em camadas atualizada.
5. `AI_MODIFICACOES.md` atualizado com o histórico da alteração.
