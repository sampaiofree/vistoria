# 06 — Dashboard, inspeções e fluxo operacional

| Status | Usuário responsável pela etapa | Campos de datas |
|---|---|---|
| Planejada | Planejador; Inspetor inicia a execução | `planned_start_on`, `planned_end_on` |
| Em inspeção | Inspetor | `inspected_on`: dia do início, preenchido automaticamente |
| Aguardando revisão | Revisor inicia a revisão | `field_completed_at`: primeira conclusão da inspeção |
| Em revisão | Revisor | Sem campo exclusivo; transição registrada no histórico |
| Em correção | Inspetor | Sem campo exclusivo; transição registrada no histórico |
| Aguardando liberação | Liberador | `approved_at`: aprovação pelo Revisor |
| Liberada | Liberador | `released_at` |
| Cancelada | Administrador ou responsável atribuído | `canceled_at` |

Esta tabela descreve o fluxo operacional implementado.

As datas resumidas representam marcos operacionais, não necessariamente a última
entrada no status. `inspected_on` será a única data operacional de início,
guardando apenas o dia em que o Inspetor clicar em **Iniciar inspeção**.
`field_completed_at` preservará a primeira conclusão, mesmo após correções ou
reenvios. A aprovação pelo Revisor preencherá `approved_at`, entrando em
**Aguardando liberação**.

O histórico registra origem, destino, usuário, motivo e data/hora de cada mudança
em `inspection_status_histories.created_at`. Assim, início de revisão, correções
e reenvios continuam rastreáveis sem campo exclusivo e sem substituir a primeira
conclusão. `report_date` permanece como a data oficial do relatório; sua regra de
preenchimento será definida posteriormente.

## Fluxo operacional aprovado

O caminho principal será:

**Planejada → Em inspeção → Aguardando revisão → Em revisão → Aguardando liberação → Liberada.**

Os retornos aprovados são:

- **Em revisão → Em correção → Aguardando revisão**: o Revisor solicita a
  correção; o Inspetor corrige e reenvia para uma nova revisão;
- **Aguardando liberação → Aguardando revisão**: o Liberador devolve para nova
  revisão, informando a justificativa.

| Origem | Ação | Usuário | Destino |
|---|---|---|---|
| Planejada | Iniciar inspeção | Inspetor | Em inspeção |
| Em inspeção | Concluir inspeção | Inspetor | Aguardando revisão |
| Aguardando revisão | Iniciar revisão | Revisor | Em revisão |
| Em revisão | Aprovar | Revisor | Aguardando liberação |
| Em revisão | Enviar para correção | Revisor | Em correção |
| Em correção | Reenviar para revisão | Inspetor | Aguardando revisão |
| Aguardando liberação | Liberar | Liberador | Liberada |
| Aguardando liberação | Devolver para revisão | Liberador | Aguardando revisão |
| Qualquer etapa aberta | Cancelar, com justificativa | Administrador ou responsável atribuído | Cancelada |

**Em correção** somente poderá seguir para **Aguardando revisão**, além da
exceção de cancelamento. **Aguardando liberação** somente poderá seguir para
**Aguardando revisão** ou **Liberada**, além dessa mesma exceção.

O cancelamento está disponível em qualquer etapa aberta. Em **Em inspeção**,
somente o Inspetor vinculado pode cancelar, inclusive quando também for
administrador. Nas demais etapas abertas, o cancelamento é permitido ao
Administrador ou a qualquer responsável vinculado. **Liberada** e **Cancelada**
são finais.

O fluxo alvo não terá os estados **Aguardando aprovação**, **Aprovada** ou
**Relatório gerado**. A aprovação ocorrerá na revisão e levará diretamente para
**Aguardando liberação**. Gerar ou exportar um arquivo não será um status.

## Regras de atuação aprovadas

Para executar uma etapa, o usuário deverá estar ativo, pertencer à organização,
possuir o papel operacional correto e estar vinculado a qualquer
responsabilidade naquela inspeção. Ser administrador, por si só, não substituirá
esses requisitos nas ações operacionais; o cancelamento manterá a possibilidade
de atuação administrativa.

O Planejador cuidará do planejamento. O Inspetor iniciará, executará e concluirá
a inspeção; poderá editar o conteúdo de campo somente em **Em inspeção** e
**Em correção**. Ao clicar em **Concluir inspeção**, perderá a edição já em
**Aguardando revisão**, sem precisar esperar o Revisor iniciar a análise.

