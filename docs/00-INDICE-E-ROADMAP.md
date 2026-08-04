# Projeto Vistoria — Índice e Roadmap do MVP

## Objetivo

Organizar o desenvolvimento do MVP em documentos sequenciais, mantendo uma única fonte de verdade para arquitetura, regras de negócio, implementação e validação.

## Escopo do MVP

O MVP será uma aplicação web responsiva em Laravel 13, Inertia e Vue, com MySQL, voltada inicialmente para inspeções da categoria CIVIL.

Inclui:

- arquitetura multiempresa;
- usuários e administrador interno por empresa;
- clientes, unidades, áreas e subáreas;
- equipamentos com histórico;
- inspeções e reinspeções;
- avarias permanentes com código único por empresa;
- avaliações da avaria por inspeção;
- fotos capturadas pelo celular;
- cálculo GUT e classificação CV;
- revisão, aprovação e liberação;
- relatório PDF simplificado;
- auditoria e isolamento de dados entre empresas.

Fora do primeiro MVP:

- TAC;
- REC;
- editor gráfico de desenhos técnicos;
- inteligência artificial;
- funcionamento offline;
- aplicativo nativo;
- cobrança automática do SaaS.

## Estados e dimensões de acompanhamento

O estado geral não substitui as três dimensões de acompanhamento:

- **Documentado**: decisões, escopo e critérios estão registrados, mas isso não comprova execução.
- **Implementado**: o código previsto foi localizado por conferência estática; ainda pode faltar validação.
- **Em validação**: há implementação para conferir, porém migrations, testes automatizados ou validação manual continuam pendentes.
- **Concluído**: documentação e implementação estão completas e todas as validações obrigatórias foram executadas com sucesso.

Nas colunas de dimensão, **Concluída** significa que aquela dimensão terminou, **Parcial** indica lacunas identificadas e **Pendente** significa que ainda não existe evidência suficiente. A presença de arquivos de teste não equivale à execução da suíte.

## Ordem dos documentos

| Ordem | Documento | Objetivo | Documentação | Implementação | Validação | Estado geral |
|---:|---|---|---|---|---|---|
| 00 | `00-INDICE-E-ROADMAP.md` | Controlar a ordem e o andamento do projeto | Concluída | Não se aplica | Parcial | Em validação |
| 01 | `01-VISAO-GERAL-E-ESCOPO.md` | Consolidar produto, usuários, limites e critérios do MVP | Concluída | Não aferida neste documento | Pendente | Documentado |
| 02 | `02-ARQUITETURA-E-PADROES.md` | Definir arquitetura Laravel, módulos, convenções e segurança | Concluída | Não aferida neste documento | Pendente | Documentado |
| 03 | `03-MODELAGEM-MULTIEMPRESA.md` | Criar organizações, usuários e isolamento por empresa | Concluída | Concluída na conferência estática | Parcial | Em validação |
| 04 | `04-CLIENTES-E-ESTRUTURA-OPERACIONAL.md` | Criar clientes, unidades, áreas e subáreas | Concluída | Concluída na conferência estática | Parcial | Em validação |
| 05 | `05-EQUIPAMENTOS-E-DOCUMENTOS.md` | Criar equipamentos, TAGs, desenhos e documentos | Concluída | Concluída | Concluída | Concluído |
| 06 | `06-INSPECOES-E-FLUXO.md` | Criar inspeções, responsáveis, estados e histórico | Concluída | Concluída | Concluída | Concluído |
| 06A | `06A-DASHBOARD-E-NAVEGACAO.md` | Criar dashboard operacional, shell e navegação principal | Concluída | Concluída | Concluída | Concluído |
| 06B | `06B-VIEW-FIRST-DEMO.md` | Entregar o fluxo visual completo e repetível para apresentação | Concluída | Concluída | Concluída | Em validação — commit pendente |
| 07 | `07-AVARIAS-E-REINSPECOES.md` | Modelar avarias permanentes e avaliações históricas | Concluída | Parcial | Parcial | Em validação |
| 08 | `08-FOTOS-E-ARMAZENAMENTO.md` | Definir captura, compressão, upload e armazenamento | Concluída | Pendente | Pendente | Documentado |
| 09 | `09-CLASSIFICACAO-CIVIL-GUT.md` | Implementar regras GUT, CV, danos e recomendações | Concluída | Pendente | Pendente | Documentado |
| 10 | `10-REVISAO-APROVACAO-E-AUDITORIA.md` | Implementar controle técnico e rastreabilidade | Pendente | Pendente | Pendente | Pendente |
| 11 | `11-RELATORIO-PDF.md` | Gerar o relatório simplificado do MVP | Pendente | Pendente | Pendente | Pendente |
| 12 | `12-TESTES-E-SEGURANCA.md` | Cobrir regras críticas, permissões e isolamento | Pendente | Pendente | Pendente | Pendente |
| 13 | `13-DEPLOY-HETZNER.md` | Preparar ambiente de produção, filas, backups e storage | Pendente | Pendente | Pendente | Pendente |
| 14 | `14-ROADMAP-POS-MVP.md` | Planejar TAC, REC, desenhos, IA e SaaS comercial | Pendente | Pendente | Pendente | Pendente |

