# 08A — Plano de Implementação de Mapas e Localização

> Status em 09/08/2026: Fatias 1 a 7 implementadas e aprovadas pelos testes automatizados. Permanecem pendentes os gates ambientais em MySQL e worker assíncrono, a validação manual responsiva/impressão e o fechamento em commit. As regras de domínio permanecem definidas em `08A-MAPAS-E-LOCALIZACAO-DA-INSPECAO.md`.

## 1. Resultado esperado

Entregar mapas de localização privados e auditáveis, vinculados à inspeção e à categoria, com marcações vetoriais associadas às avaliações e às respectivas fotografias.

Ao final, o fluxo deverá ser:

```text
Selecionar categoria
→ criar mapa
→ selecionar documento ou enviar imagem
→ processar imagem-base
→ marcar regiões
→ vincular avaliação e fotos
→ validar cobertura
→ gerar localização e documentação fotográfica no relatório
```

---

## 2. Invariantes que não podem ser quebradas

- `Defect` continua sendo a identidade permanente da avaria;
- `DefectAssessment` continua sendo o registro temporal da inspeção;
- `location_description` permanece disponível como descrição textual;
- nenhuma FK de mapa será adicionada diretamente em `defects`;
- uma avaliação poderá possuir várias marcações;
- uma marcação poderá conter várias regiões;
- a folha do relatório não será persistida nesta etapa;
- fotografias continuarão pertencendo à avaliação;
- mapas e arquivos permanecerão privados;
- todas as queries operacionais continuarão tenant-scoped;
- a prévia demonstrativa atual só será substituída quando o novo read model estiver completo;
- migrations serão aditivas e não exigirão backfill de mapas.

---

## 3. Decisões técnicas

### 3.1 Ativação por categoria

Será adicionado em `defect_categories`:

```text
requires_location_map boolean default false
```

A cobertura somente será exigida quando essa opção estiver ativa. Não haverá regra hard-coded para o código `CV`.

Rollout:

1. criar estrutura com a opção desativada;
2. implantar telas e processamento;
3. criar mapas para inspeções abertas;
4. validar o fluxo;
5. ativar a exigência por categoria.

### 3.2 Editor sem dependência gráfica externa inicial

O primeiro editor será construído com Vue e SVG nativo. Isso mantém a geometria sob controle do projeto e evita introduzir uma biblioteca antes de conhecer os requisitos reais de interação.

Uma biblioteca especializada somente será considerada se os testes manuais demonstrarem problemas de seleção, zoom, transformação ou acessibilidade.

### 3.3 Geometria versionada

O backend será a autoridade para validar o JSON de geometria. O frontend nunca poderá persistir propriedades arbitrárias.

Versão inicial:

```text
geometry_schema_version = 1
```

### 3.4 Processamento assíncrono

PDFs e imagens serão normalizados por Job na fila `images`, seguindo o padrão já utilizado por `ProcessAssessmentPhoto`.

### 3.5 Implantação sem bloqueio imediato

A primeira migration não ativará `requires_location_map` para nenhuma categoria existente. O validador será integrado antes da ativação, mas retornará sem bloquear categorias desativadas.

### 3.6 Rollback operacional

Em caso de problema após ativação, o rollback preferido será desativar `requires_location_map`. As tabelas e os arquivos não serão removidos.

---

## 4. Fatia 1 — Fundação de dados

### 4.1 Objetivo

Criar estrutura persistente, modelos, relacionamentos e autorização, sem alterar telas ou o relatório.

### 4.2 Migration

Arquivo previsto:

```text
database/migrations/2026_08_08_000008_create_inspection_location_maps.php
```

Alterações:

- adicionar `requires_location_map` em `defect_categories`;
- criar `inspection_location_maps`;
- criar `inspection_location_markers`;
- criar `inspection_location_marker_photos`;
- adicionar as chaves compostas necessárias em `assessment_photos` e nas novas tabelas.

Mapas e marcações terão `lock_version` iniciado em `1`. Toda atualização deverá informar a versão lida e incrementá-la atomicamente, rejeitando edições feitas sobre estado desatualizado.

