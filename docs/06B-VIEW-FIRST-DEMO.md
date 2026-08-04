# 06B — View First e Demonstração do MVP

## 1. Objetivo

Criar um corte visual demonstrável do Projeto Vistoria, priorizando a experiência do cliente antes da conclusão de toda a lógica dos módulos restantes.

A proposta é produzir rapidamente um fluxo coerente, navegável e visualmente consistente, utilizando a base já concluída nos módulos:

```text
03 — Modelagem Multiempresa
04 — Clientes e Estrutura Operacional
05 — Equipamentos e Documentos
06 — Inspeções e Fluxo Operacional
06A — Dashboard e Navegação
```

O módulo 07, de avarias e reinspeções, será aproveitado no estado atual, sem exigir sua conclusão integral antes da demonstração.

A regra deste corte será:

```text
Interface definitiva
+
Dados reais quando já disponíveis
+
Dados de demonstração quando a lógica ainda não estiver pronta
```

O objetivo não é criar um protótipo descartável.

Os componentes Vue, layouts, páginas e padrões visuais produzidos nesta etapa deverão ser reutilizados na implementação definitiva.

---

## 2. Resultado esperado

Ao concluir esta etapa, deverá ser possível apresentar ao cliente um fluxo visual completo:

```text
Dashboard
↓
Equipamento
↓
Inspeção
↓
Avarias
↓
Avaliação CIVIL
↓
Fotografias
↓
Prévia do relatório
```

A demonstração deverá permitir que o cliente compreenda:

- como localizar um equipamento;
- como consultar o histórico de inspeções;
- como abrir uma inspeção;
- como visualizar as avarias encontradas;
- como registrar ou editar uma avaliação;
- como visualizar GUT e classificação CV;
- como consultar fotografias;
- como visualizar o resultado final antes da geração do PDF.

A experiência deverá parecer um produto único, e não uma coleção de páginas isoladas.

---

## 3. Escopo incluído

Será criado ou ajustado:

- fluxo visual principal da demonstração;
- página de detalhes do equipamento;
- página de detalhes da inspeção;
- visão resumida das avarias;
- tela de avaliação de avaria CIVIL;
- galeria visual de fotografias;
- prévia navegável do relatório;
- componentes visuais reutilizáveis;
- dados de demonstração controlados;
- estados vazios, carregamento e indisponibilidade;
- responsividade para desktop e celular;
- navegação coerente entre os módulos;
- indicação visual de conteúdo provisório;
- seed ou provider específico para demonstração;
- testes mínimos de renderização e navegação.

Também será permitido ajustar visualmente páginas já existentes quando isso for necessário para manter consistência com o fluxo demonstrado.

---

## 4. Fora do escopo

Não será necessário concluir nesta etapa:

- processamento real de imagens;
- compressão definitiva das fotografias;
- upload em fila;
- regras completas e oficiais de GUT;
- faixas oficiais completas de CV;
- revisão técnica item a item;
- aprovação definitiva;
- auditoria completa;
- geração real do PDF;
- armazenamento final do relatório;
- assinatura eletrônica;
- funcionamento offline;
- importação completa das planilhas antigas;
- reprodução exata das 34 páginas do relatório atual;
- categoria TAC;
- categoria REC;
- inteligência artificial.

Esta etapa não deve antecipar lógica complexa apenas para tornar uma tela aparentemente funcional.

Quando a lógica ainda não existir, a interface deverá usar dados de demonstração claramente controlados.

---

## 5. Princípios do View First

### 5.1 Não criar protótipo descartável

As páginas deverão ser construídas dentro da aplicação Laravel, Inertia e Vue existente.

Não criar:

```text
HTML separado
projeto paralelo
Figma convertido manualmente em página estática
rotas fora do padrão atual
```

Os componentes criados deverão receber posteriormente dados reais sem reconstrução completa.

---

### 5.2 Não duplicar regras de negócio no frontend

O frontend poderá simular resultados para demonstração, mas não deverá implementar regras definitivas de domínio.

Exemplo permitido:

```text
Mostrar GUT 36 e CV-2 vindo de uma prop de demonstração.
```

Exemplo proibido:

```text
Criar no Vue uma tabela definitiva de faixas CV sem validação técnica.
```

A fonte de verdade continuará sendo o backend quando os módulos forem concluídos.

