# Projeto Vistoria — 06A Dashboard e Navegação

## 1. Objetivo

Criar a primeira central operacional do sistema, substituindo o `dashboard` estático por uma página Inertia/Vue com shell navegacional, cards prioritários, tabela operacional, resumo de fluxo e atividades recentes.

## 2. Escopo incluído

- migração da rota `/dashboard` para controller Inertia;
- shell compartilhado com sidebar escura, topbar e header de página;
- navegação principal visível apenas com rotas reais;
- cards de prioridade com links reais para a listagem de inspeções;
- tabela "Minhas inspeções" com estado de loading, vazio e lista operacional;
- resumo de fluxo por etapa;
- atividades recentes;
- estados globais para superadmin sem organização operacional;
- contrato de dados para a dashboard via backend;
- filtros pessoais coerentes entre contadores e listagem;
- carregamento deferred independente, com erro e tentativa novamente por bloco;
- drawer com foco contido, retorno de foco e bypass para o conteúdo;
- testes de contrato, acesso, isolamento e timezone.

## 3. Escopo excluído

- busca global com endpoint real;
- notificações com backend;
- perfil do usuário com rota dedicada;
- relatórios e menus administrativos inexistentes no MVP;
- gráficos analíticos;
- personalização de widgets;
- mobile completo com drawer avançado além do necessário para o corte.

## 4. Regras de negócio

- o dashboard operacional depende de organização ativa;
- superadministrador recebe uma visão global neutra, sem módulos operacionais;
- a tabela principal mostra apenas inspeções com relação operacional ao usuário;
- o card "Atrasadas" considera apenas inspeções `planned` com `scheduled_for` anterior ao dia atual da organização;
- os cards de prioridade usam filtros reais da listagem de inspeções;
- para membros, o destino dos cards preserva usuário e responsabilidade usados na contagem;
- o backend resolve permissões e dados; o front apenas renderiza o que recebeu;
- a próxima ação é um link para a inspeção e seu rótulo respeita as Policies do usuário;
- inspeções `released` e `canceled` não aparecem na lista de itens que exigem atenção;
- o resumo de fluxo pode ser company-wide para administrador da empresa e pessoal para usuários operacionais;
- atividades recentes devem respeitar organização e relevância do usuário.

## 5. Modelagem de dados

### Contrato da dashboard

- `mode`
- `organization`
- `can`
- `links`
- `priority_counts`
- `my_inspections`
- `workflow_summary`
- `recent_activities`

### Campos por inspeção

- `public_id`
- `number`
- `inspection_type`
- `inspection_type_label`
- `status`
- `status_label`
- `created_at`
- `client`
- `unit`
- `equipment`
- `user_responsibilities`
- `schedule`
- `next_action`

### Campos por atividade

- `id`
- `description`
- `time_label`
- `status`
- `inspection`
- `actor`

### Índices de apoio

A migration `2026_07_30_000016_add_dashboard_query_indexes.php` adiciona:

- `inspections (organization_id, status, scheduled_for)`;
- `inspection_responsibles (organization_id, user_id, responsibility, inspection_id)`;
- `inspection_status_histories (organization_id, created_at)`.

## 6. Endpoints e ações

- `GET /dashboard`
- `GET /inspections`, incluindo `responsible` e `responsibility` nos drill-downs pessoais
- `GET /inspections/create`
- `GET /inspections/{inspection}`
- `GET /equipments`
- `GET /clients`
- logout via `POST /logout`

## 7. Telas envolvidas

- dashboard principal;
- listagem de inspeções;
- páginas já existentes do shell reutilizado;
- estados vazios e de carregamento dos blocos do dashboard.

## 8. Implementação em etapas

1. trocar a rota `dashboard` para controller Inertia;
2. compartilhar navegação e URLs reais no middleware Inertia;
3. refatorar `AppLayout` para sidebar/topbar sem quebrar as páginas atuais;
4. criar ícones locais e o header de página;
5. entregar os quatro cards prioritários;
6. entregar a tabela "Minhas inspeções";
7. entregar o resumo do fluxo;
8. entregar atividades recentes;
9. separar os grupos deferred e tratar falhas recuperáveis;
10. alinhar contadores pessoais e filtros da listagem;
11. adicionar testes de contrato, acesso, isolamento, autorização e timezone;
12. revisar responsividade, teclado, estados vazios e páginas legadas.

