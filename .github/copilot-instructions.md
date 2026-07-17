# Contexto e regras gerais para IA

Estas regras valem para qualquer solicitação neste repositório.

## Regras obrigatórias

1. **Registrar todas as modificações em Markdown**  
   Toda alteração de código, configuração, documentação ou estrutura deve ser registrada em `AI_MODIFICACOES.md`.

2. **Após afirmativa do usuário, salvar boas práticas/contexto em Markdown**  
   Quando o usuário confirmar uma direção, consolidar decisões, boas práticas e regras em um arquivo `.md` de contexto (este arquivo é a referência principal).

3. **Definir arquitetura/caso de uso antes de codificar**  
   Antes de implementar, descrever de forma objetiva:
   - objetivo do caso de uso;
   - componentes impactados;
   - fluxo de dados/execução;
   - riscos e critérios de aceitação.

4. **Pesquisar exemplos antes de executar qualquer ação**  
   Antes de alterar código, buscar exemplos no próprio projeto e/ou referências confiáveis para orientar a implementação.

5. **Manter documentação em camadas por caso de uso**  
   Conforme o código evoluir, manter documentação em Markdown estruturada em camadas (visão geral, domínio, aplicação e detalhes técnicos) para cada caso de uso implementado.

6. **Sincronizar documentação com o código sempre que houver alteração**  
   Toda modificação de código deve ser refletida na documentação do caso de uso correspondente para manter o estado atual do sistema.

7. **Corrigir escrita ao tocar na documentação relacionada**  
   Ao editar uma parte do código, revisar e corrigir erros de escrita/clareza na seção documental correspondente.

## Aplicação prática

- Sempre que possível, reaproveitar padrões já existentes no projeto.
- Evitar mudanças sem contexto arquitetural mínimo.
- Não pular o registro de alterações em `AI_MODIFICACOES.md`.
- Tratar documentação como parte do código: alterar código implica revisar a documentação relacionada.
