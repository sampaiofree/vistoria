# Portal do cliente — dashboard e consulta técnica

**Status:** cadastro, consulta de inspeções liberadas e comparação de avaliações na prévia implementados; dashboard completo planejado.
**Data das decisões:** 03/10/2026.
**Cliente de referência:** Samarco. A solução deve atender ao cliente da organização, sem regras específicas fixadas para a Samarco.

## 1. Objetivo e premissa obrigatória

Criar uma experiência de consulta amigável para o cliente final acompanhar os
equipamentos, consultar relatórios e entender as avarias e sua evolução. O
dashboard será a porta de entrada para equipamentos, avarias e relatórios.

> O portal é uma camada de leitura sobre o sistema existente. Deve preservar as
> regras atuais de datas, prazos, classificações, estados, avaliações, mapas e
> composição dos relatórios. Não deve alterar o fluxo operacional nem introduzir
> cálculos alternativos para os mesmos conceitos.

O cliente deve conseguir localizar uma avaria, consultar suas evidências e
acessar o relatório correspondente em poucos passos. A implementação deverá
reutilizar os serviços e as regras do domínio, acrescentando apresentação,
navegação e autorização próprias para o público externo.

## 2. Decisões de produto

| Tema | Decisão |
|---|---|
| Visibilidade | Conteúdo disponibilizado automaticamente após a liberação técnica da inspeção, sem uma publicação adicional. |
| Alcance | Todos os usuários do cliente consultam o mesmo conjunto de equipamentos autorizados dentro da organização. Não haverá restrições individuais por área ou equipamento nesta versão. |
| Entrada dos equipamentos | Um equipamento aparece no portal somente depois de sua primeira inspeção liberada. |
| Contas | Criadas, desativadas e administradas pela equipe interna da organização. |
| Tela inicial | Visão geral com indicadores clicáveis, relatórios recentes e busca por equipamento ou avaria. |
| Avarias | Detalhamento técnico completo do conteúdo liberado, linha do tempo e comparação de duas avaliações. |
| Fotografias | Ampliação, zoom, navegação e comparação dentro do portal, sem botão de download individual. |
| Mapas | Cada mapa pertence a uma única avaria; consulta da versão correspondente à avaliação. |
| Relatório online | Prévia existente, com fotos de avarias que abrem resumo, histórico, comparação e detalhes de classificação e quantitativo no próprio relatório. |
| PDF | Geração sob demanda no formato A4 atual, sem arquivamento de uma cópia oficial e sem links para o portal. |
| Outros downloads | Planilha de quantitativos. DOCX e download separado de fotos não fazem parte desta versão. |
| Indicadores | Situação das avarias, classificações, novas e reparações constatadas no período, prazos vencidos e relatórios recentes. |
| Datas e regras técnicas | Reutilizar o que já está definido no sistema e nos relatórios; não criar uma nova lógica para o portal. |

## 3. Acesso e conteúdo disponível

**Etapas já implementadas:** existe o tipo de conta `client`, gerenciado pelo
administrador em **Configurações → Cliente → Gerenciar usuários do cliente**.
Essas contas usam a tabela de usuários e não têm papel operacional. O login
abre a lista de inspeções liberadas dos equipamentos do cliente da organização,
com busca e paginação. A senha temporária deve ser trocada no primeiro acesso.
O botão **Ver relatório** abre diretamente a prévia. Dentro da inspeção, o
Cliente acessa somente relatório e quantitativo, sem abas ou menu contextual.
O relatório oferece **Voltar às inspeções**, **Quantitativo** e PDF/impressão;
o quantitativo oferece **Voltar ao relatório** e **Exportar XLS**.
Avarias, classificação, fotografias e comparação são consultadas pelo modal.
As demais páginas da inspeção ficam bloqueadas, inclusive por link direto;
o endereço antigo da visão geral redireciona ao relatório após autorização.
As rotas de imagens, mapas e histórico do modal mantêm a leitura autorizada.
Equipe, histórico operacional, DOCX, configurações e alterações permanecem bloqueados.

As regras abaixo continuam orientando o portal completo e as próximas etapas.

- Criar um perfil próprio de consulta do cliente, sem atribuições de Inspetor,
  Planejador, Revisor ou Liberador.
- Reutilizar a autenticação e a gestão de contas existentes, incluindo senha
  temporária e troca obrigatória no primeiro acesso.
- Manter o isolamento por organização e pelo cliente vinculado. Não oferecer
  acesso a dados de outras organizações nem seletor de empresas.
- Não permitir edição, upload, exclusão, alteração de classificação, atribuição
  de responsáveis ou transição de inspeções pelo cliente.
