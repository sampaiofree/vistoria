# Avaria

- Modelo: `Defect`.
- A avaliação da avaria dentro de uma inspeção é registrada no modelo
  `DefectAssessment`. É nela que ficam a condição observada no ciclo e o estado
  de publicação.

## Campos da avaria (`Defect`)

| Campo | Tipo / opções | Relação com o status |
|---|---|---|
| `public_id` | ULID público | Identificador externo da avaria. |
| `organization_id` | referência à organização | Define a organização proprietária. |
| `equipment_id` | referência ao equipamento | Define o equipamento ao qual a avaria pertence. |
| `first_inspection_id` | referência à primeira inspeção | Mantém a inspeção em que a avaria foi criada. |
| `code` | texto | Código permanente da avaria. Não é duplicado na reinspeção. |
| `category` | `CV`, `TAC`, `REC` ou `TEL` | Categoria técnica da avaria. |
| `sequence_number` | número inteiro | Sequência da avaria dentro de equipamento e categoria. |
| `title` | texto | Título da avaria. |
| `origin_description` | texto opcional | Descrição da origem. |
| `status` | `active`, `repaired`, `archived` | Estado corrente da avaria, detalhado abaixo. |
| `repaired_at` | data/hora opcional | Preenchido quando a avaria passa para `repaired`; vazio nos demais estados. |
| `archived_at` | data/hora opcional | Reservado para o arquivamento; vazio enquanto não arquivada. |
| `created_by` / `updated_by` | referências ao usuário | Auditoria de autoria. |
| `created_at` / `updated_at` | data/hora | Auditoria de criação e última atualização. |

## Status da avaria (`Defect.status`)

| Rótulo | Valor | Significado na reinspeção |
|---|---|---|
| Ativa | `active` | Avaria em acompanhamento. É trazida como avaria herdada na próxima reinspeção. |
| Reparada | `repaired` | A última avaliação válida publicada foi marcada como `treated`. Não é trazida automaticamente para uma nova reinspeção. Se voltar a ser identificada, deve ser registrada como recorrência/nova avaria vinculada à original. |
| Arquivada | `archived` | Estado administrativo/final, preservado para histórico. Não é recalculado pelas avaliações e não é trazido automaticamente para reinspeção. |

## Campos de estado da avaliação (`DefectAssessment`)

| Campo | Opções | Finalidade |
|---|---|---|
| `status` | Rascunho (`draft`), Publicada (`complete`) | Controla se a avaliação ainda pode ser editada e se seus dados definem o estado corrente da avaria. |
| `condition` | Nova (`new`), Reinspecionada (`reinspected`), Reclassificada (`reclassified`), Cancelada (`canceled`), Cancelada S/R (`canceled_sr`), Tratada (`treated`) | Registra o resultado técnico da avaria naquela inspeção. |
| `previous_assessment_id` | referência opcional à avaliação anterior | Cria a cadeia histórica de uma avaria herdada. |
| `assessed_at` | data/hora opcional | Data/hora da publicação/conclusão da avaliação. |

## Regras de atualização do status

- Uma avaliação publicada (`complete`) com condição `treated` muda a avaria para
  `repaired` e preenche `repaired_at`.
- Uma avaliação publicada com condição `new`, `reinspected` ou `reclassified`
  mantém ou retorna a avaria para `active` e limpa `repaired_at`.
- As condições `canceled` e `canceled_sr` permanecem no histórico, mas não
  determinam o estado corrente da avaria.
- Uma avaliação em `draft` não produz um resultado definitivo; ao retirar uma
  publicação de `treated` para rascunho, a avaria volta a `active`.
- O status `archived` não é atribuído automaticamente por uma condição de
  avaliação.
