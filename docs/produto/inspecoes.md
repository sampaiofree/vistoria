# Inspeções e fluxo operacional

## Estados e transições

| Estado | Ação seguinte normal | Papel operacional |
|---|---|---|
| `planned` | Iniciar inspeção | Inspetor |
| `in_progress` | Enviar ao planejador | Inspetor |
| `awaiting_m2` | Conferir notas e enviar à revisão ou devolver ao inspetor | Planejador |
| `awaiting_review` | Iniciar revisão | Revisor |
| `in_review` | Aprovar ou devolver ao Inspetor/Planejador para correção | Revisor |
| `in_correction` | Reenviar ao planejador | Inspetor |
| `awaiting_release` | Liberar ou devolver para revisão | Liberador |
| `released` | Final | — |
| `canceled` | Final | — |

Somente o Liberador operacional ativo, vinculado como `releaser`, pode cancelar
uma inspeção, em qualquer etapa aberta. A justificativa é obrigatória, com 10 a
5.000 caracteres. Administradores não possuem exceção para essa operação. Toda
transição gera histórico com origem, destino, usuário, motivo e horário.

## Planejamento e responsabilidades

Um Planejador ativo cria inspeções em lote para equipamentos aptos, definindo ordem
de serviço, janela planejada e Inspetor. O sistema bloqueia a criação de mais de uma
inspeção aberta por equipamento, determina se é inicial ou reinspeção, cria o
número `INS-AAAA-NNNNNN`, registra o snapshot de contexto e os dois blocos da vista
geral.

As responsabilidades técnicas são `preparer` (Planejador), `reviewer`
(Inspetor), `approver` (Revisor) e `releaser` (Liberador). Há no máximo um
responsável por função. Elas não substituem o papel operacional: para uma ação de
fluxo, o usuário deve estar ativo, no tenant, ter o papel correto e estar atribuído
à inspeção.

## Autoatribuição de Revisor e Liberador

Membros e administradores ativos com papel Revisor ou Liberador podem assumir
inspeções abertas da mesma empresa com vaga na função, das mais antigas para as
mais novas. Membros possuem as filas **Minhas inspeções** (seus vínculos) e
**Disponíveis para mim**; administradores mantêm a visão de **Todas as inspeções**
e também recebem a fila **Disponíveis para mim**. Podem consultar a inspeção e
seu relatório antes de assumir; ações de revisão, liberação e cancelamento exigem
o vínculo específico. A dashboard oferece um atalho para a fila disponível.

`POST /inspections/{inspection}/self-assign` deriva usuário e função da sessão,
rejeitando parâmetros para escolher outro usuário ou papel. A operação bloqueia a
inspeção em transação e preenche somente a vaga correspondente (`approver` para
Revisor, `releaser` para Liberador), mesmo com a outra função ocupada. Não muda a
etapa. Repetições pelo mesmo usuário preservam datas e histórico; se outra pessoa
assumiu, o solicitante retorna à fila com uma mensagem explicativa.

Cada autoatribuição registra autor, função e horário em `inspection_status_histories`,
com origem igual ao destino e `metadata.event = responsibility_self_assigned`.
O registro permanece após substituições administrativas. Responsáveis inativos ou
com papel incompatível continuam ocupando a função até correção administrativa.
A atribuição manual de Revisor ou Liberador aceita membro ou administrador ativo
com o papel operacional correspondente, validado no servidor e filtrado no
formulário. A atribuição manual mantém a substituição do responsável existente.

## Conteúdo de campo

Durante `in_progress` e `in_correction`, o Inspetor responsável pode manter
avarias, avaliações, fotos, mapas, quantitativos, revisão de relatório, aspectos
gerais. A aba Classificação/M2 é somente leitura para o Inspetor. Em outros
estados, essas permissões variam por recurso e Policy; as transições críticas
não dependem de esconder botões no frontend.

Em `in_review`, essas mesmas edições cabem exclusivamente ao Revisor responsável.
Administradores vinculados como Revisor possuem as mesmas permissões de edição
do Revisor membro em `in_review`. Liberadores não editam conteúdo do relatório;
em `awaiting_release` registram apontamentos, devolvem para revisão ou liberam.

## Solicitações de ajuste

As solicitações reutilizam um único histórico com quatro fluxos: Planejador →
Inspetor, Revisor → Inspetor, Revisor → Planejador e Liberador → Revisor. O Revisor
pode escolher o Planejador apenas para uma mensagem geral, sem apontamentos de
avarias marcados para o Inspetor. Nesse caso, a inspeção volta diretamente a
`awaiting_m2`; o Planejador responde antes de reenviá-la ao Revisor, que confere
e encerra a solicitação antes da aprovação. As permissões de edição do Planejador
continuam restritas ao cabeçalho, M2 e tratativas especiais.