- Permitir as ações necessárias à própria conta, como troca de senha e saída,
  sem confundi-las com edição de conteúdo técnico.
- Não exibir inspeções em preparação, revisão ou correção, nem seus conteúdos,
  contagens, apontamentos internos ou notificações operacionais.
- Aplicar as mesmas restrições a páginas, respostas de dados, fotografias,
  versões de mapas e downloads. Ocultar controles na interface não substitui
  autorização no servidor.

A conclusão de uma avaliação, isoladamente, não torna seu conteúdo visível ao
cliente. A inspeção que a contém precisa estar liberada. Durante uma nova
inspeção, o portal continua apresentando o histórico já liberado do equipamento.

## 4. Telas e navegação

### 4.1. Visão geral

Apresentar indicadores de avarias ativas por classificação, avarias novas no
período, reparações constatadas no período e prazos de tratamento vencidos,
além de relatórios liberados recentemente. Cada indicador deve abrir a lista
que explica sua contagem, preservando os filtros aplicados.

Oferecer busca por TAG, identificação do equipamento e código de avaria, com
filtros pertinentes por área, subárea, equipamento, categoria, classificação
e período. Área e subárea continuam sendo atributos do equipamento, conforme
o modelo atual; o portal não exige novos cadastros para esses filtros.

Mostrar a data da última inspeção liberada no contexto do equipamento para que
o cliente reconheça a atualidade das evidências. Não criar uma nota geral de
saúde que misture categorias ou substitua as classificações existentes.

### 4.2. Equipamentos

Listar os equipamentos com ao menos uma inspeção liberada. O detalhe reúne
identificação, histórico de inspeções liberadas, avarias e relatórios, mantendo
a navegação entre esses conteúdos. A existência de uma reinspeção em andamento
não deve retirar o acesso ao histórico anterior.

### 4.3. Avarias

Exibir código, equipamento, categoria, localização, situação baseada no conteúdo
liberado e data da avaliação consultada. Disponibilizar os dados técnicos
aplicáveis: classificação, quantitativos, comentários técnicos, recomendações,
prazos, números de Nota M2 e tratativas especiais, além de fotografias e mapa.

Usar os vínculos e dados do contexto da inspeção consultada. Nota M2 e tratativas
de um ciclo posterior não devem substituir os valores apresentados em uma
consulta histórica. Notas internas e pedidos de correção não fazem parte do
conteúdo técnico externo.

### 4.4. Relatórios

Oferecer biblioteca de inspeções liberadas, com identificação do relatório,
equipamento e datas já adotadas pelo sistema. Permitir leitura online e
downloads de PDF e planilha de quantitativos.

O relatório online será adaptável à tela, sem depender da leitura de uma imagem
A4 reduzida. Seu conteúdo deve corresponder ao relatório da mesma inspeção,
incluindo as seções aplicáveis, mapas, evidências e quantitativos.

Ao clicar em uma foto de avaria, o usuário vê a imagem ampliada, o resumo da
avaliação e o histórico anterior em um modal, sem perder o ponto de leitura.
No modal, a foto pode ser aberta em tela cheia, ampliada e movimentada; ao
fechar o visualizador, a consulta retorna ao mesmo painel. As notas GUT exibem
as cores salvas em cada avaliação, inclusive no histórico comparado.
Os códigos de classificação aparecem em badges no resumo, histórico e nos dois
painéis da comparação, sempre com a cor salva na avaliação correspondente ou
apresentação neutra quando não houver cor válida.
Na prévia online, os itens do sumário levam às páginas indicadas, preservando o
zoom. Os atalhos não são incluídos na impressão ou no PDF.
O histórico é limitado à avaliação representada no relatório aberto. A opção
de avaliação completa abre a tela existente em nova aba apenas para usuários
internos. O Cliente permanece no modal, sem esse botão ou links de avaliação.
Fotos gerais do equipamento não abrem esse modal.

Um relatório antigo não deve mostrar silenciosamente uma avaliação mais
recente. Na reinspeção seletiva, o modal e a avaliação completa respeitam a
referência histórica usada na composição daquele relatório. A interação é
exclusiva da leitura online; impressão e PDF permanecem sem links.

## 5. Histórico, comparação e evidências

### Histórico de evolução

A linha do tempo usa a identidade persistente da avaria e as avaliações
disponíveis em inspeções liberadas. Cada avaliação deve permitir consultar
seus dados e abrir o relatório de origem.

Quando uma inspeção mantém uma avaliação anterior sem reavaliar a avaria,
identificar explicitamente o histórico mantido e sua origem. Não criar uma
avaliação artificial, duplicar evidências ou apresentar a ausência de
reavaliação como confirmação de que a condição permaneceu igual.