### 4.3 Chaves e restrições

`inspection_location_maps`:

```text
FK (organization_id, equipment_id, inspection_id)
  → inspections (organization_id, equipment_id, id)

FK (organization_id, defect_category_id)
  → defect_categories (organization_id, id)

FK (organization_id, equipment_document_id)
  → equipment_documents (organization_id, id)
```

O vínculo com `equipment_document_id` será opcional. A Action validará também que o documento pertence ao equipamento e está selecionado em `inspection_reference_documents`.

`inspection_location_markers`:

```text
FK (organization_id, equipment_id, inspection_id, inspection_location_map_id)
  → inspection_location_maps

FK (organization_id, inspection_id, defect_assessment_id)
  → defect_assessments
```

`inspection_location_marker_photos`:

```text
FK (organization_id, inspection_id, inspection_location_marker_id)
  → inspection_location_markers

FK (organization_id, inspection_id, assessment_photo_id)
  → assessment_photos
```

Será criada unicidade para:

```text
(organization_id, inspection_location_marker_id, assessment_photo_id)
```

Não haverá unicidade entre mapa e avaliação, pois uma avaliação pode possuir várias marcações no mesmo mapa.

O `down()` deverá remover na ordem: pivot, markers, maps, índices auxiliares e coluna da categoria. Os nomes de FKs serão explícitos e o rollback terá tratamento compatível com SQLite, seguindo o padrão das migrations atuais.

### 4.4 Enums

Arquivos:

```text
app/Enums/InspectionLocationMapSourceKind.php
app/Enums/InspectionLocationMapProcessingStatus.php
```

Valores:

```text
source_kind: reference_document, upload
processing_status: pending, processing, ready, failed
```

### 4.5 Models

Arquivos:

```text
app/Models/InspectionLocationMap.php
app/Models/InspectionLocationMarker.php
app/Models/InspectionLocationMarkerPhoto.php
```

Relacionamentos atualizados:

```text
Organization::inspectionLocationMaps()
Equipment::inspectionLocationMaps()
Inspection::locationMaps()
DefectCategory::locationMaps()
DefectAssessment::locationMarkers()
AssessmentPhoto::locationMarkers()
EquipmentDocument::inspectionLocationMaps()
```

`DefectCategory` também receberá `requires_location_map` em `fillable` e `casts`, sem alterar o comportamento enquanto o valor permanecer `false`.

### 4.6 Policies

Arquivos:

```text
app/Policies/InspectionLocationMapPolicy.php
app/Policies/InspectionLocationMarkerPolicy.php
```

Regras:

- `view`: usuário ativo, não superadmin e mesma organização;
- `create/update/delete`: inspeção em `in_progress` ou `in_correction` e usuário responsável como inspector ou preparer;
- operações de marcação reutilizam a autorização do mapa e da avaliação;
- soft delete somente durante estado editável.

### 4.7 Factories

```text
database/factories/InspectionLocationMapFactory.php
database/factories/InspectionLocationMarkerFactory.php
```

### 4.8 Testes da fatia

```text
tests/Feature/InspectionLocations/InspectionLocationFoundationTest.php
tests/Feature/InspectionLocations/InspectionLocationPolicyTest.php
```

Casos mínimos:

- migration sobe e reverte isoladamente em SQLite e MySQL;
- relações carregam corretamente;
- tenant estrangeiro recebe `404` ou `403` conforme o contrato atual;
- mapa não aceita inspeção, categoria ou documento incompatível;
- marker não aceita avaliação de outra inspeção;
- múltiplas marcações para a mesma avaliação são permitidas;
- registros históricos não podem ser editados.

### 4.9 Gate de saída

- migrations e rollback aprovados;
- Models, Policies e factories cobertos;
- nenhuma rota operacional nova;
- suíte atual permanece verde;
- `requires_location_map` continua desativado.

### 4.10 Commit sugerido

```text
feat: add inspection location map foundation
```

---

## 5. Fatia 2 — Origem e processamento da imagem-base

