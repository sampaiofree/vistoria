# 06 — Inspeções e fluxo operacional

## Estados e transições

| Estado | Ação seguinte normal | Papel operacional |
|---|---|---|
| `planned` | Iniciar inspeção | Inspetor |
| `in_progress` | Enviar para revisão | Inspetor |
| `awaiting_review` | Iniciar revisão | Revisor |
| `in_review` | Aprovar ou devolver para correção | Revisor |
| `in_correction` | Reenviar para revisão | Inspetor |
| `awaiting_release` | Liberar ou devolver para revisão | Liberador |
| `released` | Final | — |
| `canceled` | Final | — |

O cancelamento exige uma inspeção aberta. Em `in_progress`, somente o Inspetor
responsável pode cancelar; nos demais estados abertos, administrador da empresa ou
responsável atribuído pode fazê-lo. Toda transição gera histórico com origem,
destino, usuário, motivo e horário.

## Planejamento e responsabilidades

Um Planejador ativo cria inspeções em lote para equipamentos aptos, definindo ordem
de serviço, janela planejada e Inspetor. O sistema bloqueia a criação de mais de uma
inspeção aberta por equipamento, determina se é inicial ou reinspeção, cria o
número `INS-AAAA-NNNNNN`, registra o snapshot de contexto e os dois blocos da vista
geral.

As responsabilidades técnicas são `preparer` (Preparador), `reviewer`
(Verificador), `approver` (Aprovador) e `releaser` (Liberador). Há no máximo um
responsável por função. Elas não substituem o papel operacional: para uma ação de
fluxo, o usuário deve estar ativo, no tenant, ter o papel correto e estar atribuído
à inspeção.

## Conteúdo de campo

Durante `in_progress` e `in_correction`, o Inspetor responsável pode manter
avarias, avaliações, fotos, mapas, quantitativos, revisão de relatório, aspectos
gerais e vínculos M2. Em outros estados, essas permissões variam por recurso e
Policy; as transições críticas não dependem de esconder botões no frontend.

Em `in_review`, essas mesmas edições cabem exclusivamente ao Revisor responsável.
Administradores e Liberadores não editam conteúdo do relatório em nenhum estado;
em `awaiting_release` o Liberador apenas registra apontamentos, devolve para
revisão ou libera.

## Solicitações de ajuste

As solicitações reutilizam um único histórico com dois fluxos: Revisor →
Inspetor e Liberador → Revisor. O Liberador pode apontar uma avaria ou registrar
uma mensagem geral e devolver a inspeção; o Revisor responde diretamente ou
desdobra o apontamento para o Inspetor. Esse filho fica vinculado ao apontamento
do Liberador e precisa ser validado e encerrado pelo Revisor antes da resposta ao
Liberador. Cada solicitante encerra individualmente os ajustes atendidos antes de
avançar sua etapa.

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