---

### 5.3 Interface definitiva, lógica progressiva

A página deverá ser criada como versão visual definitiva sempre que possível.

A lógica poderá evoluir em três níveis:

```text
Nível 1 — Dados fixos de demonstração
Nível 2 — Dados reais somente leitura
Nível 3 — Escrita e regras reais
```

A evolução entre níveis não deverá exigir troca de página ou reconstrução do layout.

---

### 5.4 Fluxo vertical antes de cobertura horizontal

Será priorizado um fluxo completo para um equipamento específico.

Não será prioridade criar agora todas as variações possíveis de:

- clientes;
- unidades;
- equipamentos;
- tipos de inspeção;
- estados raros;
- permissões excepcionais;
- combinações de avarias.

Primeiro será entregue uma história completa e convincente.

---

### 5.5 Dados provisórios identificados

As regras oficiais do procedimento técnico ainda não estão completamente disponíveis.

Por isso, dados de classificação usados na demonstração deverão ser identificados como provisórios.

Texto sugerido na interface:

```text
Configuração de demonstração — parâmetros sujeitos à validação técnica.
```

Essa indicação deverá aparecer principalmente na tela de avaliação CIVIL e na prévia do relatório.

---

## 6. Cenário oficial da demonstração

A demonstração principal utilizará:

```text
Cliente: Samarco Mineração
Unidade: Unidade de Ubu
Área: Usina III
Subárea: Forno de Endurecimento
Equipamento: U03-06VT002
Categoria: CIVIL
Tipo de inspeção: Reinspeção
```

O equipamento deverá possuir:

- dados cadastrais preenchidos;
- pelo menos um documento técnico;
- uma inspeção anterior liberada;
- uma inspeção atual em andamento ou aguardando revisão;
- entre 5 e 8 avarias;
- avarias com diferentes condições;
- avaliações com diferentes classificações;
- fotografias demonstrativas;
- comentários e recomendações;
- quantitativos;
- responsáveis atribuídos.

### 6.1 Distribuição sugerida das avarias

```text
1 avaria nova
2 avarias sem alteração
1 avaria agravada
1 avaria melhorada
1 avaria reparada
1 avaria não localizada ou não inspecionada
```

A quantidade poderá ser reduzida quando necessário, mas a demonstração deverá mostrar pelo menos quatro condições diferentes.

### 6.2 Classificações sugeridas

Usar exemplos variados:

```text
CV-2
CV-3
CV-4
CV-5
```

Não é necessário demonstrar todas as classes.

---

## 7. Telas envolvidas

## 7.1 Dashboard operacional

A dashboard já existe e será usada como ponto inicial.

Ajustes permitidos:

- melhorar destaque da inspeção de demonstração;
- garantir CTA claro para continuar o trabalho;
- exibir atividade recente relacionada ao equipamento;
- apresentar estado visual consistente com as páginas novas.

Não reconstruir a dashboard.

---

## 7.2 Detalhes do equipamento

Rota conceitual:

```text
/equipments/{equipment}
```

A página deverá apresentar:

- TAG;
- nome do equipamento;
- status;
- cliente;
- unidade;
- área;
- subárea;
- localização;
- fabricante e modelo, quando disponíveis;
- criticidade CIVIL atual;
- quantidade de avarias ativas;
- quantidade de inspeções;
- documentos atuais;
- linha do tempo de inspeções;
- CTA para abrir a inspeção atual.

### Blocos sugeridos

```text
Cabeçalho do equipamento
Indicadores resumidos
Informações cadastrais
Inspeção atual
Histórico de inspeções
Documentos técnicos
```

---

## 7.3 Detalhes da inspeção

Rota conceitual:

```text
/inspections/{inspection}
```

A página deverá funcionar como central da inspeção.

Apresentar:

- número da inspeção;
- equipamento;
- tipo;
- status;
- data prevista;
- data de execução;
- ordem de serviço;
- responsáveis;
- progresso do preenchimento;
- resumo das avarias;
- resumo por classificação;
- documentos de referência;
- histórico de status;
- ações disponíveis.

### Navegação interna sugerida

```text
Visão geral
Avarias
Fotografias
Documentos
Histórico
Relatório
```

No View First, essa navegação poderá usar tabs ou links locais.

---

