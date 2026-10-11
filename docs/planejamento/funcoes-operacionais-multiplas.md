# Funções operacionais múltiplas

**Estado:** planejamento para implementação futura; o sistema ainda usa um único
papel operacional por usuário.

**Decisões registradas em:** 09/10/2026.

**Atualização:** 11/10/2026 — edição de contas de outros administradores.

## Objetivo e decisões

Permitir que um administrador da empresa atribua de uma a quatro funções
operacionais aos usuários internos que pode editar e a si mesmo. As funções
são Planejador, Inspetor, Revisor e Liberador. O tipo de conta
(`company_admin` ou `member`) continua independente das funções. O
superadministrador escolhe uma ou mais funções para o primeiro administrador ao
criar a empresa. Contas `client` e `super_admin` não recebem funções
operacionais.

A habilitação no cadastro não atribui automaticamente ninguém a uma inspeção.
As ações sobre uma inspeção existente exigem a função apropriada **e** o vínculo
específico, além de usuário ativo, organização correta e estado adequado. Para
criar uma inspeção, o Planejador precisa da função; o vínculo `preparer` é
criado junto com a inspeção. Uma pessoa pode ocupar duas, três ou as quatro
funções na mesma inspeção, desde que seja atribuída a cada uma. Não haverá
bloqueio de revisão ou liberação do próprio trabalho. O histórico deve
continuar registrando o usuário que realizou cada ação.

| Função no usuário | Responsabilidade na inspeção | Autoatribuição |
|---|---|---|
| Planejador (`planner`) | `preparer` | Não |
| Inspetor (`inspector`) | `reviewer` | Não |
| Revisor (`reviewer`) | `approver` | Sim |
| Liberador (`releaser`) | `releaser` | Sim |

Permanece o limite de **um responsável por responsabilidade em cada inspeção**.
Um administrador atribuído como Planejador ou Inspetor pode executar as mesmas
ações operacionais de um membro nessa função. Sua capacidade administrativa de
gerenciar a empresa e substituir responsáveis permanece separada: ela não
dispensa o vínculo exigido para criar conteúdo, revisar, liberar ou cancelar.

## Cadastro, dados e transição

- Trocar o campo único `users.operational_role` por uma relação de funções do
  usuário, com unicidade por usuário e função e consultas eficientes por função.
  Migrar os valores válidos existentes sem criar funções adicionais. Depois que
  todas as leituras e gravações usarem a relação, remover o campo antigo.
- Exigir de uma a quatro funções para criar ou atualizar `member` e
  `company_admin`. Rejeitar funções repetidas ou fora das quatro opções.
  Proibir funções para `client` e `super_admin`. Manter o isolamento por
  organização nas consultas e alterações.
- Antes de ativar a nova exigência, identificar contas internas existentes sem
  função e regularizá-las por decisão de quem puder editar cada conta. Não
  escolher uma função automaticamente. Verificar também vínculos em inspeções
  abertas cujo usuário esteja inativo ou não tenha a função exigida; corrigir esses vínculos
  antes da ativação. A migração deve informar o que impede a ativação sem
  alterar silenciosamente as atribuições.
- No formulário global de criação de empresa, exigir que o superadministrador
  selecione de uma a quatro funções para o primeiro administrador. Na gestão de
  usuários da empresa, substituir o seletor único por seleção múltipla em
  criação e edição, mostrar todas as funções na lista e fazer o filtro por
  função encontrar qualquer usuário que a possua. O administrador pode editar
  as próprias funções, mantendo a restrição atual à mudança do próprio tipo de
  conta.
- O administrador da empresa continua podendo **criar outra conta
  `company_admin`**, escolhendo suas funções na criação. Depois disso, não pode
  alterar a conta de outro administrador da empresa: cadastro, funções, tipo de
  acesso, status e redefinição de senha ficam indisponíveis para ele. Cada
  administrador continua podendo editar a própria conta conforme as regras já
  descritas. A restrição deve ser aplicada nas autorizações do servidor, também
  para acesso direto às rotas, e refletida na interface. Esta decisão não cria
  uma nova rotina de gestão de administradores pelo superadministrador.
- Ao retirar uma função de um usuário, bloquear a gravação enquanto ele ocupar
  a responsabilidade correspondente em qualquer inspeção aberta (`released` e
  `canceled` ficam fora dessa verificação). Informar ao administrador quais
  responsabilidades precisam de substituição. Aplicar o mesmo bloqueio ao
  converter uma conta interna para `client`, pois a conversão retiraria todas
  as funções. Executar a verificação e a gravação de forma atômica para evitar
  uma remoção concorrente à atribuição. Inspeções finalizadas preservam seus
  vínculos e histórico.

## Fluxo das inspeções