### 5.1 Objetivo

Permitir criar um mapa a partir de um documento referenciado ou de um upload privado e produzir a imagem-base usada pelo editor.

### 5.2 Configuração

Adicionar disco:

```text
inspection_maps
```

Arquivo:

```text
config/filesystems.php
```

Variável de produção prevista:

```text
INSPECTION_MAPS_ROOT=/data/vistoria/inspection-maps
```

### 5.3 Requests

```text
app/Http/Requests/InspectionLocations/StoreInspectionLocationMapRequest.php
app/Http/Requests/InspectionLocations/UpdateInspectionLocationMapRequest.php
app/Http/Requests/InspectionLocations/StoreInspectionLocationMapSourceRequest.php
```

Validações iniciais:

- título até 200 caracteres;
- categoria ativa da organização;
- documento pertencente ao equipamento e referenciado na inspeção;
- upload em PDF, PNG, JPEG ou WEBP;
- limite inicial de 50 MB;
- `source_page >= 1`;
- recorte com valores normalizados;
- rejeição de arquivos sem MIME confirmado pelo servidor.

### 5.4 Actions

```text
app/Actions/InspectionLocations/CreateInspectionLocationMap.php
app/Actions/InspectionLocations/UpdateInspectionLocationMap.php
app/Actions/InspectionLocations/DeleteInspectionLocationMap.php
app/Actions/InspectionLocations/StoreInspectionLocationMapSource.php
app/Actions/InspectionLocations/RetryInspectionLocationMapProcessing.php
app/Console/Commands/CleanupDeletedInspectionLocationMaps.php
```

### 5.5 Job

```text
app/Jobs/ProcessInspectionLocationMap.php
```

Responsabilidades:

- resolver novamente o mapa pelo ID;
- marcar como `processing`;
- abrir imagem ou página do PDF;
- validar dimensões e quantidade de pixels;
- aplicar recorte quando informado;
- gerar WEBP de alta resolução para o editor;
- registrar dimensões, tamanho e checksum;
- marcar como `ready`;
- preservar o original quando a origem for upload;
- marcar `failed` e salvar mensagem sanitizada em caso de erro.

Configuração inicial da imagem-base:

```text
maior lado: 3200 px
formato: WEBP
qualidade: 88
```

O Job deverá validar a disponibilidade de Imagick e suporte a PDF no ambiente. Ghostscript e a policy do ImageMagick entrarão no checklist de deploy.

O comando de limpeza purgará arquivos somente após a janela inicial de 30 dias e nunca removerá assets de inspeções aprovadas, com relatório gerado ou liberadas. Ele será agendado em `bootstrap/app.php`.

### 5.6 Controllers e rotas

```text
app/Http/Controllers/InspectionLocationMapController.php
app/Http/Controllers/InspectionLocationMapAssetController.php
```

Rotas:

```text
POST   inspections/{inspection}/location-maps
PUT    inspection-location-maps/{map}
DELETE inspection-location-maps/{map}
POST   inspection-location-maps/{map}/source
POST   inspection-location-maps/{map}/retry
GET    inspection-location-maps/{map}/background
```

O asset controller deverá fazer streaming privado, com cache privado e Policy.

`InspectionReferenceDocumentController::destroy` também será protegido: um documento usado como origem por mapa não poderá ser removido da inspeção até que o mapa seja alterado ou removido em estado editável.

### 5.7 Resolução tenant-scoped

Adicionar em `ResolvesTenantStructure`:

```text
tenantInspectionLocationMap()
tenantInspectionLocationMarker()
```

### 5.8 Testes da fatia

```text
tests/Feature/InspectionLocations/InspectionLocationMapRoutesTest.php
tests/Feature/InspectionLocations/InspectionLocationMapProcessingTest.php
```

Cobertura:

- criação com upload;
- criação com documento referenciado;
- rejeição de documento não selecionado na inspeção;
- bloqueio da remoção de documento utilizado por mapa;
- processamento de imagem real;
- processamento da página correta de PDF;
- recorte normalizado;
- retry após falha;
- acesso privado;
- remoção antes da revisão;
- bloqueio de remoção após revisão;
- soft delete preserva arquivos durante a janela de retenção;
- limpeza física somente após a janela de retenção.