Relações existentes de recorrência, divisão ou associação devem preservar as
identidades e os vínculos atuais, sem fundir avarias distintas em uma mesma
sequência de avaliações.

### Comparação de duas avaliações

Permitir selecionar duas avaliações liberadas da mesma avaria e consultar lado
a lado fotografias, classificação, quantitativos e recomendações, com datas e
inspeções identificadas. Com apenas uma avaliação disponível, informar que
a comparação ainda não está disponível.

Mostrar diferenças registradas sem produzir uma conclusão automática de
melhora ou piora a partir de pontuação ou de imagens. Não somar ou comparar
quantidades de unidades diferentes como se fossem equivalentes.

### Fotografias e mapas

O visualizador de fotos deve permitir tela cheia, zoom, movimentação da imagem
e navegação entre as fotografias, mantendo legenda, código e data disponível
identificados. A comparação usará imagens lado a lado, sem exigir o mesmo
enquadramento ou introduzir alinhamento automático.

Reutilizar os arquivos otimizados existentes. Esta iniciativa não altera a
política de upload, resolução ou retenção dos originais. Não prometer detalhe
visual além da resolução armazenada.

Cada mapa corresponde a uma única avaria e pode destacar várias regiões dessa
mesma avaria. Exibir no detalhe e no relatório a versão associada à avaliação,
com suas marcações, legenda e referência de projeto, permitindo ampliação para
consulta. Não implementar mapa coletivo de avarias ou editor de geometria.

## 6. Datas, indicadores e fidelidade ao domínio

A data da inspeção já existe no relatório (`inspected_on`) e será reutilizada
como referência para os indicadores de avarias identificadas e reparações
constatadas no período. A data de liberação determina a disponibilidade ao
cliente e a apresentação dos relatórios recentemente liberados. Não criar
novos campos de data nem substituir datas existentes para atender ao dashboard.

Preservar as regras atuais de prazos por classificação e os prazos históricos
mantidos em reinspeções seletivas. Avarias sem prazo aplicável não entram na
contagem de vencidas. O portal não deve recalcular vencimentos tomando a
liberação como uma nova data inicial.

As contagens devem representar avarias distintas, sem duplicá-las por aparecerem
em vários relatórios ou por terem avaliações históricas mantidas. Listas e
indicadores devem usar a mesma seleção de registros e os mesmos critérios.

**Cuidado de implementação:** o estado operacional persistido de uma avaria pode
ser atualizado por uma avaliação concluída antes da liberação da inspeção.
Portanto, copiar diretamente esse estado para o portal pode expor uma mudança
ainda interna. A leitura externa deve respeitar o recorte das inspeções liberadas
e reutilizar a semântica e a cronologia existentes, sem alterar o estado
operacional nem criar uma segunda regra de classificação ou tratamento.

Se houver dados históricos incompletos, seguir o tratamento já adotado pelo
domínio e explicitar a informação indisponível. Não inventar datas, prazos ou
classificações para preencher o dashboard. Inconsistências encontradas devem ser
tratadas separadamente, sem mudanças silenciosas de regra nesta iniciativa.

## 7. Orientações para a implementação futura

1. **Acesso e autorização (primeira consulta entregue):** manter o perfil de
   cliente, sua entrada na lista de inspeções e a separação do acesso externo e
   operacional, inclusive navegação e notificações.
2. **Leitura do conteúdo liberado (primeira consulta entregue):** manter o
   recorte autorizado em listas, detalhes, relatórios e arquivos, usando os
   snapshots, referências históricas e serviços existentes.
3. **Consulta:** implementar navegação própria entre visão geral, equipamentos,
   avarias e relatórios; paginação, filtros e estados vazios devem funcionar
   dentro do conjunto autorizado.
4. **Evidências e histórico:** manter a ampliação e comparação já disponíveis na
   prévia; evoluir a apresentação das versões corretas dos mapas, sem controles operacionais.
5. **Relatório e exportações:** criar a apresentação web adaptável usando a mesma
   composição técnica do relatório; reutilizar a geração A4 e a exportação de
   quantitativos com autorização para o perfil de cliente.

As interfaces de consulta deverão transportar identificadores e links que
preservem o contexto da inspeção e da avaliação, sem encaminhar o cliente para
rotas internas de edição. Não serializar campos internos apenas porque os
componentes visuais não os exibem.

O PDF será gerado sob demanda, conforme a funcionalidade existente. Não haverá
arquivo oficial persistido nem garantia de reprodução binária de downloads
realizados em momentos diferentes. O conteúdo técnico deve corresponder à
inspeção selecionada. Os atalhos são exclusivos da leitura online.