O Revisor tem as ações **Iniciar revisão**, **Aprovar** e **Enviar para correção**.
O Liberador atua em **Aguardando liberação**, podendo liberar ou devolver para
revisão. Nenhuma dessas ações operacionais é concedida apenas pela conta
administrativa: o papel operacional e o vínculo continuam obrigatórios.

## Dashboard

A dashboard possui dois modos:

- **global**: superadministrador recebe acesso à administração de empresas e não
  vê indicadores operacionais;
- **operacional**: usuários de uma organização veem inspeções, prioridades,
  atividades recentes e uma inspeção em destaque.

Para administradores, os indicadores abrangem a empresa. Para membros, as
consultas são filtradas pelas responsabilidades do usuário. Prioridades distinguem
planejadas atrasadas, aguardando revisão, em correção e aguardando liberação.
Uma inspeção planejada só é considerada atrasada após seu prazo final.

Os blocos de contagem, inspeções, fluxo e atividades usam propriedades deferred
independentes do Inertia e podem ser recarregados separadamente. A inspeção em
destaque prioriza uma reinspeção em andamento quando houver.

Contagens, rótulos, atalhos, próximos passos e atividades refletem os estados
atuais. Atrasos de inspeções planejadas são calculados pelo prazo final,
`planned_end_on`.

## Criação da inspeção

Somente um usuário ativo com papel operacional **Planejador** cria inspeções. O
cadastro é feito em lote: o Planejador adiciona uma ou mais linhas, revisa uma
prévia editável e confirma todas em uma única transação. A criação:

1. bloqueia a linha do equipamento;
2. confirma que equipamento e cliente estão ativos;
3. impede mais de uma inspeção aberta por equipamento;
4. localiza a última inspeção liberada do equipamento;
5. define `initial` quando não há anterior ou `reinspection` quando há;
6. cria número no formato `INS-AAAA-NNNNNN`;
7. captura o snapshot da organização, do cliente e do equipamento;
8. registra o estado inicial no histórico;
9. cria dois blocos vazios para a vista geral do relatório;
10. atribui o criador como Preparador e o Inspetor selecionado como
    Verificador;
11. define `emission_type` como `C — Para conhecimento`.

Cada linha informa Item de manutenção/equipamento, ordem de serviço, data inicial
planejada, prazo final e Inspetor. O equipamento pode ser pesquisado por Item de
manutenção, TAG ou descrição; a lista de Inspetores contém somente usuários
ativos com esse papel operacional. O prazo final não pode ser anterior à data
inicial. A inspeção continua sendo inicial ou reinspeção conforme a última
inspeção liberada do equipamento.

## Papéis operacionais e responsabilidades

Cada inspeção possui no máximo um usuário em cada responsabilidade técnica. O
papel operacional do usuário e a responsabilidade da inspeção são conceitos
distintos, mas o mapeamento oficial é:

| Papel operacional | Responsabilidade na inspeção | Atribuição no cadastro em lote |
|---|---|---|
| Planejador | Preparador (`preparer`) | O Planejador que cria o lote, atribuído automaticamente |
| Inspetor | Verificador (`reviewer`) | Usuários ativos com papel Inspetor; um é selecionado em cada linha |
| Revisor | Aprovador (`approver`) | Não selecionado no lote; a tela Equipe já permite atribuição manual |
| Liberador | Liberador (`releaser`) | Não selecionado no lote; a tela Equipe já permite atribuição manual |

Ao definir um responsável pela tela Equipe, a atribuição existente daquela
função é substituída automaticamente. O cadastro em lote aplica o mesmo
mapeamento acima. O campo técnico legado `is_primary` permanece verdadeiro no
único vínculo para manter a compatibilidade dos resumos existentes.

Uma pessoa ainda pode receber mais de uma responsabilidade técnica. Isso não
significará permissão para atuar em várias etapas no fluxo aprovado: cada usuário
possui um único papel operacional, e papel e vínculo deverão ser compatíveis com
a ação. O vínculo único de cada função é usado no resumo da listagem, mas não
será uma exigência adicional para executar a etapa.

## Estados e transições implementados