O Liberador pode apontar uma avaria ou registrar uma mensagem geral e devolver
a inspeção; o Revisor responde diretamente ou desdobra o apontamento para o
Inspetor. Esse filho fica vinculado ao apontamento
do Liberador e precisa ser validado e encerrado pelo Revisor antes da resposta ao
Liberador. Cada solicitante encerra individualmente os ajustes atendidos antes de
avançar sua etapa.

Toda saída do Inspetor, inclusive após correções, passa por `awaiting_m2`, mesmo
sem notas aplicáveis. O Planejador membro vinculado como `preparer` edita o cabeçalho,
M2 e tratativas especiais; não edita avarias. Pode salvar parcialmente, mas deve
preencher M2 de cada grupo elegível e Serviço, Prioridade e Nota de cada tratativa
especial antes de encaminhar à revisão. Criação e envio do Inspetor não exigem
essas notas. O Revisor pode ajustá-las, e aprovação/liberação repetem as validações.

O Inspetor responde a todos os apontamentos enviados antes de reenviar ao
Planejador. Este encerra apenas seu próprio fluxo; respostas ao Revisor seguem
para conferência do Revisor. Cada encaminhamento exige responsável ativo e
habilitado no destino. O administrador pode substituir responsáveis em estados
abertos; a substituição, por si só, não concede permissão de edição do conteúdo.

As alterações da aba são registradas em `inspection_status_histories`, com
origem igual ao destino e `metadata.event = classification_updated`, valores
anteriores/novos, autor e horário. Esses eventos não contam como entrada em etapa.
Edições HTTP e transições compartilham o bloqueio da inspeção e reavaliam o estado.
Inspeções existentes mantêm sua etapa; ao retornar ao Inspetor, seguem o novo fluxo.

## Dados do relatório

O relatório possui metadados, data, tipo de emissão, referências de documentos,
dois blocos de vista geral e templates de aspectos gerais por organização. A revisão
`report_revision` é um número reservado por equipamento e organização, editável
pelo Inspetor responsável nos estados de campo ou pelo Revisor responsável em
`in_review`. Ela substitui as antigas revisões autônomas de equipamento.

O campo **Aspectos gerais do equipamento** da inspeção permite imagens e tabelas
simples no mesmo documento. Os modelos por organização também aceitam imagens e
tabelas, com campos dinâmicos e trechos manuais em vermelho nas células. Ao
aplicar um modelo, cada imagem é copiada para a inspeção; alterar ou excluir o
modelo depois não modifica o documento da inspeção.

## Responsáveis exibidos no relatório

Em **Configurações → Relatório de Inspeção → Responsáveis do relatório**, o
administrador define os nomes documentais de Verificador (`reviewer`, coluna Verif.),
Revisor (`approver`, coluna Aprov.) e Liberador (`releaser`, coluna Liber.). São
três campos opcionais por empresa, com até 150 caracteres, sem vínculo com contas
ou permissões operacionais. “Preparado” (`preparer`, coluna Prep.) mostra o nome
do Inspetor vinculado à respectiva inspeção; sem Inspetor, fica vazio. O vínculo
operacional do Planejador não define esse nome no relatório.

Inspeções abertas usam os nomes atuais da empresa. A liberação ou o cancelamento
grava `report_responsibles_snapshot` na mesma transação da finalização, inclusive
quando os nomes estão vazios. Capa e quadro de revisões usam essa mesma origem,
tanto na prévia quanto nas exportações PDF e DOCX. Nomes documentais vazios
aparecem como “Não definido” na capa e “—” nas iniciais, sem bloquear o fluxo.

A migração preserva, para inspeções já finalizadas, os nomes apresentados pelas
atribuições anteriores no campo “Verificado”, sem alterar os nomes preservados de
Revisor e Liberador nem os timestamps. Alterar o cadastro da empresa, renomear
usuários ou substituir responsáveis não altera os nomes documentais preservados.
As atribuições e os registros dos autores das ações continuam seguindo as regras
operacionais existentes.

## Dashboard, prévia e exportação

A dashboard global é exclusiva do superadministrador. A dashboard operacional
mostra prioridades, atividade e inspeções do tenant, filtradas pelas
responsabilidades para membros.

A prévia combina o read model do servidor com paginação A4 no navegador. PDF e DOCX
são gerados localmente a partir da prévia, não persistem arquivo e não alteram
status, datas ou fluxo. A exportação depende das validações de conteúdo exibidas na
interface; ela não é uma transição de inspeção.

## Limites atuais

- a liberação não persiste um artefato oficial de relatório;
- `report_generated_at` não deve ser interpretado como prova de exportação oficial;
- o layout e as exportações atuais estão descritos em [Relatórios](relatorios.md);
- o resumo de classificação e a Nota M2 possuem as limitações descritas em
  [Classificação e Nota M2](classificacao-e-m2.md).
