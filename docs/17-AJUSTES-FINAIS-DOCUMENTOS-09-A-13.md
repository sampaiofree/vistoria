# 17 — Pendências verificadas dos documentos 09 a 13

> **Status: registro de pendências.** Este documento separa o que já foi entregue
> do que ainda não existe no código. Ele não transforma requisitos futuros em
> comportamento implementado.

## Entregue na árvore atual

| Item | Evidência funcional |
|---|---|
| Catálogo TEL | Resolver próprio, snapshot TEL e rota de classificação |
| Quantitativos múltiplos | Vários itens por avaliação, posição e snapshot agregado |
| Revisão do relatório | `report_revision` único por equipamento e inspeção |
| Aspectos gerais | Templates por organização e atualização da inspeção |
| Nota M2 básica | `SapM2Note`, vínculos por grupo e edição em estado de campo |
| Resumo inicial | Read model com TAC, REC, CIVIL e TEL na ordem do relatório |

## Pendências ainda presentes

### 1. Avaliações publicadas append-only

Uma avaliação `complete` pode ser alterada nos estados `in_progress` e
`in_correction`. A alteração reutiliza a mesma linha, limpa snapshots em alguns
fluxos e pode devolvê-la a `draft`. Ainda não existe cadeia de revisões publicada
independente da cadeia de reinspeções.

### 2. Resumo independente do catálogo

O resumo enumera classificações do catálogo nativo vigente. Um código histórico
removido ou renomeado não é incluído apenas porque existe em um snapshot publicado.
As linhas devem futuramente ser formadas pela união do catálogo atual, snapshots e
vínculos M2.

### 3. Vínculos M2 sem grupo atual

`UpdateInspectionClassificationM2Links` só aceita grupos com `defect_count > 0`.
Assim, um vínculo antigo sem avaliação publicada vigente não aparece como grupo
editável e não pode ser removido pelo formulário atual.

### 4. Cabeçalho completo

O resumo atual entrega área, subárea, local, ABC, data, equipamento, TAG, ordem e
procedimento. Criticidade e desenho geral, entre outros campos do relatório de
referência, ainda não estão no contrato do builder.

### 5. Precisão decimal

Os itens usam `BigDecimal`, mas o resumo converte a soma para `float`. O contrato
futuro deve expor valores brutos como strings decimais e arredondar somente os
rótulos de apresentação.

### 6. Oráculos independentes

Parte da cobertura de classificação usa o catálogo/definições de produção para
montar expectativas. Ainda faltam fixtures independentes para todas as faixas,
limites, rejeições e fórmulas técnicas.

## Critérios para encerrar estas pendências

1. Publicações antigas permanecerem imutáveis e correções criarem nova revisão.
2. O resumo preservar códigos e metadados de snapshots fora do catálogo atual.
3. Vínculos M2 órfãos de grupo poderem ser visualizados e removidos com segurança.
4. O cabeçalho receber contrato completo e fallback explícito para valores ausentes.
5. Quantidades brutas serem strings decimais sem conversão binária.
6. Testes usarem expectativas independentes do catálogo de produção.

## Evidência de qualidade

Na verificação de 20/09/2026, `npm run test:js` passou com 27 testes. A execução
`php artisan test --compact` teve 353 aprovados, 1 falho e 4 ignorados; a falha
foi `ClientCrudTest::test_client_navigation_is_nested_in_administrator_settings_only`.
Esse resultado é uma fotografia do workspace e não foi corrigido nesta tarefa
documental.