## 7.4 Lista de avarias da inspeção

A lista deverá permitir leitura rápida do estado técnico.

Cada avaria deverá mostrar:

- código;
- título;
- localização;
- condição atual;
- classificação CV;
- pontuação GUT;
- quantidade de fotografias;
- status da avaliação;
- pendências;
- ação principal.

### Filtros visuais sugeridos

```text
Todas
Críticas
Pendentes
Reparadas
Não inspecionadas
```

Os filtros poderão ser somente locais durante a demonstração, desde que não escondam comportamento inexistente como se fosse definitivo.

---

## 7.5 Avaliação da avaria CIVIL

Esta será a tela central da demonstração.

Rota conceitual:

```text
/defect-assessments/{defectAssessment}
```

A página deverá mostrar:

### Identificação

- código da avaria;
- título;
- condição atual;
- localização;
- avaliação anterior, quando existir.

### Classificação

- gravidade;
- urgência;
- tendência;
- pontuação GUT;
- classificação CV;
- prazo recomendado;
- aviso de parâmetros provisórios.

### Caracterização técnica

- tipo de dano;
- elemento;
- item ou subitem;
- referência de projeto;
- impacto na atividade.

### Quantitativos

- descrição;
- quantidade;
- medida;
- unidade;
- totalização visual quando aplicável.

### Texto técnico

- comentário;
- recomendação;
- modelos sugeridos, quando disponíveis;
- indicação de edição manual.

### Evidências

- fotografias;
- legenda;
- foto principal;
- comparação com avaliação anterior.

### Ações

- salvar rascunho;
- marcar como completa;
- voltar à inspeção;
- navegar para avaria anterior ou próxima.

No corte inicial, ações sem backend real poderão operar apenas no estado local da página ou apresentar mensagem explícita de demonstração.

---

## 7.6 Galeria de fotografias

A galeria deverá apresentar:

- miniaturas;
- fotografia principal;
- tipo da fotografia;
- legenda;
- data;
- usuário responsável;
- visualização ampliada;
- vínculo com a avaria;
- indicação de imagem atual ou histórica.

No View First:

- imagens poderão vir de arquivos já disponíveis para demonstração;
- não é obrigatório implementar processamento;
- não é obrigatório implementar upload real;
- o layout deverá prever estados `pending`, `ready` e `failed`.

---

## 7.7 Prévia do relatório

Rota conceitual:

```text
/inspections/{inspection}/report-preview
```

A prévia deverá simular o conteúdo principal do relatório futuro.

Blocos mínimos:

```text
Capa simplificada
Identificação da organização
Identificação do cliente
Identificação do equipamento
Dados da inspeção
Resumo de criticidade
Resumo por classificação
Relação de avarias
Detalhamento das avaliações
Fotografias
Comentários e recomendações
Responsáveis
Número da revisão
```

A prévia será HTML responsivo dentro da aplicação.

Não será necessário gerar PDF nesta etapa.

A interface deverá deixar claro:

```text
Prévia de demonstração — o documento final ainda não foi gerado.
```

---

## 8. Componentes reutilizáveis

Criar ou consolidar componentes como:

```text
PageHeader
StatusBadge
MetricCard
SectionCard
EmptyState
LoadingState
ErrorState
Timeline
InspectionStatusBadge
DefectConditionBadge
CivilClassificationBadge
GutScoreCard
DefectCard
PhotoGallery
PhotoViewer
AssessmentProgress
ResponsiblesList
ReportSection
ProvisionalDataNotice
```

### Regra

Componentes genéricos devem ficar em:

```text
resources/js/components/ui
```

Componentes específicos do domínio devem ficar em:

```text
resources/js/components/domain
```

Evitar um componente único excessivamente grande para toda a tela de avaliação.

---

## 9. Contratos de dados da demonstração

## 9.1 Estratégia

Os dados deverão ser fornecidos pelo backend por props do Inertia.

Não espalhar objetos fixos dentro de vários componentes Vue.

Criar uma única origem de dados de demonstração.

Opções permitidas:

```text
DemoOrganizationSeeder
ViewFirstDemoSeeder
DemoInspectionDataFactory
DemoInspectionPresenter
```

A opção preferida será um seeder idempotente, complementado por presenters ou mapeadores de página.

---

## 9.2 Dados reais já disponíveis