### 5.9 Gate de saída

- mapa pode chegar a `ready` por ambas as origens;
- imagem-base só é acessível por usuário autorizado;
- fila `images` processa sem interferir nas fotos;
- falhas são recuperáveis;
- nenhum mapa é ainda obrigatório.

### 5.10 Commit sugerido

```text
feat: add private inspection map processing
```

---

## 6. Fatia 3 — Read model e gestão de mapas

### 6.1 Objetivo

Substituir a lista visual provisória da aba Localização por agrupamento real de categorias e mapas, ainda sem edição vetorial completa.

### 6.2 Serviço de apresentação

Criar:

```text
app/Services/InspectionLocations/InspectionLocationPresenter.php
```

Contrato principal:

```text
categories[]
├── category
├── maps[]
│   ├── source
│   ├── background_url
│   ├── processing_status
│   ├── marker_count
│   └── capabilities
└── unlocated_assessments[]
```

O presenter atual continuará responsável pelas demais abas. A aba `locations` passará a consumir o novo serviço apenas quando existirem mapas reais; o fallback demo será explicitamente identificado e removido na Fatia 6.

### 6.3 Páginas e componentes

```text
resources/js/pages/InspectionLocationMaps/Index.vue
resources/js/pages/InspectionLocationMaps/Create.vue
resources/js/pages/InspectionLocationMaps/Edit.vue
resources/js/components/domain/inspection-locations/InspectionLocationMapForm.vue
resources/js/components/domain/inspection-locations/InspectionLocationMapCard.vue
resources/js/components/domain/inspection-locations/InspectionLocationMapStatus.vue
```

Fluxo:

- aba Localização abre o índice real;
- usuário cria mapa para uma categoria;
- escolhe documento ou upload;
- acompanha `pending`, `processing`, `ready` ou `failed`;
- abre editor quando o background estiver pronto;
- vê avaliações ainda sem marcação.

### 6.4 Testes da fatia

```text
tests/Feature/InspectionLocations/InspectionLocationPagesTest.php
```

- agrupamento por categoria;
- ordem estável;
- estados de processamento;
- lista de avaliações sem marcação;
- capabilities por usuário e estado;
- ausência de dados estrangeiros;
- comportamento vazio sem mapas.

### 6.5 Gate de saída

- CRUD de mapas utilizável sem editor vetorial;
- aba Localização não assume mais um desenho compartilhado pela primeira avaria;
- experiência responsiva em 375, 768 e 1440 px;
- nenhuma alteração no relatório.

### 6.6 Commit sugerido

```text
feat: add inspection location map management
```

---

## 7. Fatia 4 — Editor vetorial e marcações

### 7.1 Objetivo

Permitir marcar uma ou várias regiões, associar uma avaliação publicada e gerar automaticamente sua chamada padronizada.

### 7.2 Validador de geometria

Criar:

```text
app/Services/InspectionLocations/InspectionLocationGeometryValidator.php
```

Regras da versão 1:

- payload com tamanho limitado;
- `version = 1`;
- uma a cinquenta formas por marcação;
- todos os números finitos entre `0` e `1`;
- ponto com `x/y`;
- retângulo com largura e altura positivas;
- polígono com no mínimo três pontos;
- polyline com no mínimo dois pontos;
- propriedades antigas de `callout` aceitas para compatibilidade, mas ignoradas na renderização;
- propriedades desconhecidas rejeitadas;
- estilo limitado a uma whitelist.

### 7.3 Actions e Requests

```text
app/Actions/InspectionLocations/CreateInspectionLocationMarker.php
app/Actions/InspectionLocations/UpdateInspectionLocationMarker.php
app/Actions/InspectionLocations/DeleteInspectionLocationMarker.php
app/Actions/InspectionLocations/ReorderInspectionLocationMarkers.php

app/Http/Requests/InspectionLocations/StoreInspectionLocationMarkerRequest.php
app/Http/Requests/InspectionLocations/UpdateInspectionLocationMarkerRequest.php
```

