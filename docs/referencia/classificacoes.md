# Classificações técnicas e quantitativos

O catálogo compartilhado por todas as empresas está em
`app/Services/Classification/NativeDefectCatalog.php`. As opções da interface
vêm dele; não há edição de matrizes por organização. A avaliação guarda as
opções escolhidas, a classificação calculada e snapshots usados pelo relatório.
Alterar o catálogo exige versionar o comportamento e seus testes.

## Categorias

| Categoria | Cálculo | Classes | Quantitativo |
|---|---|---|---|
| CIVIL (`CV`) | G × U × T | CV-1 a CV-5 | volume por item, m³ |
| TAC | G × U × T | TA-1 a TA-5 | área informada, m² |
| REC | G × U × T | IE-1 a IE-5 | peso calculado ou informado, kg |
| TEL | impacto × risco | TE-1 a TE-4 | não se aplica |
| Estruturas Solidárias (`ES`) | sem matriz automática | sem classe | não se aplica |

O inspetor seleciona a descrição técnica e o sistema determina as notas. Em CIVIL
e REC, `G` é o maior valor entre impacto na segurança e impacto no ativo. Em TAC,
`G` vai de 1 a 3; nas outras categorias GUT, G, U e T vão de 1 a 5. Em TEL,
impacto vem da altura e risco vem do tipo e condição do dano. As matrizes de
opções estão em [REC](rec.md), [CIVIL](civil.md) e [TEL](tel.md).

CIVIL, TAC e REC também permitem selecionar o método **Nota de Engenharia** na
avaliação. A marcação de condição insegura e as avaliações `ES` aparecem no
quadro de tratativas especiais do relatório; `ES` não exige mapa ou quantitativo.
Para publicar uma avaliação com evidência são exigidos comentário, recomendação,
ao menos duas fotos prontas e, exceto em `ES`, mapa pronto com número de projeto e
localização confirmada. CIVIL, TAC e REC exigem quantitativo; TEL e ES não.

## Faixas e recomendações

| Prioridade | CIVIL / REC | TAC | TEL |
|---|---|---|---|
| 1 | CV-1 / IE-1: 75–125, até 1 ano | TA-1: 45–75, até 1 ano | TE-1: 12–15, até 1 ano |
| 2 | CV-2 / IE-2: 36–74, até 2 anos | TA-2: 25–44, até 3 anos | TE-2: 9–11, até 2 anos |
| 3 | CV-3 / IE-3: 16–35, até 3 anos | TA-3: 15–24, até 5 anos | TE-3: 6–8, até 3 anos |
| 4 | CV-4 / IE-4: 8–15, oportunidade | TA-4: 9–14, oportunidade | TE-4: 3–5, oportunidade |
| 5 | CV-5 / IE-5: 1–7, registro | TA-5: 3–8, registro | — |

`AssessmentTreatmentDueDate` calcula data de vencimento a partir da data da
inspeção para CV-1 a CV-3, IE-1 a IE-3 e TA-1 a TA-3. Em reinspeções seletivas,
o resumo preserva a data da avaliação herdada; um grupo com prazos distintos
mostra o menor vencimento. TEL contém recomendação textual no catálogo, mas não
entra nesse cálculo de data.

A planilha técnica prevê IE-0, CV-0 e TE-0 para risco grave e iminente. No código
atual, essas linhas são **marcadores vazios no relatório** (`report_rows`), não
classes retornadas pelos resolvedores GUT/TEL. A sinalização de condição insegura
é um campo próprio da avaliação. TAC não possui marcador zero.

## TAC

A gravidade TAC vem da classe A/B/C/D do ativo; a urgência, da atmosfera
C2/C3/C4/C5/CX; a tendência, do grau ASTM D610. A área é informada manualmente
por item. Consulte `NativeDefectCatalog` e `GutClassificationResolver` para os
códigos aceitos e as notas exatas de cada opção.

## Persistência e limites

- Uma avaliação publicada (`complete`) pode ser movida a rascunho e republicada
  enquanto a inspeção estiver editável. A publicação anterior da mesma avaliação
  não permanece como revisão independente.
- Itens usam aritmética decimal; o resumo agregado converte a soma a `float`
  antes de entregá-la ao frontend. Valores de `quantity.value` e `total.value`
  não são um contrato de decimal exato.
- O resumo enumera códigos do catálogo atual. Uma classe retirada do catálogo
  não ganha linha própria só por constar de um snapshot antigo.
- Os prazos normativos não significam que o sistema abra ou atualize uma Nota M2
  no SAP; o número M2 é informado no Vistoria.

Fonte técnica das matrizes: `T000000-S-2PO006 — Procedimento de Inspeção de
Estruturas e Priorização de Avarias — Rev. 04` e planilhas de quantitativos
correspondentes. As fórmulas executadas estão em
`app/Services/Defects/NativeDefectQuantityCalculator.php`.
