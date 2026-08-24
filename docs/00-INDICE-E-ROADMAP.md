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
- mapas de localização por inspeção, categoria e avaliação;
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
| 06B | `06B-VIEW-FIRST-DEMO.md` | Entregar o fluxo visual completo e repetível para apresentação | Concluída | Concluída — dados demonstrativos no fluxo oficial | Parcial | Em validação — validação manual pendente |
| 07 | `07-AVARIAS-E-REINSPECOES.md` | Modelar avarias permanentes e avaliações históricas | Concluída | Concluída | Concluída | Concluído |
| 07A | `07A-CATEGORIAS-E-CLASSIFICACOES.md` | Tornar categorias e classificações configuráveis por organização | Concluída | Concluída | Parcial | Em validação — validação manual pendente |
| 08 | `08-FOTOS-E-ARMAZENAMENTO.md` | Definir captura, compressão, upload e armazenamento | Concluída | Concluída | Parcial | Em validação final |
| 08A | `08A-MAPAS-E-LOCALIZACAO-DA-INSPECAO.md` | Modelar mapas, marcações e localização fotográfica | Concluída | Concluída — Fatias 1 a 7 | Parcial | Em validação — gates ambientais e manuais pendentes |
| 09 | `09-CLASSIFICACAO-CIVIL-GUT.md` | Implementar regras opcionais GUT/CV após o catálogo | Concluída | Parcial — núcleo, formulário e quantitativos concluídos | Parcial | Em validação — procedimento e templates pendentes |
| 10 | `10-REVISAO-APROVACAO-E-AUDITORIA.md` | Implementar controle técnico e rastreabilidade | Pendente | Pendente | Pendente | Pendente |
| 11 | `11-RELATORIO-PDF.md` | Gerar o relatório simplificado do MVP | Pendente | Pendente | Pendente | Pendente |
| 12 | `12-TESTES-E-SEGURANCA.md` | Cobrir regras críticas, permissões e isolamento | Pendente | Pendente | Pendente | Pendente |
| 13 | `13-DEPLOY-HETZNER.md` | Preparar ambiente de produção, filas, backups e storage | Concluída | Concluída — Horizon e proteção de imagens | Parcial | Em validação — provisionamento do VPS pendente |
| 13A | `13A-PASSO-A-PASSO-DEPLOY-PRODUCAO.md` | Orientar o responsável pelo primeiro deploy e pelas atualizações | Concluída | Não se aplica | Parcial | Em validação — execução no VPS pendente |
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

O 06B foi consolidado em commits próprios e permanece como experiência visual aprovada. O módulo 07 foi concluído com relações entre avarias, avaliações históricas, checklist de reinspeção, cobertura obrigatória e bloqueio de envio incompleto. O módulo 08 está em validação final, com fluxo privado de fotografias e cobertura automatizada implementados. A análise do relatório real originou o módulo 08A, que separa mapa da inspeção, marcação da avaliação, fotografia e folha do relatório.

## Corte de implementação 08A — Fatias 1 a 7

As sete fatias de `08A-PLANO-DE-IMPLEMENTACAO.md` foram implementadas em 09/08/2026. O corte entrega:

- mapas privados por inspeção e categoria, com origem documental ou upload;
- processamento da imagem-base, editor SVG e geometria versionada;
- marcações vinculadas às avaliações e às fotografias selecionadas;
- cópia controlada para reinspeções e numeração fotográfica global;
- cobertura configurável antes da revisão, com ativação auditada por categoria;
- compositor neutro e snapshot estável usados pela prévia do relatório;
- cenário View First com duas folhas CIVIL e quatorze marcações idempotentes;
- hardening de tenant, assets privados, caminhos, limites, concorrência, Jobs e limpeza;
- thumbnail sob demanda, payloads limitados e alternativa textual/teclado no editor.

Evidências automatizadas do corte:

- `php artisan test`: 176 testes, 175 aprovados, 1 ignorado e 1.932 assertions;
- testes focados de localização, classificação, seeder, View First e avarias: 91 testes e 1.047 assertions;
- instalação com seed e rollback/reapply 08A aprovados em SQLite isolado;
- processamento real de PNG, JPEG, WEBP e PDF aprovado;
- `composer validate --strict --no-check-publish`, `vendor/bin/pint --test`, `npm run build` com Node 22.21.1 e `git diff --check` aprovados;
- seeder demonstrativo executado duas vezes nos testes sem duplicar mapas ou marcações;
- categorias existentes e novas permanecem com `requires_location_map = false` até ativação explícita.

Permanecem pendentes: migration/rollback em MySQL isolado, worker assíncrono real, validação manual responsiva, impressão, decisão de ativação CIVIL e commits.

## Oficialização da configuração de demonstração

Em 10/08/2026, o cenário View First passou a utilizar o mesmo fluxo operacional das demais organizações:

- organização identificada por `is_demo`, sem propagação de valores demonstrativos para outros tenants;
- perfil GUT/CV ativo somente no tenant de demonstração e perfis comuns preservados em rascunho;
- contexto, GUT/CV e snapshots gravados nas avaliações;
- quantitativos persistidos e consolidados separadamente por unidade;
- 36 fotos privadas vinculadas às avaliações e às 14 marcações dos mapas;
- requisito de mapa ativado apenas para a categoria CIVIL do tenant demonstrativo;
- formulário oficial com edição de contexto, classificação, quantitativos, textos e fotografias;
- estados vazios reais quando uma organização comum ainda não cadastrou informação técnica;
- prévia derivada dos dados persistidos, mantendo o PDF oficial desabilitado até o módulo 11.

Os gates automatizados de código, suíte e build foram executados: 179 testes, 178 aprovados, 1 ignorado e 1.962 assertions; Pint, Composer, build Vite e `git diff --check` aprovados. A migration foi aplicada e o seeder foi executado duas vezes no SQLite local sem duplicação. Permanecem a validação manual responsiva/impressa, os gates MySQL/worker já registrados no corte 08A e o commit.

## Próximo documento

O próximo passo é concluir os gates ambientais e manuais descritos na Fatia 7 de `08A-PLANO-DE-IMPLEMENTACAO.md`, incluindo a conferência do cenário demo oficializado. Depois dessas evidências, o fluxo pode avançar para os módulos 10 e 11 sem reconstruir a localização a partir de textos soltos ou dados demonstrativos paralelos.
