# 07 — Avarias, avaliações e reinspeções

## Separação entre identidade e observação

Uma avaria (`Defect`) é permanente no equipamento. Cada inspeção registra uma
avaliação (`DefectAssessment`) dessa avaria.

```text
Equipment
└── Defect
    ├── primeira Inspection
    ├── categoria
    ├── código permanente
    └── avaliações por Inspection
```

Essa separação preserva o histórico: uma nova inspeção não sobrescreve condição,
comentário, classificação, quantidade, fotos ou localização anteriores.

## Código da avaria

O equipamento possui um prefixo técnico. Cada categoria mantém uma sequência por
equipamento e organização. O código segue:

```text
PREFIXO-CATEGORIA-NNN
```

Por exemplo, para prefixo `EQ01` e categoria `CV`, o primeiro código é
`EQ01-CV-001`. A geração usa transação e bloqueio para evitar números duplicados.
O código persistido não muda quando TAG, prefixo ou nome da categoria forem
alterados depois.

## Estados da avaria

| Estado | Significado |
|---|---|
| `active` | Continua exigindo acompanhamento |
| `repaired` | A avaliação completa mais recente marcou reparo |
| `archived` | Estado previsto no domínio e preservado pelo sincronizador |

O status é derivado da última avaliação completa de uma inspeção não cancelada,
ordenada pelo ciclo da inspeção e pela data da avaliação. `repaired` preenche
`repaired_at`; qualquer condição posterior que mantenha a avaria ativa limpa essa
data. Uma avaria arquivada não é reativada automaticamente. O código atual não
oferece rota para arquivá-la; esse estado só pode existir em dados previamente
persistidos ou por integração administrativa externa à interface.

## Avaliação

Cada par avaria/inspeção aceita no máximo uma avaliação. Ela começa como `draft` e
pode ser publicada como `complete`.

Campos funcionais incluem:

- condição;
- localização textual, comentário e recomendação;
- justificativa e notas internas;
- item, referência de projeto e impacto na atividade;
- GUT e classificação derivada;
- um total de quantitativo, composto por um ou mais itens da mesma unidade;
- fotografias, versão do mapa e uma localização confirmável;
- snapshots da avaria, do GUT, da classificação e do quantitativo.

A escrita exige preparador atribuído e inspeção em `in_progress` ou
`in_correction`.

## Condições

| Valor | Uso |
|---|---|
| `new` | Situação padrão da primeira avaliação; não é permitida em avaliações posteriores |
| `reinspected` | Avaria observada novamente em uma reinspeção |
| `reclassified` | Avaria reclassificada tecnicamente |
| `canceled` | Avaliação cancelada com motivo |
| `canceled_sr` | Avaliação cancelada sem reparo, com motivo |
| `treated` | Tratamento executado; encerra a avaria atual |

Toda avaliação completa exige comentário. A primeira avaliação pode registrar
qualquer situação, inclusive para avarias já existentes em sistemas anteriores;
avaliações posteriores não aceitam `new`. `new`, `reinspected` e
`reclassified` exigem GUT, quantitativo, fotografias, mapa pronto e localização
confirmada. `treated` exige os mesmos itens de evidência, remove o GUT e a classificação atuais e altera a avaria para
`repaired`.

`canceled` e `canceled_sr` exigem motivo, removem o GUT e a classificação e
dispensam quantitativo e fotografias. Essas duas condições não alteram o status da
avaria. Uma avaliação publicada pode voltar a rascunho durante correção.
Substituir seu mapa ou remover a localização faz essa transição automaticamente.

## Fotos e quantitativo

Uma avaliação possui uma galeria ordenada por `position` e ID. A ordem pode ser
alterada somente enquanto a inspeção não for final. A publicação de uma condição
observável exige pelo menos duas fotos e todas as fotos anexadas precisam estar
prontas. O envio da inspeção à verificação repete essa validação de cobertura.

