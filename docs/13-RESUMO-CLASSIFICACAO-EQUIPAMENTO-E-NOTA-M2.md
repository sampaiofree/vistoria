# 13 — Resumo da classificação do equipamento e Nota M2

## Estado atual

O resumo é montado por `BuildInspectionClassificationSummary` e é entregue no
read model da prévia View First. Ele não cria uma integração com SAP: apenas
mantém números de M2 e seus vínculos por inspeção.

## Ordem e linhas

As categorias aparecem nesta ordem fixa:

```text
TAC → REC → CIVIL → TEL
```

Para cada categoria, o serviço enumera as classificações do catálogo nativo atual.
Cada linha contém:

| Campo | Origem atual |
|---|---|
| `category` | Categoria da avaliação |
| `classification_code` | Código do catálogo atual |
| `classification_label` | Snapshot da primeira avaliação do grupo ou catálogo atual |
| `priority` | Prioridade persistida; fallback para a definição atual |
| `defect_count` | Avaliações `complete` da inspeção naquele código |
| `quantity` | Soma dos `quantity_snapshot.total` do grupo |
| `sap_m2_number` | Nota M2 do vínculo da inspeção, quando existe |

Linhas sem ocorrência continuam no resumo com contagem zero. A classificação mais
crítica da categoria é a linha presente com menor `priority` numérica. Avaliações
rascunho e avaliações sem código não entram.

## Quantidade e unidade

O resumo usa estas unidades de exibição:

| Categoria | Unidade | Comportamento atual |
|---|---|---|
| TAC | `m²` | Soma decimal dos snapshots |
| REC | `kg` | Soma decimal dos snapshots |
| CIVIL | nenhuma | Soma, sem unidade apresentada |
| TEL | nenhuma | `value: null` e `label: —` |

O serviço soma com `BigDecimal`, mas converte o total para `float` antes de
devolver `quantity.value` e `total.value`. O rótulo é formatado com duas casas e
vírgula decimal. Isso é uma limitação conhecida, não um contrato de precisão
decimal exata.

## Cabeçalho do equipamento

O contrato atual de `equipment` entrega sempre estas chaves:

```text
area, subarea, installation_location, abc_code, inspected_on,
name, tag, work_order, inspection_procedure
```

Área e subárea vêm dos campos textuais congelados no snapshot do equipamento.
Datas e ordem vêm da inspeção. Campos como criticidade, desenho geral e demais
colunas previstas no relatório de referência ainda não são entregues pelo builder;
não devem ser inferidos do cadastro atual.

## Nota M2

`SapM2Note` é única por organização, equipamento e número SAP. Uma
`InspectionClassificationM2Link` associa uma inspeção, categoria, código de
classificação e nota. A tela pode editar vínculos quando o usuário tem a Policy
`manageClassificationM2`: Inspetor ativo, responsável e inspeção em
`in_progress` ou `in_correction`.

No estado atual, uma linha só é elegível para vínculo se tiver ao menos uma avaria
publicada (`defect_count > 0`). O vínculo é atualizado ou removido informando
`sap_number` nulo, mas uma linha sem avaria publicada é rejeitada pela ação mesmo
quando a intenção é remover um vínculo antigo. A entidade `SapM2Note` não é
apagada ao remover o vínculo.

Não há chamada ao SAP. O número é texto validado e persistido dentro do tenant.

## Limitações e decisões abertas

- linhas históricas cujo código saiu do catálogo não são adicionadas ao resumo;
- vínculos M2 sem grupo atual não podem ser removidos pelo fluxo atual;
- o cabeçalho ainda não contém criticidade e desenho geral;
- valores brutos agregados não são strings decimais exatas;
- a unidade oficial de CIVIL no resumo e a regra de criticidade do cabeçalho ainda
  precisam de decisão funcional;
- regras normativas de M2 para classes sem avaria e compartilhamento entre grupos
  continuam como pauta.

As correções propostas para essas limitações estão registradas no
[documento 17](17-AJUSTES-FINAIS-DOCUMENTOS-09-A-13.md).