Não fazem parte desta entrega: integração SAP, novas regras técnicas, novos
fluxos de revisão ou publicação, comentários e edição pelo cliente, gestão de
contas pela Samarco, restrição de acesso por área, arquivamento oficial de PDF,
exportação DOCX no portal ou mudança da política de armazenamento das imagens.

## 8. Critérios de aceite e verificação

- [ ] Uma conta de cliente acessa apenas sua organização e não consegue executar
  ações operacionais, inclusive por requisições diretas.
- [ ] Inspeções não liberadas e seus arquivos não aparecem nem podem ser obtidos
  por acesso direto, busca, comparação, contagem ou download.
- [ ] A primeira liberação disponibiliza automaticamente o equipamento e seu
  conteúdo, sem uma etapa adicional de publicação.
- [ ] Uma avaliação concluída em reinspeção ainda não liberada não altera a
  situação apresentada ao cliente nem seus indicadores.
- [ ] Datas, classificações, quantitativos, M2, tratativas e prazos conferem com
  os valores e critérios do sistema para o mesmo contexto liberado.
- [ ] Uma avaria presente em vários ciclos não é duplicada nos indicadores de
  situação; clicar em um indicador apresenta os registros que o compõem.
- [ ] Histórico mantido é identificado e conserva a avaliação, as evidências e
  o prazo de origem, sem ser apresentado como nova constatação.
- [x] Na prévia, a comparação mostra duas avaliações da mesma avaria, com fotos,
  situação, classificação, data e inspeção; classificação e quantitativo de cada
  lado podem ser expandidos separadamente. Sem histórico, a opção de comparar não aparece.
- [x] Fotografias de avarias no modal permitem tela cheia, zoom de 100% a 400%,
  movimentação e navegação entre fotos da mesma avaliação, com retorno ao painel
  de origem. As notas GUT usam as cores persistidas da avaliação exibida.
- [ ] Mapas mantêm a relação de uma avaria por mapa e apresentam a versão
  histórica correta.
- [x] Fotos de avarias no relatório online abrem imagem ampliada, resumo e
  histórico até o relatório selecionado, com acesso à avaliação completa em
  nova aba somente para usuários internos e retorno ao mesmo ponto de leitura ao fechar o modal. O resumo
  permite expandir notas GUT ou TEL aplicáveis e itens de quantitativo, sempre
  conforme os dados técnicos persistidos da avaliação exibida.
- [ ] Relatório online adaptável e PDF A4 apresentam conteúdo técnico coerente;
  downloads de PDF e planilha funcionam com o perfil externo.
- [x] A prévia oferece zoom para todos os perfis: ajuste à largura ao abrir,
  controles de 50% a 200% em passos de 25 pontos percentuais e rolagem horizontal
  dentro do relatório. A barra acompanha a leitura; fotos e modais permanecem
  acessíveis. O zoom não altera paginação, impressão ou PDF e não é persistido.
- [x] O sumário online oferece atalhos às páginas, inclusive em paisagem; a
  paginação e o texto impresso ou exportado permanecem iguais.
- [x] Os badges de classificação do modal usam as cores históricas salvas,
  com contraste legível e apresentação neutra na ausência de cor válida.
- [ ] Não são oferecidos download individual de fotos, DOCX ou links de avarias
  no PDF.
- [ ] Consulta, filtros, relatório e visualização de evidências funcionam em
  desktop e celular, com navegação por teclado e fechamento dos visualizadores.
- [ ] Ausência de conteúdo liberado ou falha no carregamento de uma evidência
  recebe mensagem adequada, sem substituir o dado por conteúdo de outro ciclo.
- [ ] Os fluxos internos e as regras existentes continuam funcionando após a
  introdução do perfil e das telas externas.

## 9. Referências do projeto

- [Visão geral do produto](../produto/visao-geral.md)
- [Cliente e estrutura operacional](../produto/clientes.md)
- [Inspeções](../produto/inspecoes.md)
- [Avarias, avaliações e reinspeções](../produto/avarias.md)
- [Classificação e Nota M2](../produto/classificacao-e-m2.md)
- [Relatórios e exportações](../produto/relatorios.md)
- [Organizações, usuários e isolamento](../arquitetura/multiempresa.md)
- [Fotografias e armazenamento](../arquitetura/fotos.md)
- [Mapas e localização por avaria](../arquitetura/mapas.md)

Este documento registra a experiência futura acordada e a consulta já
disponível. As referências acima e o código do domínio permanecem como fontes
do comportamento atual. Indicadores, ampliação avançada e relatório web
adaptável continuam planejados. A reinspeção usada para conferir a comparação é
um exemplo fictício criado apenas pelo seeder local `LocalReportComparisonSeeder`;
não integra os dados ou as regras de liberação do produto.
