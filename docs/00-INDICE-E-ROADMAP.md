# Projeto Vistoria — índice e estado verificado

## Como ler esta pasta

Esta pasta documenta a árvore de trabalho atual do Vistoria. Código, migrations,
rotas, policies e testes são a fonte de verdade para comportamento entregue. Uma
regra técnica, uma pauta ou um plano pendente nunca deve ser lido como capacidade
implementada apenas por estar descrito aqui.

Última reconciliação: **20/09/2026**.

## Visão rápida

O Vistoria é uma aplicação web multiempresa para planejar, executar, revisar,
aprovar e liberar inspeções técnicas. O estado atual inclui:

- administração global, organizações, usuários e identidade visual por tenant;
- um cliente operacional por organização e equipamentos vinculados a ele;
- equipamentos com atributos textuais de área e subárea e
  campos de manutenção;
- inspeções iniciais e reinspeções, responsáveis, histórico de estados, revisão
  do relatório e aspectos gerais;
- avarias permanentes, avaliações, quantitativos por itens, fotos e mapa
  versionado por avaria;
- catálogo técnico nativo de CIVIL, TAC, REC e TEL;
- resumo de classificação, vínculos de Nota M2 e prévia A4 no navegador;
- notificações para falhas de imagem e Horizon protegido.

## Índice

| Documento | Conteúdo |
|---|---|
| [01 — Visão geral e escopo](01-VISAO-GERAL-E-ESCOPO.md) | Produto, papéis e limites atuais |
| [02 — Arquitetura e padrões](02-ARQUITETURA-E-PADROES.md) | Stack, camadas, segurança e filas |
| [03 — Modelagem multiempresa](03-MODELAGEM-MULTIEMPRESA.md) | Organizações, usuários e tenant |
| [04 — Clientes](04-CLIENTES-E-ESTRUTURA-OPERACIONAL.md) | Cliente único por organização |
| [05 — Equipamentos e documentos](05-EQUIPAMENTOS-E-DOCUMENTOS.md) | Cadastro, manutenção e arquivos privados |
| [06 — Inspeções e fluxo](06-INSPECOES-E-FLUXO.md) | Fluxo, responsabilidades e relatório |
| [07 — Avarias e reinspeções](07-AVARIAS-E-REINSPECOES.md) | Avaliações, quantitativos e histórico |
| [08 — Fotos e armazenamento](08-FOTOS-E-ARMAZENAMENTO.md) | Fotos privadas e processamento assíncrono |
| [08A — Mapas e localização](08A-MAPAS-E-LOCALIZACAO-DA-INSPECAO.md) | Mapa versionado e geometria por avaliação |
| [09 — Classificações e quantitativos](09-CLASSIFICACOES-TECNICAS-E-QUANTITATIVOS.md) | Regras transversais e limites atuais |
| [10 — REC](10-CLASSIFICACAO-REC-E-QUANTITATIVOS.md) | Matriz técnica e quantitativos REC |
| [11 — CIVIL](11-CLASSIFICACAO-CIVIL-E-QUANTITATIVOS.md) | Matriz técnica e quantitativos CIVIL |
| [12 — TEL](12-CLASSIFICACAO-TELHADO-TAPAMENTO.md) | Matriz TEL implementada |
| [13 — Resumo e Nota M2](13-RESUMO-CLASSIFICACAO-EQUIPAMENTO-E-NOTA-M2.md) | Contrato atual e regras de negócio abertas |
| [14 — Deploy](14-DEPLOY-HETZNER.md) | Checklist derivado do repositório |
| [14A — Produção](14A-PASSO-A-PASSO-DEPLOY-PRODUCAO.md) | Runbook de referência |
| [15 — Padrão visual](15-PADRAO-VISUAL-E-LAYOUT-DO-RELATORIO.md) | Referência visual e itens não entregues |
| [16 — Pauta SEND](16-PAUTA-SEND-QUANTITATIVOS-REC.md) | Perguntas de negócio pendentes |
| [17 — Pendências verificadas](17-AJUSTES-FINAIS-DOCUMENTOS-09-A-13.md) | Lacunas conhecidas dos documentos 09 a 13 |

## Limites verificados

- Não há API pública, operação offline, aplicativo nativo, integração SAP ou
  cobrança SaaS.
- PDF e DOCX são gerados no navegador a partir da prévia; nenhum arquivo oficial
  é persistido no servidor e a exportação não muda o status da inspeção.
- O catálogo técnico é nativo e compartilhado; não há configuração por tenant.
- Uma avaliação `complete` ainda pode ser modificada enquanto a inspeção está em
  estado editável. A revisão append-only é pendência, não comportamento atual.
- O resumo usa o catálogo nativo atual para formar suas linhas; dados históricos
  removidos do catálogo não são preservados como linhas independentes.
- O scheduler registra apenas o snapshot do Horizon; não há limpeza periódica de
  arquivos.

## Evidência de qualidade desta reconciliação

Em 20/09/2026, `npm run test:js` passou com 27 testes. `php artisan test --compact`
teve 353 testes aprovados, 1 falho e 4 ignorados; a falha observada foi
`ClientCrudTest::test_client_navigation_is_nested_in_administrator_settings_only`.
Essa fotografia não é uma propriedade permanente do projeto nem é corrigida por
esta atualização documental.

## Regra de manutenção

Atualize o documento de domínio no mesmo conjunto de mudanças de qualquer alteração
funcional. Antes de concluir, confira os contratos de rotas e requests, permissões,
migrations e links relativos. Marque propostas como tal, em vez de antecipar na
documentação um comportamento ainda não entregue.