As Actions validarão novamente:

- tenant;
- inspeção e equipamento;
- estado editável;
- categoria da avaria;
- avaliação pertencente à inspeção;
- avaliação publicada e com condição localizável;
- mapa pronto.

Updates exigirão `lock_version`. Quando outro usuário tiver salvo o mapa ou a marcação antes, a operação será rejeitada com mensagem para recarregar o editor, sem sobrescrever a versão mais recente.

### 7.4 Frontend

```text
resources/js/pages/InspectionLocationMaps/Editor.vue
resources/js/components/domain/inspection-locations/InspectionLocationMapEditor.vue
resources/js/components/domain/inspection-locations/InspectionLocationToolbar.vue
resources/js/components/domain/inspection-locations/InspectionLocationMarkerPanel.vue
resources/js/components/domain/inspection-locations/InspectionLocationAssessmentPicker.vue
```

Ordem de implementação do editor:

1. zoom e pan;
2. seleção de avaliação;
3. ponto e retângulo;
4. polígono e polyline;
5. múltiplas formas por marcação;
6. legenda automática de fotos e legenda adicional de até 240 caracteres;
7. seleção, edição e remoção;
8. teclado, foco e feedback de erro.

O SVG utilizará `viewBox` estável. Coordenadas de ponteiro serão convertidas para valores normalizados antes do envio.

A marcação e sua legenda serão renderizadas por um componente SVG compartilhado entre editor e relatório. A legenda ficará abaixo do conjunto de formas, com exceção automática na borda inferior, e receberá deslocamentos determinísticos para evitar sobreposição. O posicionamento manual antigo não fará parte do fluxo.

Após criar ou atualizar uma marcação, o editor retornará à lista inicial e descartará somente o estado temporário. Exclusões e atualizações sempre enviarão as versões mais recentes recebidas nas propriedades Inertia; conflitos manterão o bloqueio otimista e oferecerão recarregamento explícito.

Avaliações vinculadas precisam permanecer publicadas. O retorno a rascunho será bloqueado enquanto houver marcações em qualquer mapa da inspeção; correções utilizarão “Salvar e republicar”, preservando os vínculos.

### 7.5 Testes da fatia

```text
tests/Unit/InspectionLocations/InspectionLocationGeometryValidatorTest.php
tests/Feature/InspectionLocations/InspectionLocationMarkerRoutesTest.php
```

Casos:

- todos os tipos de forma válidos;
- NaN, infinito e valores fora do canvas rejeitados;
- payload excessivo rejeitado;
- várias formas na mesma marcação;
- várias marcações para a mesma avaliação;
- categoria incompatível rejeitada;
- atualização concorrente protegida por `lock_version`;
- edição bloqueada fora dos estados permitidos.

### 7.6 Gate de saída

- editor cobre os casos observados nas páginas 20, 23 e 29;
- geometria permanece alinhada após redimensionamento;
- operações principais funcionam por mouse e toque;
- fluxo básico possui alternativa de teclado;
- persistência e reabertura não alteram coordenadas.

### 7.7 Commit sugerido

```text
feat: add inspection location marker editor
```

---

## 8. Fatia 5 — Fotografias, ordem e reinspeção

### 8.1 Objetivo

Selecionar fotografias por marcação, calcular numeração determinística e permitir copiar mapas anteriores sem reutilizar registros históricos.

### 8.2 Actions e serviços

```text
app/Actions/InspectionLocations/SyncInspectionLocationMarkerPhotos.php
app/Actions/InspectionLocations/ReorderInspectionLocationMaps.php
app/Actions/InspectionLocations/CopyInspectionLocationMapsFromPreviousInspection.php
app/Services/InspectionLocations/InspectionLocationPhotoNumbering.php
```

Regras de fotos:

