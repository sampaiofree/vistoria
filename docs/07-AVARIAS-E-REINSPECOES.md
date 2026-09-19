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
- um quantitativo principal e unidade;
- fotografias e marcações de mapa;
- snapshots da avaria, do GUT e da classificação.

A escrita exige preparador atribuído e inspeção em `in_progress` ou
`in_correction`.

## Condições

| Valor | Uso |
|---|---|
| `new` | Somente na primeira avaliação da avaria |
| `unchanged` | Condição mantida |
| `worsened` | Condição agravada |
| `improved` | Condição melhorada sem reparo completo |
| `repaired` | Encerra a avaria atual |
| `not_located` | Não localizada; exige justificativa |
| `not_inspected` | Não foi possível inspecionar; exige justificativa |

Toda avaliação completa exige comentário. Condições `not_located` e
`not_inspected` exigem justificativa, removem GUT/classificação e não podem manter
marcações. `repaired` também remove a classificação atual. As quatro condições
ativas exigem as três notas GUT nativas, de 1 a 5, salvas antes da publicação.

As condições observáveis — `new`, `unchanged`, `worsened`, `improved` e
`repaired` — também exigem, já na publicação, um quantitativo principal e pelo
menos duas fotografias, todas com processamento concluído. `not_located` e
`not_inspected` não exigem quantitativo nem evidência fotográfica.

## Fotos e quantitativo

Uma avaliação possui uma galeria ordenada por `position` e ID. A ordem pode ser
alterada somente enquanto a inspeção não for final. A publicação de uma condição
observável exige pelo menos duas fotos e todas as fotos anexadas precisam estar
prontas. O envio da inspeção à verificação repete essa validação de cobertura.

Há no máximo um quantitativo principal por avaliação, obrigatório para publicar
uma condição observável. Apenas CIVIL (`CV`) recebe comprimento, altura e largura
em metros, além da quantidade, que admite frações. Os quatro campos devem ser
positivos, com até quatro casas decimais. O formulário mostra `M³ UNI. = comprimento
× altura × largura` e `M³ TOTAL = M³ UNI. × quantidade`. O backend recalcula ambos
com aritmética decimal e salva o total como quantitativo em `m3`, sem arredondar
os produtos intermediários. O modelo existente `DefectAssessmentQuantity`
armazena esse conjunto por avaliação, mantendo a restrição de unicidade.

TAC e REC continuam recebendo valor e unidade: unidade, metro, metro quadrado,
metro cúbico, milímetro, centímetro, quilograma, litro ou outra. Alterar quantitativo de uma avaliação completa normalmente a devolve a
rascunho; quando já existem marcações, ela permanece publicada para preservar a
consistência do mapa e atualiza a data da avaliação.

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
avaliação em rascunho, inicialmente como `unchanged`, ligada à última avaliação
publicada. Código, identidade e origem da avaria permanecem iguais; textos, GUT,
classificação, quantidade e fotos anteriores não são copiados.

Cada avaria da lista precisa ter avaliação completa no ciclo corrente antes do
envio para verificação. A tela de avaliação é a mesma em todos os ciclos e mostra
a situação atual como primeiro bloco. Em avaliações herdadas, o cabeçalho resume
a última avaliação e um modal apresenta toda a cadeia histórica com fotos somente
para consulta.

Avarias reparadas deixam o filtro ativo, mas permanecem nos filtros de todas e
reparadas, no histórico e no relatório. Se o problema reaparecer, cria-se uma nova
avaria com relação `recurrence`, preservando o encerramento anterior.

## Histórico e relatório

Ao publicar, a avaliação captura um snapshot da avaria. GUT e classificação
também preservam a regra aplicada naquele momento. O relatório usa somente
avaliações publicadas, inclui um quadro com situação e classes anterior/atual e
ordena fotografias conforme os mapas e a galeria. Condições sem evidência
fotográfica recebem ficha textual. Pendências impedem exportar PDF ou DOCX.

Inspeções canceladas não determinam o status corrente da avaria. Dados já
persistidos continuam disponíveis para auditoria dentro do tenant.

## Cobertura automatizada

Os testes cobrem geração concorrente de código, primeira avaliação, condições,
publicação, relações, sincronização de status, lista unificada, cadeia de avaliações,
quantitativo, fotos, snapshots, autorização e isolamento multiempresa.