## 9. Testes obrigatórios

- [x] acesso autenticado ao `/dashboard`;
- [x] bloqueio por usuário inativo e organização suspensa/inativa;
- [x] dashboard operacional para company admin com dados reais;
- [x] dashboard pessoal para membro;
- [x] dashboard global para superadmin;
- [x] isolamento de dados entre organizações;
- [x] contagem e drill-down usando o mesmo usuário e responsabilidade;
- [x] próxima ação coerente com a autorização;
- [x] grupos deferred independentes;
- [x] timezone da organização para prazo;
- [x] estados finais ausentes da lista de atenção;
- [x] build do front sem erro;
- [x] regressão visual de páginas existentes usando `AppLayout`.

## 10. Critérios de aceite

- [x] a sidebar aparece com apenas rotas reais;
- [x] a topbar exibe organização, busca visual indisponível, notificações e menu do usuário;
- [x] sidebar recolhida não apresenta clipping ou overflow;
- [x] drawer móvel contém o foco, fecha com `Escape` e devolve o foco ao gatilho;
- [x] existe link para pular diretamente ao conteúdo;
- [x] os cards prioritários usam dados do backend;
- [x] o clique de um card pessoal preserva o mesmo escopo de sua contagem;
- [x] a tabela "Minhas inspeções" mostra somente itens abertos e relevantes;
- [x] o resumo do fluxo informa se o escopo é da empresa ou pessoal;
- [x] cada bloco carrega e pode falhar independentemente;
- [x] o superadmin não entra em módulos operacionais sem organização;
- [x] não há overflow horizontal da página entre 375 e 1440 px;
- [x] o dashboard compila e os testes da faixa passam.

## 11. Riscos e brechas remanescentes

- a busca global permanece desabilitada até existir endpoint tenant-scoped;
- notificações ainda não têm backend;
- o menu de perfil permanece indisponível até existir rota dedicada;
- tabelas densas usam rolagem horizontal interna em telas estreitas;
- métricas analíticas, gráficos e polling continuam fora desta fatia;
- novas páginas densas devem reutilizar o modo de largura adequado do shell.

## 12. Evidências de validação — 30/07/2026

### Automatizada

- `php artisan test`: **82 testes**, **81 aprovados**, **1 ignorado**, **817 assertions**;
- `tests/Feature/Dashboard/DashboardPagesTest.php`: **4 testes**, **242 assertions**;
- `vendor/bin/pint --test`: aprovado após ajuste de formatação;
- `npm run build`: aprovado com Vite 8;
- migration `000016`: aplicada, revertida isoladamente e reaplicada no MySQL sem erro;
- `php artisan migrate:fresh --seed`: aprovado no MySQL com as 17 migrations e o seeder local.

### Manual pelo Herd/Chrome

- perfis validados: administrador da empresa, membro e superadministrador;
- viewports da dashboard: 1440, 1280, 1024, 768 e 375 px;
- drawer validado em 375 px;
- páginas de regressão: inspeções em 1440 px, equipamentos em 1024 px e clientes em 768 px;
- nenhuma exceção no navegador;
- nenhum overflow horizontal do documento nas larguras conferidas;
- navegação global do superadministrador limitada ao dashboard;
- membro sem atribuições recebeu estados vazios e resumo pessoal;
- administrador recebeu indicadores da empresa e CTA autorizado.

## 13. Checklist final

- [x] rota `/dashboard` migrada para Inertia;
- [x] middleware de navegação compartilhada;
- [x] shell lateral e topbar;
- [x] cards de prioridade com links coerentes;
- [x] tabela operacional;
- [x] resumo de fluxo;
- [x] atividades recentes;
- [x] grupos deferred independentes com retry;
- [x] filtros pessoais e isolamento;
- [x] acessibilidade básica do shell e drawer;
- [x] superadmin com landing neutra;
- [x] testes da fatia;
- [x] validação visual e responsiva;
- [x] migration e rollback;
- [x] Pint;
- [x] build do front;
- [x] roadmap atualizado;
- [x] corte preparado para commit.

## 14. Commit sugerido

`feat: complete equipment inspection and dashboard modules`

O corte foi consolidado com os módulos 05 e 06 porque rotas, seeder, hierarquia e layout são arquivos compartilhados e ainda não existiam commits intermediários íntegros.

## 15. Próximo documento

`07-AVARIAS-E-REINSPECOES.md`