- somente fotos da avaliação da marcação;
- somente fotos não removidas;
- foto pendente pode ser selecionada durante edição, mas impede revisão;
- ordem explícita na pivot;
- a mesma foto pode aparecer em diferentes marcações;
- a numeração global deduplica a fotografia no relatório;
- intervalos são formatados somente na apresentação.

Regras de cópia:

- novos mapas e novas marcações;
- mesmo documento de referência apenas se continuar anexado à inspeção atual;
- background pode ser regenerado;
- geometria é copiada;
- avaliações são resolvidas pelo mesmo `defect_id` na inspeção atual;
- fotos nunca são copiadas;
- marcações sem avaliação atual ficam pendentes para resolução manual.

### 8.3 UI

Adicionar ao painel da marcação:

- miniaturas das fotos da avaliação;
- seleção múltipla;
- ordem por drag-and-drop acessível ou botões subir/descer;
- previsão de numeração;
- alertas de processamento.

Adicionar ao índice:

- ação “Copiar mapas da inspeção anterior”;
- resumo da cópia antes da confirmação;
- pendências de resolução depois da cópia.

### 8.4 Testes da fatia

```text
tests/Feature/InspectionLocations/InspectionLocationMarkerPhotoTest.php
tests/Feature/InspectionLocations/CopyInspectionLocationMapsTest.php
tests/Unit/InspectionLocations/InspectionLocationPhotoNumberingTest.php
```

- foto estrangeira rejeitada;
- foto de outra avaliação rejeitada;
- ordem persistida;
- intervalos unitários, contínuos e descontínuos;
- foto reutilizada não recebe dois números globais;
- cópia cria novos IDs;
- inspeção anterior permanece imutável;
- avaliação atual é resolvida corretamente;
- cópia parcial produz pendências explícitas.

### 8.5 Gate de saída

- relação mapa → marcação → avaliação → fotos completa;
- numeração reproduz casos como `1 E 2` e `5 A 8`;
- reinspeção não altera histórico;
- nenhuma exigência de cobertura ativa ainda.

### 8.6 Commit sugerido

```text
feat: link location markers to photos and reinspections
```

---

## 9. Fatia 6 — Cobertura, relatório e ativação

> Implementada em 09/08/2026. A exigência permanece desativada por padrão e só pode ser ligada por categoria após análise de impacto e confirmação explícita.

### 9.1 Objetivo

Transformar mapas reais em fonte de verdade da aba Localização e do relatório e ativar o bloqueio configurável antes da revisão.

### 9.2 Validador de cobertura

Criar:

```text
app/Services/InspectionLocations/InspectionLocationCoverageValidator.php
```

Integrar em:

```text
app/Actions/Inspections/SubmitInspectionForReview.php
```

Ordem dos validadores:

```text
ReinspectionCoverageValidator
→ AssessmentPhotoCoverageValidator
→ InspectionLocationCoverageValidator
```

O validador retorna imediatamente quando nenhuma categoria avaliada possui `requires_location_map = true`.

Para categorias ativas, bloqueará:

- mapa ainda não processado;
- avaliação ativa sem marcação;
- geometria inválida;
- marcação sem fotos selecionadas quando a avaliação exige evidência;
- foto selecionada não pronta;
- inconsistência de inspeção, equipamento ou categoria.

### 9.3 Read model definitivo

O `InspectionLocationPresenter` passará a alimentar:

- aba Localização;
- prévia do relatório;
- seção de documentação fotográfica;
- indicadores de cobertura.

O método provisório `ViewFirstDemoPresenter::locations()` deixará de ser fonte operacional. O fallback demonstrativo poderá permanecer somente para o seeder legado, identificado por flag explícita.

### 9.4 Compositor de localização

Criar:

```text
app/Services/InspectionLocations/InspectionLocationReportComposer.php
```

Responsabilidades:

- agrupar por categoria;
- ordenar mapas e marcações;
- calcular números e intervalos das fotos;
- permitir mais de um mapa na mesma folha;
- produzir contrato neutro para prévia HTML e futuro PDF;
- não persistir páginas.

### 9.5 Snapshot

Criar:

```text
app/Actions/InspectionLocations/BuildInspectionLocationSnapshot.php
```

