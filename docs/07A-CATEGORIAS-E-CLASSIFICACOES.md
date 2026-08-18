# 07A — Categorias e Classificações de Avarias

## 1. Objetivo

Transformar categoria e classificação em dados configuráveis por organização, mantendo a categoria na avaria permanente e a classificação em cada avaliação histórica.

```text
Defect
└── defect_category_id

DefectAssessment
├── defect_classification_id
└── classification_snapshot
```

## 2. Escopo desta etapa

Incluído:

- catálogo de categorias por organização;
- catálogo de classificações dentro de uma categoria;
- status ativo/inativo, sem exclusão definitiva;
- isolamento por organização;
- código da categoria usado na geração da avaria;
- classificação selecionada por avaliação;
- snapshot histórico da classificação;
- provisionamento inicial CIVIL/CV-1 a CV-5.

Não incluído nesta etapa:

- regras GUT obrigatórias;
- enquadramento automático;
- workflow técnico específico TAC ou REC;
- campos personalizados por categoria;
- importação de catálogo externo.

## 3. Regras de negócio

### 3.1 Categoria

Uma categoria pertence a uma organização e possui código técnico normalizado em maiúsculas. O código é único dentro da organização e compõe o código da avaria.

Depois de utilizada por uma avaria, a categoria não pode ser trocada e seu código não pode ser alterado pela interface normal.

Categoria inativa não pode ser usada para novas avarias, mas continua visível em avarias e relatórios históricos. Avarias existentes continuam podendo ser reinspecionadas.

### 3.2 Classificação

Uma classificação pertence a uma categoria e organização. Seu código é único dentro da categoria.

Classificação inativa não pode ser selecionada em uma nova avaliação, mas permanece visível no histórico. Depois de utilizada, seu código e categoria não podem ser alterados pela interface normal.

### 3.3 Avaliação

Para condições ativas (`new`, `unchanged`, `worsened` e `improved`), a classificação é obrigatória ao concluir a avaliação. Para `repaired`, `not_located` e `not_inspected`, a classificação atual é nula; a classificação anterior permanece no histórico.

O backend valida organização e compatibilidade entre a classificação selecionada e a categoria permanente da avaria.

### 3.4 Criticidade

`position` controla a ordem de exibição. `severity_rank` representa a criticidade e deve ser copiado para o snapshot quando usado em resumos ou relatórios.

## 4. Estrutura de dados

### `defect_categories`

Campos principais:

```text
public_id
organization_id
name
code
description
status
position
created_by
updated_by
```

### `defect_classifications`

Campos principais:

```text
public_id
organization_id
defect_category_id
code
name
description
status
position
severity_rank
created_by
updated_by
```

As FKs compostas incluem `organization_id` para reforçar o isolamento no banco.

Durante a transição, os campos legados `defects.category`, `defect_code_sequences.category` e `defect_assessments.classification_code` continuam disponíveis para compatibilidade e backfill.

## 5. Provisionamento e migração

A migration aditiva cria o catálogo CIVIL para organizações existentes e faz o backfill das avarias, sequências e avaliações que já possuem códigos CIVIL. Organizações novas devem receber o mesmo catálogo pela Action de provisionamento.

O seed pode complementar o ambiente de desenvolvimento, mas não é a única fonte de dados necessária em produção.

## 6. Compatibilidade com GUT

O GUT não é requisito para selecionar uma classificação nesta etapa. O núcleo atual permanece opcional e, em uma etapa futura, cada faixa GUT deverá apontar para `defect_classifications.id`, preservando o catálogo como fonte única da classificação.

## 7. Testes obrigatórios

- categoria e classificação isoladas por organização;
- código de categoria único por organização;
- código de classificação único dentro da categoria;
- categoria CIVIL provisionada para organizações existentes;
- nova avaria vinculada à categoria do catálogo;
- sequência baseada em `defect_category_id`;
- classificação incompatível rejeitada;
- classificação inativa não selecionável;
- classificação anterior preservada após reinspeção;
- snapshot histórico preservado após alteração de nome ou inativação.

## 8. Critérios de aceite

- [x] catálogo CIVIL criado por organização;
- [x] FKs aditivas criadas em avarias, avaliações e sequências;
- [x] backfill legado implementado;
- [x] novas avarias CIVIL usam o catálogo;
- [x] CRUD administrativo de categorias;
- [x] CRUD administrativo de classificações;
- [x] seleção manual na tela de avaliação;
- [x] políticas e testes completos de isolamento;
- [x] GUT removido da obrigatoriedade da inspeção;
- [x] resumos operacionais sem códigos hard-coded;
- [ ] relatório/exportação sem códigos hard-coded e enforcement final da classificação manual.

## 9. Próximo passo

Concluir a integração do catálogo no relatório/exportação junto ao módulo 08A e substituir gradualmente os fallbacks legados (`DefectCategory` e `classification_code`) depois que os consumidores históricos forem migrados.