Substituir comparações com um único `operational_role` por uma verificação de
pertencimento ao conjunto de funções. Revisar em conjunto Policies, Actions,
Form Requests, filtros de usuários, consultas de destinatários de transições,
dashboard e componentes de atribuição. A validação do servidor deve usar a
mesma regra que filtra as opções na interface.

As atribuições manuais de **todas** as responsabilidades devem aceitar apenas
usuário interno, ativo, da mesma organização e habilitado na função
correspondente. Atribuir uma pessoa a outra função da mesma inspeção é
permitido. A substituição administrativa continua ocupando somente a função
escolhida. A transição para outra etapa exige que o responsável do destino
continue ativo e habilitado, inclusive quando for administrador.

Remover as restrições que exigem especificamente `member` para o trabalho do
Planejador em `awaiting_m2` e do Inspetor em `in_progress` ou `in_correction`,
incluindo edição de conteúdo, classificação, solicitações de ajuste e
validação dos destinatários. A autorização operacional continuará condicionada
à função e ao vínculo; `company_admin` sozinho não concede acesso à etapa.

### Autoatribuição de Revisor e Liberador

Mostrar ao usuário ativo as vagas de `approver` e `releaser` para as quais ele
tenha a função correspondente. Quem tiver as duas funções poderá selecionar
uma ou ambas, inclusive na mesma inspeção. Planejador e Inspetor continuarão
sem autoatribuição. A fila **Disponíveis para mim**, o painel e a página da
inspeção devem apresentar as opções por responsabilidade, sem escolher uma
função com base em uma ordem fixa ou somente no estado atual.

No `POST /inspections/{inspection}/self-assign`, a sessão continua definindo o
usuário. O pedido pode conter um conjunto não vazio de responsabilidades
selecionadas, limitado a `approver` e `releaser`; o servidor confirma que o
usuário possui cada função. Para preservar o comportamento dos usuários com
uma única função, um pedido sem seleção explícita assume essa única função
elegível. Se o usuário tiver as duas, a seleção é obrigatória. Não aceitar
`user_id` enviado pelo navegador.

Bloquear a inspeção e verificar todas as vagas selecionadas na mesma transação.
Se qualquer uma tiver sido ocupada por outra pessoa, não criar nenhum vínculo
nem evento e devolver uma mensagem para atualizar a escolha. Uma vaga já
ocupada pelo próprio usuário conta como atendida; não alterar suas datas nem
duplicar seu histórico. Criar vínculos e eventos de histórico apenas para as
vagas novas, sem mudar o estado da inspeção. A restrição única de
`inspection_id` e `responsibility` continua protegendo contra concorrência.

## Relatório e histórico

Os nomes documentais de Verificador, Revisor e Liberador continuam vindo da
configuração da empresa, com o comportamento de snapshot já usado na
finalização. Não são substituídos pelos nomes das contas atribuídas. O nome de
“Preparado” continua seguindo a regra atual do relatório. A mudança de
funções não reescreve relatórios, atribuições nem eventos históricos.

## Roteiro de implementação e aceite

1. Inventariar contas internas sem função e vínculos abertos incompatíveis;
   regularizá-los antes da ativação, sem atribuição presumida. Acrescentar a
   relação de funções e migrar cada papel existente.
2. Atualizar cadastro e criação global, centralizar a verificação de função e
   adaptar as regras de atribuição, autorização e transição. Só retirar o campo
   antigo após migrar seus consumidores.
3. Adaptar filas, painel e autoatribuição múltipla com operação atômica.
   Atualizar os documentos de produto e arquitetura quando o comportamento
   estiver implementado; este documento permanece identificado como plano até
   lá.

Verificar com testes de migração e de ponta a ponta:

- contas antigas com papel preservado, contas internas sem função detectadas,
  clientes e superadministradores sem funções;
- criação do primeiro administrador e gestão de usuários com uma, várias ou
  quatro funções, incluindo edição da própria conta e filtro por função;
- criação de outro administrador pela empresa permitida; edição, alteração de
  status e redefinição de senha de outro administrador bloqueadas, inclusive
  por rota direta;
- isolamento entre empresas, conta inativa, função ausente, vínculo ausente e
  estado incompatível em rotas diretas;
- administrador como Planejador e Inspetor, mesma pessoa em todas as etapas e
  uma única pessoa por responsabilidade;
- retirada de função e conversão para `client` bloqueadas por vínculos abertos,
  liberadas após substituição, sem alterar inspeções finalizadas;
- autoatribuição de uma ou duas vagas, repetição pelo próprio usuário e
  disputa concorrente com resultado integral ou nenhum vínculo novo;
- preservação dos nomes documentais, snapshots e eventos do histórico.

O repositório já pode conter alterações em andamento em arquivos do fluxo de
inspeções. A implementação futura deve partir do estado então vigente e
preservar essas alterações.