Conteúdo mínimo:

- categoria e códigos;
- mapa, título e ordem;
- referência documental e checksum;
- dimensões e checksum do background;
- geometria e estilo;
- avaliação e avaria;
- fotos, ordem e numeração calculada.

A persistência do snapshot final será conectada ao artefato de relatório no módulo 11. Até lá, os registros ficarão bloqueados pelo workflow.

### 9.6 Ativação por categoria

Atualizar formulários administrativos de categoria:

```text
resources/js/components/domain/classification/DefectCategoryForm.vue
app/Http/Requests/Classification/StoreDefectCategoryRequest.php
app/Http/Requests/Classification/UpdateDefectCategoryRequest.php
```

Campo:

```text
Exigir mapa de localização antes da revisão
```

Antes de ativar, a interface exibirá:

- quantidade de inspeções abertas afetadas;
- avaliações ainda sem marcação;
- confirmação explícita.

### 9.7 Seeder demonstrativo

Atualizar o cenário View First para criar mapas e marcações correspondentes às evidências conhecidas. Só então o fallback visual será removido da navegação principal.

### 9.8 Testes da fatia

```text
tests/Feature/InspectionLocations/InspectionLocationCoverageTest.php
tests/Feature/InspectionLocations/InspectionLocationReportComposerTest.php
tests/Feature/Seeders/ViewFirstDemoSeederTest.php
```

- categoria desativada não bloqueia revisão;
- categoria ativada bloqueia cobertura incompleta;
- condições dispensadas não exigem marcação;
- correção completa permite revisão;
- agrupamento por categoria;
- dois mapas na mesma folha lógica;
- uma marcação com várias regiões;
- intervalos fotográficos corretos;
- snapshot estável;
- seeder idempotente.

### 9.9 Gate de saída

- mapas reais são fonte de verdade operacional;
- revisão respeita configuração da categoria;
- relatório não reconstrói localização a partir de textos soltos;
- cenário demonstrativo continua navegável;
- ativação não ocorre sem auditoria das inspeções abertas.

### 9.10 Commit sugerido

```text
feat: enforce inspection location coverage and reporting
```

---

## 10. Fatia 7 — Hardening e fechamento

> Implementação concluída em 09/08/2026. O módulo permanece em validação até a execução dos gates ambientais e manuais listados em 10.8.

### 10.1 Segurança

- conferir todas as rotas com usuário de outro tenant;
- testar acesso direto aos assets;
- limitar tamanho e complexidade da geometria;
- sanitizar erros do processador;
- validar decompression bombs;
- bloquear path traversal;
- confirmar ausência de arquivos em `public/`;
- revisar mass assignment.

### 10.2 Concorrência

- lock ao calcular posições;
- atualização otimista de marcações;
- reorder atômico;
- Job idempotente;
- retry sem duplicar derivados;
- cópia de reinspeção idempotente ou protegida por confirmação única.

### 10.3 Performance

- eager loading por inspeção e categoria;
- evitar N+1 em mapas, markers e fotos;
- carregar backgrounds sob demanda;
- thumbnail no índice e imagem maior somente no editor;
- limitar número de mapas e formas retornados por request;
- medir payload do editor e do relatório.

### 10.4 Acessibilidade e responsividade

- ações completas por teclado fora do canvas;
- lista textual equivalente às marcações;
- foco visível;
- contraste das chamadas;
- mensagens não dependentes apenas de cor;
- editor utilizável em tablet;
- celular permite consulta e ajustes simples, sem exigir precisão de desktop.

### 10.5 Gates técnicos

```text
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pint --test
npm run build
```

Também executar:

- migration e rollback isolados em MySQL;
- processamento real de PNG, JPEG, WEBP e PDF;
- Job real com fila;
- validação manual em 375, 768, 1024 e 1440 px;
- impressão da prévia;
- inspeção sem mapa com categoria desativada;
- inspeção com mapa obrigatório completa e incompleta;
- reinspeção com cópia de mapas.

### 10.6 Gate de fechamento