## Estrutura obrigatória de cada documento

Cada documento de execução deve conter:

1. Objetivo.
2. Escopo incluído.
3. Escopo excluído.
4. Regras de negócio.
5. Modelagem de dados.
6. Endpoints ou ações.
7. Telas envolvidas.
8. Implementação em etapas.
9. Testes obrigatórios.
10. Critérios de aceite.
11. Riscos e brechas.
12. Checklist final.
13. Commit sugerido.

## Regra de andamento

Um documento só muda para **Concluído** quando:

- migrations executam sem erro;
- testes automatizados passam;
- validação manual foi realizada;
- critérios de aceite foram atendidos;
- alterações foram registradas em commit;
- o documento foi atualizado para refletir o código real.

## Regra de manutenção

A documentação deve refletir o sistema implementado. Quando uma regra mudar, o documento correspondente deve ser alterado no mesmo commit do código.

## Fechamento consolidado até 06A

Os módulos 05, 06 e 06A foram consolidados em 30/07/2026. O corte entrega:

- equipamentos, status, documentos privados e versionamento;
- inspeções, responsáveis, snapshots, referências, máquina de estados e histórico;
- dashboard Inertia/Vue, navegação principal, indicadores pessoais ou da empresa e atividades recentes;
- carregamento deferred independente com estado de erro e nova tentativa;
- drill-down pessoal coerente com usuário e responsabilidade;
- shell responsivo e acessível entre 375 e 1440 px.

Evidências do fechamento:

- `php artisan test`: 82 testes, 81 aprovados, 1 ignorado e 817 assertions;
- teste direcionado da dashboard: 4 testes e 242 assertions;
- `vendor/bin/pint --test` e `npm run build` aprovados;
- migration de índices da dashboard aplicada, revertida isoladamente e reaplicada no MySQL;
- `php artisan migrate:fresh --seed` aprovado com as 17 migrations;
- validação manual pelo Herd/Chrome com administrador, membro e superadministrador;
- dashboard conferida em 1440, 1280, 1024, 768 e 375 px, sem overflow horizontal do documento;
- regressão visual conferida nas páginas de inspeções, equipamentos e clientes.

## Corte demonstrativo 06B

O `06B-VIEW-FIRST-DEMO.md` foi aplicado em 04/08/2026 para preparar a apresentação local à Samarco. O corte entrega:

- fluxo `Login → Dashboard → Equipamento → Inspeção → Avarias → Avaliação CIVIL → Fotografias → Relatório`;
- hub de inspeção com seis abas e URLs próprias;
- avaliação dedicada com persistência dos campos reais e parâmetros demonstrativos somente leitura;
- cenário local idempotente com duas inspeções, sete avarias, documento privado e progresso `6/7`;
- placeholders fotográficos neutros, viewer acessível e prévia HTML imprimível;
- isolamento multiempresa e autorização nas novas rotas.

Evidências do corte:

- `composer validate --strict --no-check-publish`, `vendor/bin/pint --test` e `npm run build` aprovados;
- `php artisan test`: 105 testes, 104 aprovados, 1 ignorado e 1.479 assertions;
- seeder executado duas vezes sem duplicatas e cenário restaurado após teste de escrita;
- Chrome validado em 1440, 1280, 1024, 768 e 375 px, sem overflow horizontal ou erros no console;
- filtros, abas, viewer, teclado, persistência, impressão e bloqueio explicado do PDF conferidos.

O 06B permanece `Em validação` apenas porque o registro em commit ainda está pendente. Os módulos 08, 09 e 11 continuam documentados ou pendentes e não são considerados implementados por este corte.

## Próximo documento

O próximo passo do MVP continua sendo `07-AVARIAS-E-REINSPECOES.md`, substituindo progressivamente os dados demonstrativos pelos contratos definitivos sem refazer a experiência aprovada no 06B.
