# Projeto Vistoria — índice e estado atual

## Propósito desta documentação

Esta pasta descreve o comportamento existente da aplicação Vistoria. O código da
branch atual é a fonte de verdade; propostas antigas, comandos de scaffolding e
listas de implementação não fazem parte da documentação viva.

Última reconciliação: **19/09/2026**.

## Visão rápida

O Vistoria é uma aplicação web multiempresa para planejar, executar, revisar,
aprovar e liberar inspeções técnicas. O sistema atual inclui:

- administração global de empresas e administradores iniciais;
- usuários, identidade visual e configurações por organização;
- clientes, unidades, áreas, subáreas e equipamentos;
- documentos e histórico de revisões de equipamentos;
- inspeções iniciais e reinspeções com responsáveis e histórico de estados;
- avarias permanentes e avaliações históricas;
- catálogo técnico nativo de categorias, classificações e critérios GUT;
- fotografias privadas processadas de forma assíncrona;
- mapa versionado e uma localização por avaliação de avaria;
- prévia paginada em A4 e exportação no navegador para PDF e DOCX;
- dashboard, notificações e painel protegido do Horizon.

## Índice

| Documento | Conteúdo |
|---|---|
| [01 — Visão geral e escopo](01-VISAO-GERAL-E-ESCOPO.md) | Produto, perfis, fluxo principal e limites atuais |
| [02 — Arquitetura e padrões](02-ARQUITETURA-E-PADROES.md) | Stack, camadas, segurança, arquivos, filas e testes |
| [03 — Modelagem multiempresa](03-MODELAGEM-MULTIEMPRESA.md) | Organizações, usuários, tenant, configurações e notificações |
| [04 — Clientes e estrutura operacional](04-CLIENTES-E-ESTRUTURA-OPERACIONAL.md) | Cliente, unidade, área e subárea |
| [05 — Equipamentos e documentos](05-EQUIPAMENTOS-E-DOCUMENTOS.md) | Equipamentos, documentos e revisões |
| [06 — Inspeções e fluxo](06-INSPECOES-E-FLUXO.md) | Dashboard, inspeções, estados, responsáveis e relatório |
| [07 — Avarias e reinspeções](07-AVARIAS-E-REINSPECOES.md) | Avarias, avaliações, relações e cobertura histórica |
| [08 — Fotos e armazenamento](08-FOTOS-E-ARMAZENAMENTO.md) | Upload, processamento, acesso e falhas |
| [08A — Mapas e localização](08A-MAPAS-E-LOCALIZACAO-DA-INSPECAO.md) | Mapa por avaria, versões, localização, cobertura e relatório |
| [09 — Classificação GUT e Quantitativos](09-CLASSIFICACAO-GUT-E-QUANTITATIVOS.md) | Catálogo técnico nativo, cálculo GUT e quantitativos por categoria |
| [13 — Deploy](13-DEPLOY-HETZNER.md) | Requisitos e checklist de produção |
| [13A — Passo a passo de produção](13A-PASSO-A-PASSO-DEPLOY-PRODUCAO.md) | Runbook do primeiro deploy e atualizações |

## Estado funcional

Os módulos listados acima possuem rotas, persistência, autorização e cobertura
automatizada no repositório. Isso não equivale a declarar que um ambiente de
produção específico foi provisionado ou homologado.

Na reconciliação desta documentação foram verificados:

- `php artisan route:list`: 148 rotas da aplicação;
- `php artisan schedule:list`: somente `horizon:snapshot`, a cada cinco minutos;
- `php artisan test`: suíte aprovada, com testes ambientais ou históricos
  explicitamente ignorados;
- `npm run test:js`: suíte JavaScript aprovada;
- `composer validate --strict --no-check-publish`: manifesto válido.

As contagens são deliberadamente omitidas deste documento porque mudam com a
evolução da suíte. Consulte a execução mais recente no ambiente de trabalho ou no
CI.

## Limites conhecidos

- Não há seed operacional ou cenário de demonstração. Dados são criados pela
  aplicação; factories são usadas nos testes.
- A exportação para PDF e DOCX acontece no navegador a partir da prévia A4 e não
  altera o status nem persiste um arquivo de relatório no servidor.
- O upload do mapa ocorre na avaliação e recebe PNG, JPEG ou WEBP. Cada upload
  cria uma versão histórica do mapa lógico da avaria.
- Não existem ações HTTP de retry manual para imagens. Cada Job tenta três vezes;
  uma falha definitiva exige substituir ou reenviar o arquivo.
- O scheduler da aplicação não executa limpeza periódica de arquivos. Somente as
  métricas do Horizon estão agendadas.
- O catálogo nativo inclui CV, TAC e REC. A configuração dessas categorias e de
  suas classificações exige alteração versionada no código.
- Operação offline, aplicativo nativo, inteligência artificial, cobrança SaaS e
  API pública não estão implementados.
- O provisionamento e a homologação do VPS continuam sendo atividades do ambiente
  de produção, descritas nos documentos 13 e 13A.

## Regra de manutenção

Toda alteração funcional deve atualizar o documento correspondente no mesmo
conjunto de mudanças. Documente apenas comportamento verificável e prefira citar
o arquivo fonte a copiar grandes trechos de código.

Antes de concluir uma atualização documental:

1. confira rotas, enums, policies, requests e configurações relevantes;
2. valide os links relativos;
3. remova referências a classes ou comandos inexistentes;
4. execute as verificações automatizadas proporcionais à mudança;
5. confirme que fatos ambientais estão identificados como evidência datada, não
   como propriedade permanente do produto.
