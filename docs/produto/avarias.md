# Avarias, avaliações e reinspeções

## Identidade da avaria

`Defect` é a identidade persistente do problema e `DefectAssessment` é sua leitura
em uma inspeção. O código de avaria é gerado de forma concorrente por organização,
equipamento e categoria. Uma avaria pode relacionar-se a outra como divisão,
recorrência ou relacionada; recorrência exige avaria de origem reparada.

As categorias disponíveis são CIVIL (`CV`), TAC, REC, TEL e Estruturas Solidárias
(`ES`). Para as quatro primeiras, a classificação resulta do catálogo nativo e
das entradas técnicas. ES não possui classificação automática.

## Avaliação e publicação

Uma avaliação começa como `draft` e é publicada como `complete`. Condições como
`new`, `reinspected`, `reclassified` e `treated` exigem comentário,
recomendação e ao menos duas fotos prontas, em quantidade par. CIVIL, TAC e REC exigem quantitativo;
TEL e ES não. As três primeiras condições exigem classificação GUT para CIVIL,
TAC e REC, ou classificação própria para TEL. `treated` não recebe nova
classificação. Todas, exceto ES, exigem mapa pronto com número de projeto e
localização confirmada. Condições canceladas exigem comentário e motivo, mas
dispensam as evidências.

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
- TEL e ES não possuem quantitativo técnico.

Os cálculos no backend usam aritmética decimal. O resumo converte o total
agregado para `float` antes de enviá-lo à interface; veja
[Classificação e Nota M2](classificacao-e-m2.md).

## Reinspeção e histórico

Uma nova inspeção após uma liberação referencia a última inspeção liberada do
equipamento. A lista reúne avarias criadas no ciclo e avarias ativas da cadeia
anterior. Avaliar uma avaria herdada cria avaliação em rascunho `reinspected`,
referenciada à avaliação publicada anterior; identidade, código e relações da
avaria não são duplicados.

O mapa e a geometria podem ser herdados, mas a localização precisa de confirmação
na nova avaliação. O relatório considera avaliações `complete`; dados de inspeções
canceladas permanecem auditáveis, mas não definem o estado corrente da avaria.

## Reinspeção seletiva

O planejamento permite escolher as avarias que exigem nova avaliação. Todas começam
selecionadas; havendo avarias elegíveis, ao menos uma deve permanecer selecionada.
A seleção pode ser alterada pelo Planejador vinculado somente em `planned`. Novas
avarias cadastradas na visita sempre exigem avaliação.

`inspection_defect_scopes` fixa a seleção e a avaliação publicada de origem de cada
avaria herdada. As desmarcadas aparecem preenchidas, com identificação de histórico
mantido e edição bloqueada, sem criar outra avaliação ou copiar arquivos. Avarias
sem avaliação histórica publicada não podem ser desmarcadas. O vínculo mantém a
composição do relatório mesmo se a avaria for reparada em um ciclo posterior.

`InspectionAssessmentResolver` fornece avaliações atuais e referências históricas
para listas, mapas, fotos, classificação e exportações. O progresso e a cobertura
obrigatória consideram somente avarias selecionadas e novas. Prazos herdados são
preservados; grupos com vencimentos diferentes exibem o menor vencimento. Nas
novas reinspeções, os vínculos M2 são copiados da última inspeção liberada. As
tratativas especiais de avarias desmarcadas são copiadas na criação; as das
selecionadas são copiadas quando a nova avaliação é criada. Os registros
pertencem ao ciclo atual e podem ser editados sem alterar o histórico. A data da
inspeção não é herdada.

As opções são consultadas em `GET /inspections/reinspection-options` por
`equipment_id`, com `inspection_id` opcional para editar um planejamento. Criação
e atualização aceitam `reinspection_defect_ids` e `reinspection_base_id` (proteção
contra histórico alterado após carregar o formulário). A leitura das desmarcadas
usa `inspections/{inspection}/defects/{defect}/historical`, mantendo a navegação na
inspeção atual.

Inspeções sem `reinspection_scope_version` mantêm o comportamento anterior.
Criações sem seleção explícita incluem todas as avarias; atualizações sem o campo
preservam o escopo, exceto quando o equipamento muda. Nenhum relatório antigo é
reescrito pela migração.
