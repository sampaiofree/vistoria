# Resumo da classificação e Nota M2

A aba de classificação usa `BuildInspectionClassificationSummary`. Ela reúne
avaliações `complete` da inspeção e, na reinspeção seletiva, avaliações históricas
que continuam no escopo do relatório. As categorias do quadro são TAC, REC, CIVIL
e TEL, nessa ordem. Estruturas Solidárias (`ES`) aparecem na documentação e nas
tratativas especiais, sem linha de classe GUT/TEL.

## Linhas e totais

Cada categoria enumera as classes do catálogo nativo atual, inclusive linhas com
zero avarias. O grupo com menor prioridade numérica é o mais crítico. As linhas
somam `quantity_snapshot.total`: TAC em m², REC em kg, CIVIL em m³; TEL não tem
quantitativo. Os itens são calculados com `BigDecimal`, mas o valor agregado
entregue ao navegador é convertido a `float` e o rótulo usa duas casas decimais.

O serviço também entrega `report_rows` com marcadores vazios IE-0, CV-0 e TE-0.
Esses marcadores não são classificações atribuídas às avaliações. A criticidade
do cabeçalho usa a pior prioridade entre CV, REC e TEL; TAC só é considerado se
não houver classe nessas categorias.

O cabeçalho do quadro mostra área, subárea, local, ABC, TAG, ordem de serviço,
data da inspeção, desenho geral, número do procedimento e criticidade quando
existirem. Dados cadastrais da inspeção vêm do snapshot de contexto. Os prazos
M2 são calculados para CV-1 a CV-3, IE-1 a IE-3 e TA-1 a TA-3; veja
[Classificações](../referencia/classificacoes.md).

## Vínculos M2 e tratativas especiais

O Planejador membro ativo vinculado como `preparer` pode editar a aba em `awaiting_m2`; o
Revisor responsável pode editá-la em `in_review`. O Inspetor a consulta. Uma
`SapM2Note` guarda um número por organização e equipamento; o vínculo por
inspeção, categoria e classe é `InspectionClassificationM2Link`. O número deve ter
exatamente oito caracteres. Não há integração com SAP.

É permitido salvar a aba parcialmente. Para enviar à revisão, cada classe com
avaria publicada precisa de M2; avaliações marcadas como condição insegura, com
Nota de Engenharia ou da categoria ES exigem **Serviço, Prioridade e Nota** na
tratativa especial. Aprovação e liberação repetem essa cobertura. Edições são
auditadas no histórico da inspeção.

Uma nova reinspeção copia os vínculos M2 da última inspeção liberada. Notas
especiais das avarias fora do escopo são copiadas na criação; para avarias
selecionadas, os campos são copiados quando a nova avaliação é criada. As cópias
pertencem à nova inspeção e podem ser editadas sem alterar a anterior.

## Limites atuais

- Classes removidas do catálogo não aparecem como novas linhas históricas no
  resumo, mesmo se existirem em snapshots antigos.
- Vínculos M2 de um grupo que deixou de ter avaria publicada são preservados,
  porém ficam ocultos no quadro e não podem ser removidos pelo formulário atual.
- O agregado numérico entregue à interface é `float`; para cálculos de precisão,
  use os snapshots decimais dos itens.
- Corrigir uma avaliação publicada reutiliza a mesma linha depois de movê-la a
  rascunho. Não existe revisão append-only dentro da inspeção.