Os únicos estados disponíveis são `planned`, `in_progress`, `awaiting_review`,
`in_review`, `in_correction`, `awaiting_release`, `released` e `canceled`.
Os estados legados `awaiting_approval`, `approved` e `report_generated` não
fazem parte do sistema. O início grava `started_at` e preenche `inspected_on` se
necessário; a primeira conclusão grava `field_completed_at`, preservado em
reenvios; a aprovação grava `approved_at`; e a liberação grava `released_at`.

## Cobertura na conclusão e no reenvio

O envio para revisão executa os validadores de avaliações e fotografias. Essas validações são requisitos
para o Inspetor concluir a
inspeção ou reenviá-la após uma correção:

- toda avaria pertencente ao escopo da inspeção, nova ou herdada, precisa ter
  avaliação completa no ciclo corrente;
- avaliações com evidência obrigatória já precisam de quantitativo e pelo menos
  duas fotos prontas para serem publicadas; o envio repete a validação das fotos.

Não há obrigatoriedade de mapas por categoria. Regras de GUT e conclusão da
avaliação são aplicadas antes desses validadores.

## Tela e navegação contextual

Atualmente, a inspeção possui páginas próprias para:

- visão geral;
- vista geral fotográfica do relatório;
- avarias;
- localização;
- equipe;
- fotografias;
- documentos;
- histórico;
- relatório.

As abas preservam o contexto da inspeção e expõem apenas ações autorizadas. A
listagem usa abas de status, começa em **Todas** e ordena pela data inicial
planejada mais próxima, deixando datas não definidas ao final.

A tabela já mostra **Planejador**, **Inspetor**, **Revisor** e **Liberador**,
usando o vínculo único de cada responsabilidade; ausências aparecem como `—`.
A coluna Cliente / Unidade não é exibida.

## Conteúdo técnico e metadados

O Inspetor vinculado é o único que pode editar o conteúdo de campo em **Em
inspeção** e **Em correção**: avarias, avaliações, quantitativos, fotos, mapas,
marcadores e vista geral. Em **Em inspeção**, ele também é o único autorizado a
alterar dados da inspeção e a transicionar. Os metadados Tipo de emissão, OS,
Data do relatório e Número do relatório externo continuam somente leitura para
o Inspetor. Administradores mantêm os metadados e referências nos estágios em
que essas edições são permitidas pelas políticas.

## Prévia, PDF e DOCX

A página de relatório monta capa, sumário, aspectos gerais, vista geral, mapas e
documentação fotográfica em páginas A4. O navegador espera fontes, imagens e
paginação estabilizarem antes de habilitar impressão ou exportação.

Para exportar, são exigidos:

- número externo do relatório;
- número do Projetista I;
- quatro fotos prontas na vista geral;
- comentário e recomendação nos dois blocos;
- nenhuma foto publicada sem numeração derivada dos mapas.

Outros avisos, como avaliações pendentes, responsáveis principais ausentes ou
fotos publicadas ainda processando, aparecem na validação da prévia, mas não são
todos usados pelo mesmo bloqueio cliente-side de exportação.

PDF e DOCX são gerados no navegador como páginas rasterizadas. A prévia e a
exportação não alteram o status, não persistem o arquivo e não preenchem
automaticamente `report_date`.

## Decisões ainda em aberto

- Definir quais campos o Revisor poderá editar em **Em revisão**;
- Definir a regra de preenchimento de `report_date`;
- Adequar a seleção manual da tela Equipe para limitar cada responsabilidade ao
  papel operacional correspondente.

## Limites atuais

- não há reabertura de inspeção liberada ou cancelada;
- não há assinatura digital nem armazenamento backend do relatório exportado;
- o read model operacional ainda herda uma classe com nome histórico
  `ViewFirstDemoPresenter`, mas não existe modo demo nem dado provisório paralelo;
- a prévia contém textos e estruturas específicos do modelo técnico atual, mesmo
  com taxonomia configurável.

## Cobertura automatizada

Os testes atuais cobrem criação concorrente, snapshots, responsáveis, rotas, transições,
pré-condições, histórico, reinspeção, vista geral, metadados, navegação, paginação e
composição do relatório.

Os testes cobrem os novos estados, atuação por papel e vínculo, bloqueios de
requisições diretas, correção e reenvio, devolução para revisão e cancelamento
em etapas abertas.