Usar dados reais dos módulos concluídos para:

- organização;
- usuários;
- cliente;
- unidade;
- área;
- subárea;
- equipamento;
- documentos;
- inspeção;
- responsáveis;
- status;
- histórico de inspeção.

---

## 9.3 Dados provisórios

Poderão ser simulados para:

- fotografias processadas;
- valores completos de avaliação CIVIL;
- classificação CV;
- quantitativos;
- comentários;
- recomendações;
- resumo de criticidade;
- prévia do relatório;
- revisão técnica detalhada.

Esses dados deverão ficar concentrados e facilmente removíveis.

---

## 9.4 Proibição

Não criar registros inconsistentes apenas para preencher a interface.

Exemplos proibidos:

- avaria de outro equipamento vinculada à inspeção;
- avaliação sem inspeção correspondente;
- foto vinculada diretamente ao equipamento quando a interface afirma que pertence à avaliação;
- inspeção liberada com dados editáveis;
- usuário de outra organização como responsável.

Mesmo em demonstração, a estrutura deve respeitar o domínio já definido.

---

## 10. Endpoints e ações

Rotas de leitura sugeridas:

```text
GET /equipments/{equipment}
GET /inspections/{inspection}
GET /inspections/{inspection}/defects
GET /defect-assessments/{defectAssessment}
GET /inspections/{inspection}/photos
GET /inspections/{inspection}/report-preview
```

Rotas de escrita poderão ser simuladas ou implementadas parcialmente:

```text
PATCH /defect-assessments/{defectAssessment}
POST /defect-assessments/{defectAssessment}/complete
```

### Regra

Não criar endpoints falsos que retornem sucesso sem deixar claro que não persistem dados.

Quando uma ação ainda não existir, usar uma das opções:

```text
botão desabilitado com explicação
mensagem de demonstração
persistência local claramente temporária
```

---

## 11. Implementação em etapas

## Etapa 1 — Congelar o fluxo da demonstração

- definir o equipamento oficial;
- definir a inspeção atual;
- definir as avarias demonstradas;
- definir a ordem das telas;
- definir os dados provisórios;
- definir o que será realmente editável.

Resultado:

```text
Mapa fechado da demonstração.
```

---

## Etapa 2 — Consolidar design system mínimo

- revisar shell atual;
- consolidar cards;
- consolidar badges;
- consolidar cabeçalhos;
- definir estados de loading, vazio e erro;
- definir padrão de espaçamento;
- definir padrão responsivo.

Resultado:

```text
Base visual compartilhada pelas páginas novas.
```

---

## Etapa 3 — Detalhes do equipamento

- aprimorar ou criar a página;
- incluir indicadores;
- incluir inspeção atual;
- incluir histórico;
- incluir documentos;
- criar CTA para inspeção.

Resultado:

```text
Equipamento como porta de entrada do histórico.
```

---

## Etapa 4 — Central da inspeção

- consolidar cabeçalho;
- mostrar responsáveis;
- mostrar progresso;
- mostrar resumo por condição;
- mostrar resumo por classificação;
- criar navegação interna.

Resultado:

```text
Inspeção funcionando como central operacional.
```

---

## Etapa 5 — Lista de avarias

- criar cards ou tabela responsiva;
- adicionar filtros visuais;
- adicionar pendências;
- adicionar CTA de avaliação;
- tratar estados vazios.

Resultado:

```text
Lista de avarias pronta para navegação da demonstração.
```

---

## Etapa 6 — Avaliação CIVIL

- criar página completa;
- separar em seções;
- mostrar dados anteriores;
- mostrar GUT e CV;
- mostrar quantitativos;
- mostrar comentários;
- mostrar recomendações;
- integrar galeria visual.

Resultado:

```text
Principal tela técnica da demonstração concluída.
```

---

## Etapa 7 — Galeria

- criar miniaturas;
- criar visualização ampliada;
- criar foto principal;
- diferenciar imagem atual e histórica;
- tratar estados de processamento.

Resultado:

```text
Evidências técnicas apresentadas de forma convincente.
```

---

## Etapa 8 — Prévia do relatório

- montar estrutura HTML;
- usar dados da inspeção;
- exibir resumo;
- exibir avarias;
- exibir fotos;
- exibir responsáveis;
- exibir aviso de demonstração.

