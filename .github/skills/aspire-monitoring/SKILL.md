---
name: aspire-monitoring
description: >-
  Skill de monitoramento para aplicações Aspire: logs, traces, spans, estado de recursos
  e export de telemetria. Fonte oficial: microsoft/aspire-skills.
license: MIT
metadata:
  author: Microsoft
  source: https://github.com/microsoft/aspire-skills/blob/main/skills/aspire-monitoring/SKILL.md
---

# Aspire Monitoring

Use esta skill para investigar comportamento da aplicação com foco em observabilidade:

- `aspire describe` para estado de recursos e endpoints.
- `aspire otel logs` para logs estruturados.
- `aspire otel traces` e `aspire otel spans` para rastreamento distribuído.
- `aspire export` para bundle portátil de diagnóstico.

## Comandos principais

```bash
aspire describe --format Json
aspire otel logs [resource] --format Json
aspire otel traces [resource] --format Json
aspire otel spans [resource] --format Json
aspire export [resource]
```

## Roteamento rápido

1. Local (`aspire start`): use Aspire CLI para diagnóstico.
2. AKS: use `kubectl` + Container Insights.
3. Azure (App Service/Container Apps): use ferramentas Azure/App Insights.
4. Docker/Compose: use `docker logs` e `docker compose logs`.

## Observação

Esta instalação é no escopo do projeto, em `.github/skills/aspire-monitoring/`.
