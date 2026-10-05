# Documentação do Vistoria

As seções de produto, arquitetura, referência técnica e operação descrevem o
comportamento presente no repositório em **03/10/2026**. A seção de planejamento
registra propostas para implementação futura, ainda não disponíveis no sistema.
Para regras executáveis, consulte também rotas, policies, migrations, serviços e
testes. Configurações de produção dependem do ambiente implantado; os guias de
operação descrevem o que o código exige, sem afirmar que um servidor esteja pronto.

## Produto

| Documento | Assunto |
|---|---|
| [Visão geral](produto/visao-geral.md) | Escopo, papéis e fluxo principal |
| [Clientes](produto/clientes.md) | Cliente único por empresa |
| [Equipamentos](produto/equipamentos.md) | Cadastro, estados e histórico |
| [Inspeções](produto/inspecoes.md) | Planejamento, responsabilidades e transições |
| [Avarias](produto/avarias.md) | Avaliações, evidências e reinspeção seletiva |
| [Classificação e Nota M2](produto/classificacao-e-m2.md) | Resumo, prazos e vínculos M2 |
| [Relatórios](produto/relatorios.md) | Prévia, exportações e limites |

## Arquitetura

| Documento | Assunto |
|---|---|
| [Arquitetura](arquitetura/arquitetura.md) | Stack, camadas, autorização e filas |
| [Multiempresa](arquitetura/multiempresa.md) | Tenant, usuários e permissões |
| [Fotos](arquitetura/fotos.md) | Upload, processamento e privacidade |
| [Mapas](arquitetura/mapas.md) | Versionamento, geometria e publicação |

## Referência técnica

| Documento | Assunto |
|---|---|
| [Classificações](referencia/classificacoes.md) | GUT, TEL, TAC, prazos e regras comuns |
| [REC](referencia/rec.md) | Matrizes e fórmulas de peso |
| [CIVIL](referencia/civil.md) | Matrizes e cálculo de volume |
| [TEL](referencia/tel.md) | Matriz de telhado e tapamento |

Os documentos de categoria detalham opções técnicas; o catálogo executado é
`app/Services/Classification/NativeDefectCatalog.php`. Mudanças na matriz devem
ser refletidas no catálogo, nos testes e aqui.

## Operação

| Documento | Assunto |
|---|---|
| [Deploy](operacao/deploy.md) | Instalação, atualização e verificação |
| [Armazenamento e backup](operacao/armazenamento-e-backup.md) | Discos privados, R2 e responsabilidade pelo backup |

## Planejamento

| Documento | Assunto |
|---|---|
| [Portal do cliente](planejamento/portal-do-cliente.md) | Decisões, experiência de consulta e critérios de aceite para implementação futura |

Atualize o documento de domínio junto com alterações funcionais. Evite registrar
resultados de testes e estados de implantação como fatos permanentes.
