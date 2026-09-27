# 06 — Inspeções e fluxo operacional

## Estados e transições

| Estado | Ação seguinte normal | Papel operacional |
|---|---|---|
| `planned` | Iniciar inspeção | Inspetor |
| `in_progress` | Enviar ao planejador | Inspetor |
| `awaiting_m2` | Conferir notas e enviar à revisão ou devolver ao inspetor | Planejador |
| `awaiting_review` | Iniciar revisão | Revisor |
| `in_review` | Aprovar ou devolver para correção | Revisor |
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

Membros ativos com papel Revisor ou Liberador possuem as filas **Minhas inspeções**
(padrão, com seus vínculos atuais) e **Disponíveis para mim** (inspeções abertas da
mesma empresa, com vaga em sua função, das mais antigas para as mais novas). Podem
consultar a inspeção e seu relatório antes de assumir; ações de revisão, liberação
e cancelamento exigem o vínculo específico. Não há consulta geral da empresa para
esses membros. A dashboard mantém os indicadores pessoais e oferece um atalho para
a fila disponível.

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
A atribuição manual de Revisor ou Liberador também exige um membro ativo com o papel
operacional correspondente, validado no servidor e filtrado no formulário.

## Conteúdo de campo

Durante `in_progress` e `in_correction`, o Inspetor responsável pode manter
avarias, avaliações, fotos, mapas, quantitativos, revisão de relatório, aspectos
gerais. A aba Classificação/M2 é somente leitura para o Inspetor. Em outros estados, essas permissões variam por recurso e
Policy; as transições críticas não dependem de esconder botões no frontend.

Em `in_review`, essas mesmas edições cabem exclusivamente ao Revisor responsável.
Administradores e Liberadores não editam conteúdo do relatório em nenhum estado;
em `awaiting_release` o Liberador apenas registra apontamentos, devolve para
revisão ou libera.

## Solicitações de ajuste

As solicitações reutilizam um único histórico com três fluxos: Planejador →
Inspetor, Revisor → Inspetor e Liberador → Revisor. O Liberador pode apontar uma avaria ou registrar
uma mensagem geral e devolver a inspeção; o Revisor responde diretamente ou
desdobra o apontamento para o Inspetor. Esse filho fica vinculado ao apontamento
do Liberador e precisa ser validado e encerrado pelo Revisor antes da resposta ao
Liberador. Cada solicitante encerra individualmente os ajustes atendidos antes de
avançar sua etapa.

Toda saída do Inspetor, inclusive após correções, passa por `awaiting_m2`, mesmo
sem notas aplicáveis. O Planejador vinculado como `preparer` edita o cabeçalho,
M2 e tratativas especiais; não edita avarias. Pode salvar parcialmente, mas deve
preencher M2 de cada grupo elegível e Serviço, Prioridade e Nota de cada tratativa
especial antes de encaminhar à revisão. Criação e envio do Inspetor não exigem
essas notas. O Revisor pode ajustá-las, e aprovação/liberação repetem as validações.

O Inspetor responde a todos os apontamentos enviados antes de reenviar ao
Planejador. Este encerra apenas seu próprio fluxo; respostas ao Revisor seguem
para conferência do Revisor. Cada encaminhamento exige responsável ativo e
habilitado no destino. O administrador pode substituir responsáveis em estados
abertos, sem ganhar permissão de edição do conteúdo.

As alterações da aba são registradas em `inspection_status_histories`, com
origem igual ao destino e `metadata.event = classification_updated`, valores
anteriores/novos, autor e horário. Esses eventos não contam como entrada em etapa.
Edições HTTP e transições compartilham o bloqueio da inspeção e reavaliam o estado.
Inspeções existentes mantêm sua etapa; ao retornar ao Inspetor, seguem o novo fluxo.

O relatório possui metadados, data, tipo de emissão, referências de documentos,
dois blocos de vista geral e templates de aspectos gerais por organização. A revisão
`report_revision` é um número reservado por equipamento e organização, editável
apenas pelo Inspetor responsável nos estados de campo. Ela substitui as antigas
revisões autônomas de equipamento.

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
- regras de apresentação completas do relatório são referência no documento 15,
  não garantia de que cada detalhe visual já esteja automatizado;
- o resumo de classificação e a Nota M2 possuem limitações explicitadas nos
  documentos 13 e 17.
