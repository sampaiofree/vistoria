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
| 05 | `05-EQUIPAMENTOS-E-DOCUMENTOS.md` | Criar equipamentos, TAGs, desenhos e documentos | Concluída | Pendente | Pendente | Documentado |
| 06 | `06-INSPECOES-E-FLUXO.md` | Criar inspeções, responsáveis, estados e histórico | Concluída | Parcial e fora da sequência | Parcial | Em validação, com lacunas |
| 07 | `07-AVARIAS-E-REINSPECOES.md` | Modelar avarias permanentes e avaliações históricas | Concluída | Pendente | Pendente | Documentado |
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

## Próximo documento

Ainda não liberado. Em 30/07/2026 foram registradas as seguintes evidências:

- lacunas de schema e `public_id` do documento 03 corrigidas;
- migrations, rollback e seed idempotente executados em SQLite, MariaDB 12.2 e MySQL 8.4.11;
- suíte com 47 testes aprovados e 1 teste de concorrência do módulo 06 ignorado por depender dos módulos 05/06 completos, inclusive no MySQL 8.4.11;
- Pint e build do frontend executados com sucesso;
- smoke HTTP pelo Herd confirmou login, leitura do membro, escrita do administrador e bloqueio do superadministrador nas rotas de tenant;
- pipeline de CI preparado para MySQL 8 e Node 22.

Permanecem pendentes:

- validação visual e responsiva dos fluxos completos de autenticação, tenancy e estrutura operacional;
- registro do commit de conclusão da etapa;
- execução bem-sucedida do pipeline remoto após o envio do commit.

Somente após o registro dessas evidências o próximo documento será `05-EQUIPAMENTOS-E-DOCUMENTOS.md`.