- todos os critérios do documento 08A atendidos;
- documentação atualizada para o código real;
- roadmap atualizado;
- nenhuma dependência do fallback operacional antigo;
- commit(s) separados e revisáveis;
- decisão explícita sobre ativação CIVIL em cada organização.

### 10.7 Commit sugerido

```text
chore: harden inspection location maps
```

### 10.8 Evidências e pendências de fechamento

Evidências automatizadas executadas em 09/08/2026:

- `php artisan migrate:fresh --seed` aprovado com banco e storages privados temporários;
- rollback e reaplicação das duas migrations 08A aprovados em SQLite isolado;
- processamento real de PNG, JPEG, WEBP e PDF aprovado com geração de background e thumbnail WEBP;
- suíte completa: 176 testes, 175 aprovados, 1 ignorado e 1.932 assertions;
- suíte focada de localização, classificação, seeder, View First e avarias: 91 testes e 1.047 assertions;
- `composer validate --strict --no-check-publish`, `vendor/bin/pint --test`, build de produção com Node 22.21.1 e `git diff --check` aprovados;
- tenant estrangeiro, acesso direto, path traversal, mass assignment, decompression bomb, limites, concorrência, idempotência, limpeza e payload possuem cobertura automatizada;
- a fonte operacional antiga de localização foi removida da inspeção e da prévia do relatório.

Permanecem pendentes e não são considerados validados por esta execução:

- migration e rollback em MySQL isolado;
- Job consumido por worker assíncrono real na fila `images`;
- validação manual em 375, 768, 1024 e 1440 px;
- impressão da prévia;
- decisão de ativação de CIVIL por organização;
- criação dos commits revisáveis.

---

## 11. Matriz de dependências

| Fatia | Depende de | Pode ser implantada sem ativar bloqueio |
|---|---|---|
| 1. Fundação | 07, 07A e 08 | Sim |
| 2. Processamento | Fatia 1 e fila `images` | Sim |
| 3. Gestão | Fatias 1 e 2 | Sim |
| 4. Editor | Fatias 1 a 3 | Sim |
| 5. Fotos/reinspeção | Fatia 4 e módulo 08 | Sim |
| 6. Cobertura/relatório | Fatias 1 a 5 | Sim, enquanto a categoria estiver desativada |
| 7. Hardening | Todas | Não se aplica |

---

## 12. Estratégia de rollout

### 12.1 Antes do deploy

- garantir Node compatível com `package.json`;
- validar Imagick, WEBP, PDF e Ghostscript;
- criar diretório persistente `inspection_maps`;
- definir backup;
- confirmar queue worker para `images`;
- registrar métricas de falha dos Jobs.

### 12.2 Deploy estrutural

- executar migration;
- manter `requires_location_map = false`;
- validar CRUD e processamento;
- não alterar fluxo de revisão.

### 12.3 Deploy operacional

- liberar editor;
- criar mapas de inspeções abertas;
- atualizar seeder demo;
- validar prévia do relatório.

### 12.4 Ativação

- auditar inspeções abertas;
- resolver avaliações sem mapa;
- ativar a categoria;
- testar um envio para verificação;
- acompanhar erros e filas.

### 12.5 Reversão segura

- desativar `requires_location_map`;
- manter dados e arquivos;
- suspender novos processamentos se necessário;
- não executar `down()` em produção como mecanismo de rollback funcional.

---

## 13. Ordem de execução recomendada

```text
1. Fundação de dados
2. Origem e processamento
3. Gestão dos mapas
4. Editor vetorial
5. Fotos e reinspeção
6. Cobertura e relatório
7. Hardening e ativação
```

Cada fatia deve terminar com testes próprios e regressão completa. A próxima fatia só começa quando o gate da anterior estiver comprovado.

---

## 14. Próxima tarefa de fechamento

Executar os gates ambientais e manuais ainda pendentes, sem ativar `requires_location_map` automaticamente. Com as evidências aprovadas, registrar a decisão por organização, criar commits revisáveis e avançar para os módulos 10 e 11.