Cada avaliação possui um único total de quantitativo, composto por um ou mais itens.
`DefectAssessmentQuantity` representa um item e a própria avaliação é o agrupador.
Cada item guarda descrição opcional, posição, categoria, tipo de cálculo, entradas
estruturadas, valor unitário bruto, total bruto, unidade, modo manual ou calculado,
versão e snapshot da fórmula. Todos os itens seguem a unidade determinada pela
categoria. O navegador apresenta a prévia individual e o total, mas o backend sempre
recalcula as fórmulas e soma os itens com aritmética decimal. Resultados são exibidos
com duas casas sem arredondamento intermediário do valor persistido.

Em CIVIL (`CV`), cada item multiplica comprimento, altura e largura em metros para
obter o volume unitário; este é multiplicado pela quantidade positiva e
fracionária e a avaliação soma os itens em `m³`. TAC recebe um ou mais lançamentos
de área manual e os soma em `m²`. Cada item REC exige o elemento, calcula peso em
`kg` pelas fórmulas nativas e densidade fixa
de `7.850 kg/m³`; Ligação Parafusada, Telhas e Grade de Piso recebem peso total
manual. As fórmulas e seus campos estão no documento 09.

Ao publicar, um snapshot agregado guarda categoria, unidade, quantidade de itens,
total bruto e a lista integral dos itens com posição, descrição, entradas e fórmula.
Relatórios e históricos usam esse snapshot. Alterar dados em uma inspeção ainda
editável recria o snapshot atual; excluir o último item reabre a avaliação como
rascunho. Inspeções liberadas permanecem imutáveis.

## Relações entre avarias

Novas avarias podem ser relacionadas a outra avaria do mesmo equipamento:

- `split`: divisão;
- `recurrence`: recorrência;
- `related`: relacionada.

A relação aponta da avaria de origem para uma nova avaria, que recebe código e
avaliação próprios. Recorrência exige que a origem esteja reparada. O sistema
impede repetir o mesmo tipo de relação da origem dentro da mesma inspeção.

## Reinspeção

Ao criar uma inspeção após uma liberação, o sistema liga a nova inspeção à última
inspeção liberada do equipamento. A avaliação anterior de cada avaria é resolvida
percorrendo essa cadeia e ignorando inspeções canceladas.

Uma única lista de avarias atende inspeções iniciais e reinspeções. Ela inclui as
avarias criadas no ciclo atual e as avarias ativas originadas na cadeia de
inspeções anteriores. A rota histórica do checklist redireciona para essa lista.

Ao escolher **Avaliar** em uma avaria herdada, o sistema cria apenas uma nova
avaliação em rascunho, inicialmente como `reinspected`, ligada à última avaliação
publicada. Código, identidade e origem da avaria permanecem iguais; textos, GUT,
classificação, quantidade e fotos anteriores não são copiados. A versão pronta do
mapa e a geometria anterior são herdadas, mas a localização fica pendente de uma
nova confirmação explícita.

Cada avaria da lista precisa ter avaliação completa no ciclo corrente antes do
envio para verificação. A tela de avaliação é a mesma em todos os ciclos e mostra
a situação atual como primeiro bloco. Em avaliações herdadas, o cabeçalho resume
a última avaliação e um modal apresenta toda a cadeia histórica com fotos somente
para consulta.

Avarias tratadas deixam o filtro ativo, mas permanecem nos filtros de todas e
reparadas, no histórico e no relatório. Se o problema reaparecer, cria-se uma nova
avaria com relação `recurrence`, preservando o encerramento anterior.

## Histórico e relatório

Ao publicar, a avaliação captura um snapshot da avaria. GUT, classificação e
quantitativo também preservam a regra aplicada naquele momento. O relatório usa somente
avaliações publicadas, inclui um quadro com situação e classes anterior/atual e
ordena fotografias por categoria, sequência da avaria e galeria. Condições sem evidência
fotográfica recebem ficha textual. Pendências impedem exportar PDF ou DOCX.

Inspeções canceladas não determinam o status corrente da avaria. Dados já
persistidos continuam disponíveis para auditoria dentro do tenant.

## Cobertura automatizada

Os testes cobrem geração concorrente de código, primeira avaliação, condições,
publicação, relações, sincronização de status, lista unificada, cadeia de avaliações,
quantitativo, fotos, snapshots, autorização e isolamento multiempresa.
