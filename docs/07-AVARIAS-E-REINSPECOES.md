# 07 — Avarias, avaliações e reinspeções

## Identidade da avaria

`Defect` é a identidade persistente do problema e `DefectAssessment` é sua leitura
em uma inspeção. O código de avaria é gerado de forma concorrente por organização,
equipamento e categoria. Uma avaria pode relacionar-se a outra como divisão,
recorrência ou relacionada; recorrência exige avaria de origem reparada.

As categorias disponíveis são CIVIL (`CV`), TAC, REC e TEL. A classificação é
resultado do catálogo nativo e da entrada técnica, não uma escolha livre do usuário.

## Avaliação e publicação

Uma avaliação começa como `draft` e é publicada como `complete`. Condições como
`new`, `reinspected`, `reclassified` e `treated` exigem comentário, recomendação,
classificação aplicável, fotos prontas, mapa pronto, localização confirmada e,
exceto TEL, ao menos um item de quantitativo. Condições canceladas exigem motivo e
dispensam evidências.

A publicação guarda snapshots da avaria, classificação e quantitativos. GUT é usado
por CIVIL, TAC e REC; TEL usa sua própria pontuação e snapshot. Publicar sincroniza
o estado da avaria conforme a condição.

Uma avaliação `complete` é somente leitura. Para corrigi-la, um usuário autorizado
precisa primeiro movê-la explicitamente para `draft`; então os snapshots da
publicação são invalidados e a avaliação precisa ser publicada novamente. Ainda não
existe uma cadeia de revisões dentro da mesma inspeção.

## Quantitativos

Uma avaliação agrega zero ou mais `DefectAssessmentQuantity` ordenados por posição.
Cada item guarda entrada estruturada, cálculo, unidade, quantidade, total e
snapshot de fórmula; o agrupador da avaliação guarda o snapshot agregado na
publicação.

- CIVIL calcula volume por comprimento × altura × largura × quantidade (`m³`);
- TAC recebe áreas manuais (`m²`);
- REC calcula peso nativo ou aceita peso manual nos elementos permitidos (`kg`);
- TEL não possui quantitativo técnico.

Os cálculos no backend usam aritmética decimal. O resumo de classificação ainda
converte o total agregado para `float`; essa exceção e sua correção proposta estão
no documento 17.

## Reinspeção e histórico

Uma nova inspeção após uma liberação referencia a última inspeção liberada do
equipamento. A lista reúne avarias criadas no ciclo e avarias ativas da cadeia
anterior. Avaliar uma avaria herdada cria avaliação em rascunho `reinspected`,
referenciada à avaliação publicada anterior; identidade, código e relações da
avaria não são duplicados.

O mapa e a geometria podem ser herdados, mas a localização precisa de confirmação
na nova avaliação. O relatório considera avaliações `complete`; dados de inspeções
canceladas permanecem auditáveis, mas não definem o estado corrente da avaria.