Resultado:

```text
Fluxo encerrado com entrega visual compreensível ao cliente.
```

---

## Etapa 9 — Seed e roteiro de demonstração

- criar seed idempotente;
- garantir dados previsíveis;
- criar usuário de demonstração;
- criar roteiro de navegação;
- garantir que o ambiente possa ser restaurado.

Resultado:

```text
Demonstração repetível sem preparação manual.
```

---

## Etapa 10 — Validação visual

Validar em:

```text
1440 px
1280 px
1024 px
768 px
375 px
```

Conferir:

- sem overflow do documento;
- cards legíveis;
- tabelas com rolagem interna quando necessário;
- ações acessíveis;
- galeria utilizável;
- navegação móvel funcional;
- textos técnicos sem corte;
- contraste e foco visível.

---

## 12. Testes obrigatórios

### 12.1 Backend

- [ ] usuário autenticado acessa o fluxo;
- [ ] usuário de outra organização não acessa o equipamento;
- [ ] usuário de outra organização não acessa a inspeção;
- [ ] usuário de outra organização não acessa a avaria;
- [ ] rotas retornam somente dados necessários;
- [ ] seed é idempotente;
- [ ] relações da demonstração respeitam organização e equipamento;
- [ ] superadministrador não recebe fluxo operacional sem contexto válido.

### 12.2 Frontend

- [ ] páginas compilam sem erro;
- [ ] navegação entre as sete telas funciona;
- [ ] estados vazios são exibidos corretamente;
- [ ] estados de demonstração são identificados;
- [ ] avaliação funciona em 375 px;
- [ ] galeria funciona em tela pequena;
- [ ] prévia do relatório não possui overflow horizontal;
- [ ] foco de teclado permanece visível;
- [ ] botões sem backend real não fingem persistência definitiva.

### 12.3 Regressão

- [ ] dashboard continua funcionando;
- [ ] listagem de clientes continua funcionando;
- [ ] listagem de equipamentos continua funcionando;
- [ ] listagem de inspeções continua funcionando;
- [ ] shell existente não sofre regressão;
- [ ] build do Vite passa;
- [ ] Pint passa;
- [ ] testes existentes continuam passando.

---

## 13. Critérios de aceite

Esta etapa será considerada concluída quando:

- o cliente puder navegar do dashboard até a prévia do relatório;
- o fluxo usar o equipamento `U03-06VT002` ou outro cenário oficialmente aprovado;
- a interface utilizar o shell existente;
- as páginas novas forem construídas em Inertia e Vue;
- o equipamento apresentar histórico e inspeção atual;
- a inspeção apresentar resumo e acesso às avarias;
- a lista de avarias apresentar condições e classificações diferentes;
- a avaliação CIVIL apresentar GUT, CV, dano, elemento, quantitativos, comentário e recomendação;
- fotografias puderem ser visualizadas na interface;
- a prévia do relatório apresentar dados coerentes da inspeção;
- conteúdo provisório estiver identificado;
- não houver acesso cruzado entre organizações;
- o fluxo for utilizável em desktop e celular;
- nenhuma página essencial depender de lógica ainda inexistente para renderizar;
- o seed permitir repetir a demonstração;
- os testes mínimos e o build estiverem aprovados.

---

## 14. Riscos e brechas

### 14.1 View First virar retrabalho

Risco:

```text
Criar páginas estáticas sem contrato com o backend.
```

Mitigação:

- usar props Inertia;
- usar componentes reais;
- concentrar dados provisórios;
- manter nomes e estruturas do domínio.

---

### 14.2 Cliente interpretar simulação como funcionalidade pronta

Risco:

```text
O cliente acreditar que upload, cálculo, aprovação ou PDF já estão concluídos.
```

Mitigação:

- identificar dados provisórios;
- não exibir sucesso falso;
- explicar o estado de cada funcionalidade na demonstração;
- separar visual aprovado de regra técnica aprovada.

---

### 14.3 Implementar regra provisória como definitiva

Risco:

```text
Fixar faixas GUT/CV observadas no relatório sem o procedimento oficial.
```

Mitigação:

- usar valores somente no seed de demonstração;
- manter aviso visual;
- não criar regras permanentes em componentes Vue;
- aguardar validação técnica para ativação real.

---

### 14.4 Escopo visual crescer demais

