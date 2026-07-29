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

## Ordem dos documentos

| Ordem | Documento | Objetivo | Status |
|---:|---|---|---|
| 00 | `00-INDICE-E-ROADMAP.md` | Controlar a ordem e o andamento do projeto | Em andamento |
| 01 | `01-VISAO-GERAL-E-ESCOPO.md` | Consolidar produto, usuários, limites e critérios do MVP | Pendente |
| 02 | `02-ARQUITETURA-E-PADROES.md` | Definir arquitetura Laravel, módulos, convenções e segurança | Pendente |
| 03 | `03-MODELAGEM-MULTIEMPRESA.md` | Criar organizações, usuários e isolamento por empresa | Pendente |
| 04 | `04-CLIENTES-E-ESTRUTURA-OPERACIONAL.md` | Criar clientes, unidades, áreas e subáreas | Pendente |
| 05 | `05-EQUIPAMENTOS-E-DOCUMENTOS.md` | Criar equipamentos, TAGs, desenhos e documentos | Pendente |
| 06 | `06-INSPECOES-E-FLUXO.md` | Criar inspeções, responsáveis, estados e histórico | Pendente |
| 07 | `07-AVARIAS-E-REINSPECOES.md` | Modelar avarias permanentes e avaliações históricas | Pendente |
| 08 | `08-FOTOS-E-ARMAZENAMENTO.md` | Definir captura, compressão, upload e armazenamento | Pendente |
| 09 | `09-CLASSIFICACAO-CIVIL-GUT.md` | Implementar regras GUT, CV, danos e recomendações | Pendente |
| 10 | `10-REVISAO-APROVACAO-E-AUDITORIA.md` | Implementar controle técnico e rastreabilidade | Pendente |
| 11 | `11-RELATORIO-PDF.md` | Gerar o relatório simplificado do MVP | Pendente |
| 12 | `12-TESTES-E-SEGURANCA.md` | Cobrir regras críticas, permissões e isolamento | Pendente |
| 13 | `13-DEPLOY-HETZNER.md` | Preparar ambiente de produção, filas, backups e storage | Pendente |
| 14 | `14-ROADMAP-POS-MVP.md` | Planejar TAC, REC, desenhos, IA e SaaS comercial | Pendente |

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

`01-VISAO-GERAL-E-ESCOPO.md`
