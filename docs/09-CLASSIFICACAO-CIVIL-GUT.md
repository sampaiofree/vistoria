# 09 — Categorias, classificações e GUT

## Fonte de verdade atual

Categorias, classificações e opções GUT são registros configuráveis por
organização:

```text
DefectCategory
├── DefectClassification
└── DefectCategoryGutOption

Defect ── DefectCategory
DefectAssessment ── DefectClassification + snapshots
```

O relacionamento `defect_category_id` é canônico. O enum legado de categoria e
campos textuais antigos ainda aparecem em migrations e compatibilidade de leitura,
mas não definem o catálogo atual.

## Provisionamento padrão

Toda organização criada recebe, de forma idempotente, três categorias com opções
GUT de gravidade, urgência e tendência. Organizações anteriores também são
provisionadas por migration.

| Categoria | Classificações padrão | Faixas GUT |
|---|---|---|
| CV / CIVIL | CV-1 a CV-5 | 75–125, 36–74, 16–35, 8–15, 1–7 |
| TAC | TA-1 a TA-3 | 45–75, 25–44, 15–24 |
| REC | IE-1 a IE-5 | 75–125, 36–74, 16–35, 8–15, 1–7 |

Cada critério recebe inicialmente notas 1 a 5 com cores. A Action usa
`firstOrCreate` e complementa somente valores padrão ausentes, sem sobrescrever
personalizações já existentes.

## Administração

Somente administradores da empresa acessam o catálogo. Eles podem:

- criar e editar categorias;
- ativar ou inativar categorias;
- configurar se a categoria exige mapa;
- criar e editar classificações;
- ativar ou inativar classificações;
- definir código, nome, descrição, cor, posição, severidade e faixa;
- substituir as opções GUT de cada critério.

Não há exclusão destrutiva pela interface. Categorias ou classificações inativas
continuam preservadas no histórico, mas não são usadas como opções novas.

O código da categoria é único na organização. O código da classificação é único
dentro da categoria. Códigos técnicos são normalizados em maiúsculas e cores no
formato `#RRGGBB`.

## Regras das faixas

Toda classificação cadastrada possui limite inferior e superior. O limite inferior
não pode exceder o superior. Faixas de classificações ativas da mesma categoria
não podem se sobrepor; essa validação também é executada antes de reativar uma
classificação.

Lacunas entre faixas são permitidas. Nesse caso, o cálculo GUT é salvo sem
classificação correspondente. Sobreposição é tratada como configuração inválida e
impede a resolução.

## Opções GUT

Cada opção contém:

- critério: `gravity`, `urgency` ou `trend`;
- nota inteira entre 0 e 65.535;
- cor hexadecimal.

O par critério/nota é único dentro da categoria. A tela de configuração substitui
o conjunto inteiro em uma transação.

## Cálculo na avaliação

Para cada critério, o valor enviado precisa existir entre as opções configuradas
da categoria. O resultado é:

```text
GUT = gravidade × urgência × tendência
```

O resolver procura uma única classificação ativa cuja faixa contenha o resultado.
Ao salvar, a avaliação registra:

- as três notas e o produto;
- snapshot da categoria, opções escolhidas e cores;
- classificação encontrada e snapshot da sua faixa, nome, cor e severidade;
- usuário e data da classificação.

O frontend não escolhe manualmente o código final. A classificação é derivada no
backend a partir das faixas da categoria.

## Publicação da avaliação

As condições `new`, `unchanged`, `worsened` e `improved` exigem:

- categoria configurável;
- uma opção válida para cada critério;
- GUT salvo antes da publicação.

Essas condições e `repaired` também exigem quantitativo principal e ao menos duas
fotografias prontas para a publicação.

`repaired`, `not_located` e `not_inspected` limpam GUT e classificação atuais. Os
snapshots das avaliações anteriores permanecem preservados.

Comentário é obrigatório em toda publicação. Justificativa é adicionalmente
obrigatória para `not_located` e `not_inspected`.

## Quantitativos e resumos

Cada avaliação possui no máximo um quantitativo principal. Resumos agrupam valores
por unidade e classificação sem somar unidades incompatíveis. O relatório usa os
snapshots e as avaliações publicadas, não recalcula silenciosamente o histórico
com faixas alteradas depois.

## Campos legados

`classification_profiles` foi criado e removido por migrations históricas. O enum
`ClassificationProfileStatus` existe apenas para que migrations antigas continuem
reproduzíveis. Não há model, tela, rota ou serviço runtime de perfil de
classificação.

Também permanecem campos legados como `defects.category` e
`defect_assessments.classification_code` para compatibilidade durante a transição.
Novas regras devem usar categoria, classificação e opções GUT relacionais.

## Limites atuais

- o provisionamento do catálogo não equivale a implementar fluxos especializados
  completos de TAC e REC;
- prazos descritivos padrão não são um motor automático de agenda;
- uma lacuna de faixa pode produzir GUT sem classificação;
- o layout do relatório ainda contém textos específicos do formato técnico atual.

## Cobertura automatizada

Os testes verificam provisionamento idempotente, isolamento, CRUD, status, cores,
faixas e sobreposição, opções GUT, cálculo, snapshots, condições sem classificação,
quantitativos e integração com avaliação, mapa e relatório.