Risco:

```text
Tentar melhorar todas as páginas existentes antes da apresentação.
```

Mitigação:

- trabalhar apenas no fluxo oficial;
- limitar o corte às sete telas;
- adiar configurações e telas administrativas;
- não criar variações sem utilidade na apresentação.

---

### 14.5 Dados incoerentes

Risco:

```text
O seed demonstrar relações impossíveis no sistema real.
```

Mitigação:

- respeitar tenant;
- respeitar equipamento;
- respeitar inspeção anterior;
- respeitar status;
- respeitar vínculo da avaliação;
- testar integridade.

---

### 14.6 Tela tecnicamente bonita, mas inadequada para campo

Risco:

```text
Formulários densos funcionarem apenas em desktop.
```

Mitigação:

- validar 375 px desde o início;
- dividir a avaliação em seções;
- usar ações fixas com cuidado;
- evitar tabelas largas em formulários;
- priorizar leitura e toque.

---

### 14.7 Duplicação do módulo 07

Risco:

```text
Criar uma estrutura paralela de avarias apenas para a demonstração.
```

Mitigação:

- reutilizar `defects` e `defect_assessments` quando já disponíveis;
- complementar apenas dados ausentes;
- não criar models `DemoDefect` ou tabelas paralelas.

---

## 15. Checklist final

### Planejamento

- [ ] equipamento de demonstração definido;
- [ ] inspeção de demonstração definida;
- [ ] conjunto de avarias definido;
- [ ] classificações provisórias definidas;
- [ ] roteiro da apresentação definido.

### Interface

- [ ] dashboard integrado ao fluxo;
- [ ] detalhes do equipamento concluídos;
- [ ] detalhes da inspeção concluídos;
- [ ] lista de avarias concluída;
- [ ] avaliação CIVIL concluída;
- [ ] galeria concluída;
- [ ] prévia do relatório concluída.

### Dados

- [ ] dados reais reutilizados;
- [ ] dados provisórios centralizados;
- [ ] seed idempotente;
- [ ] cenário restaurável;
- [ ] integridade entre organização, equipamento, inspeção e avaria validada.

### Qualidade

- [ ] responsividade validada;
- [ ] acessibilidade básica validada;
- [ ] isolamento multiempresa validado;
- [ ] testes existentes passando;
- [ ] novos testes passando;
- [ ] Pint aprovado;
- [ ] build aprovado;
- [ ] documentação atualizada.

---

## 16. Ordem recomendada de execução imediata

A primeira implementação após este documento deverá ser:

```text
Detalhes da inspeção
+
Lista de avarias
```

### Motivo

A dashboard, os equipamentos e as inspeções já possuem base implementada.

A maior lacuna visual do fluxo está entre:

```text
abrir uma inspeção
↓
compreender as avarias
↓
abrir uma avaliação
```

Essa fatia entrega mais valor visual com menos risco de retrabalho.

Não iniciar pela prévia do relatório.

Sem uma estrutura clara de inspeção e avarias, a prévia obrigaria a inventar contratos que depois precisariam ser refeitos.

---

## 17. Relação com o roadmap principal

Este documento é um corte paralelo de apresentação.

Ele não substitui:

```text
07 — Avarias e Reinspeções
08 — Fotos e Armazenamento
09 — Classificação CIVIL e GUT
10 — Revisão, Aprovação e Auditoria
11 — Relatório PDF
```

A relação será:

```text
06B define e entrega a experiência visual.
07 a 11 substituem progressivamente as simulações por lógica definitiva.
```

O roadmap principal deverá indicar que o View First foi criado como prioridade temporária, sem declarar concluídos os módulos 07 a 11.

---

## 18. Estado do documento

```text
Documentação: Concluída
Implementação: Pendente
Validação: Pendente
Estado geral: Documentado
```

---

## 19. Commit sugerido

```bash
git add docs/06B-VIEW-FIRST-DEMO.md
git commit -m "docs: define corte view first para demonstração do MVP"
```

---

## 20. Próximo passo

Criar a primeira fatia visual:

```text
Detalhes da inspeção
+
Lista de avarias
```

O trabalho deverá começar pelo contrato das props Inertia dessas duas páginas, reutilizando os dados reais existentes e identificando somente os campos que precisarão de dados provisórios.
