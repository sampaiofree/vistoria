# 08A — Mapas e Localização da Inspeção

> Status em 09/08/2026: modelo de domínio e Fatias 1 a 7 implementados, com validação automatizada aprovada. Permanecem pendentes a validação isolada em MySQL, a execução com worker assíncrono real, a validação manual responsiva/impressão e o fechamento em commit. Este documento substitui a leitura provisória em que cada avaria era apresentada como uma localização isolada.

## 1. Objetivo

Modelar a localização técnica como um artefato da inspeção, associado a uma categoria e capaz de agrupar várias avaliações de avarias sobre um ou mais desenhos-base.

O modelo deve preservar separadamente:

- o mapa utilizado na inspeção;
- o documento técnico que originou o mapa;
- as regiões e chamadas gráficas de cada avaliação;
- as fotografias relacionadas a cada marcação;
- a composição final gerada para o relatório.

Não será adicionado um campo simples `location_id` em `defects`.

```text
Inspection
├── InspectionLocationMap
│   ├── base técnica / imagem
│   └── InspectionLocationMarker
│       ├── DefectAssessment
│       ├── uma ou mais regiões
│       └── AssessmentPhotos
└── relatório gerado
    └── folhas compostas a partir dos mapas
```

---

## 2. Evidências analisadas

Foram analisados:

- `U0306VT-G-6RI002_R-04.pdf`;
- `RELATÓRIO - LOC. DOC. FOTO. U03-06VT002.xlsx`.

### 2.1 Página 20

A folha `LOCALIZAÇÃO FOTOGRÁFICA - CIVIL` utiliza o projeto `U030600-M-560002` e reúne três avarias distintas:

```text
VT009-CV-001 → fotos 1 e 2
VT009-CV-002 → fotos 3 e 4
VT009-CV-003 → fotos 5 a 8
```

O desenho funciona como agrupador e cada avaria possui sua própria região e chamada fotográfica.

### 2.2 Página 23

Uma única folha do relatório reúne seis avarias, de `VT009-CV-004` a `VT009-CV-009`, e utiliza dois projetos de referência:

```text
U030600-M-560002
U030600-S-551729
```

Isso comprova que a folha do relatório e o mapa técnico não são a mesma entidade. Dois mapas podem ser compostos na mesma página.

### 2.3 Página 29

O mapa reúne `VT009-CV-011` a `VT009-CV-014`. A chamada de uma avaria pode apontar para várias regiões do mesmo desenho, o que impede representar a localização apenas como um ponto ou uma FK simples.

### 2.4 Estrutura da planilha

A planilha separa abas de localização e documentação fotográfica, por exemplo:

```text
L.CIVIL
D.CIVIL
L.CIVIL 2
D.CIVIL 2
L.TAC
TAC 1
L.REC
REC (1)
```

Essa estrutura reforça que localização, avaliação e fotografias são conceitos relacionados, mas distintos.

---

## 3. Decisões de domínio

### 3.1 A categoria não pertence à inspeção

`DefectCategory` continua sendo um catálogo da organização. O mapa apenas referencia uma categoria para organizar e validar seu conteúdo.

```text
Organization
└── DefectCategory

Inspection
└── InspectionLocationMap
    └── defect_category_id
```

### 3.2 O mapa pertence à inspeção

O mapa é uma representação técnica produzida ou selecionada para uma inspeção específica. Ele não pertence à avaria permanente.

### 3.3 A marcação referencia a avaliação

`InspectionLocationMarker` referencia `DefectAssessment`, não apenas `Defect`, porque:

- a localização é observada dentro de uma inspeção;
- uma reinspeção pode utilizar outro desenho ou outra geometria;
- o histórico de cada ciclo precisa permanecer imutável;
- a classificação e as fotografias utilizadas pertencem à avaliação.

### 3.4 Uma avaliação pode possuir várias marcações

Uma avaliação pode aparecer:

- em mais de um mapa;
- em várias regiões do mesmo mapa;
- com grupos diferentes de fotografias.

Uma marcação representa uma chamada visual. Sua geometria pode conter uma ou várias formas. Quando forem necessárias chamadas ou grupos de fotos distintos, devem ser criadas marcações distintas.

### 3.5 A folha do relatório não será persistida no MVP

O domínio persistirá mapas e marcações. O gerador do relatório agrupará os mapas em folhas conforme espaço, categoria e ordem.

Uma entidade `InspectionLocationSheet` somente será criada futuramente se houver necessidade comprovada de editar manualmente a composição exata de cada página.

### 3.6 A descrição textual continua existindo

`DefectAssessment.location_description` continua sendo a descrição humana da posição observada.

Exemplo:

```text
Face norte do pedestal, junto ao chumbador B.
```

O mapa complementa essa descrição; não a substitui.

### 3.7 As fotografias continuam pertencendo à avaliação

`AssessmentPhoto` permanece vinculada à avaliação. A relação com a marcação apenas seleciona quais fotografias serão mencionadas naquela chamada visual.

### 3.8 A numeração fotográfica será calculada

Textos como `FOTOS: 5 A 8` não serão persistidos como fonte de verdade.

A numeração será calculada de forma determinística por:

```text
ordem da categoria
→ ordem do mapa
→ ordem da marcação
→ ordem das fotografias selecionadas
```

Quando o relatório for aprovado, a numeração e a composição utilizadas serão preservadas em snapshot.

### 3.9 A exigência será configurável por categoria

`DefectCategory` receberá a opção `requires_location_map`. A migration utilizará `false` como padrão para não bloquear inspeções abertas antes da implantação completa.

Não haverá condição hard-coded para `CV`, `TAC` ou `REC`. A organização ativará a exigência quando os mapas daquela categoria estiverem prontos.

---

## 4. Escopo incluído

- mapas privados por inspeção e categoria;
- origem em documento técnico da inspeção ou upload avulso;
- imagem-base renderizada para o editor;
- regiões vetoriais em coordenadas normalizadas;
- chamadas e legendas com posicionamento automático e padronizado;
- múltiplas regiões na mesma marcação;
- várias marcações por avaliação;
- seleção de fotografias por marcação;
- ordenação de mapas, marcações e fotografias;
- cópia assistida para reinspeção;
- validação antes do envio para verificação;
- integração com a aba de localização e com o relatório;
- isolamento multiempresa;
- snapshots para auditoria.

---

## 5. Fora do escopo

- edição de arquivos CAD;
- criação de desenhos técnicos completos;
- alteração do documento original;
- OCR ou reconhecimento automático de componentes;
- identificação automática de avarias por IA;
- georreferenciamento GIS;
- colaboração simultânea no editor;
- composição manual de páginas do relatório;
- workflows técnicos específicos de TAC ou REC.

O editor do MVP será um anotador de mapas, não um editor gráfico genérico.

---

## 6. Modelo conceitual

```text
Inspection 1 ──────── N InspectionLocationMap
DefectCategory 1 ─── N InspectionLocationMap
EquipmentDocument 0 ─ N InspectionLocationMap

InspectionLocationMap 1 ─ N InspectionLocationMarker
DefectAssessment 1 ────── N InspectionLocationMarker

InspectionLocationMarker N ─ N AssessmentPhoto
```

Regras adicionais:

- mapa e avaliação devem pertencer à mesma inspeção;
- mapa e avaliação devem pertencer ao mesmo equipamento;
- a categoria do mapa deve ser a categoria permanente da avaria;
- a foto selecionada deve pertencer à avaliação da marcação.

---

## 7. Estrutura de dados proposta

### 7.0 Extensão de `defect_categories`

```text
requires_location_map boolean default false
```

Esse campo controla a cobertura no envio para verificação. Ele não altera a categoria permanente das avarias existentes.

### 7.1 `inspection_location_maps`

Campos principais:

```text
id
public_id
organization_id
equipment_id
inspection_id
defect_category_id
equipment_document_id nullable
title
description nullable
source_kind
source_page nullable
source_crop nullable json
reference_snapshot nullable json
source_disk nullable
source_path nullable
source_mime_type nullable
source_size nullable
source_checksum nullable
background_disk
background_path nullable
background_mime_type nullable
background_size nullable
background_width nullable
background_height nullable
background_checksum nullable
processing_status
processing_error nullable
processed_at nullable
geometry_schema_version
position
lock_version
created_by
updated_by
timestamps
deleted_at nullable
```

`source_kind` terá inicialmente:

```text
reference_document
upload
```

Quando a origem for um documento técnico, ele deverá estar vinculado à inspeção em `inspection_reference_documents`.

Quando a origem for um upload, os campos `source_*` preservarão o arquivo recebido. Os campos `background_*` representam a imagem normalizada utilizada pelo editor e só serão preenchidos após o processamento.

`processing_status` terá:

```text
pending
processing
ready
failed
```

`reference_snapshot` preservará pelo menos:

```json
{
  "document_public_id": "...",
  "document_number": "U030600-M-560002",
  "revision": "...",
  "checksum": "...",
  "source_page": 1
}
```

### 7.2 `inspection_location_markers`

Campos principais:

```text
id
public_id
organization_id
equipment_id
inspection_id
inspection_location_map_id
defect_assessment_id
label nullable (legenda adicional, até 240 caracteres)
geometry json
style nullable json
position
lock_version
created_by
updated_by
timestamps
deleted_at nullable
```

`geometry` será versionado e utilizará coordenadas normalizadas entre `0` e `1`, independentes da resolução da imagem.

Exemplo conceitual:

```json
{
  "version": 1,
  "shapes": [
    {
      "type": "polygon",
      "points": [[0.25, 0.32], [0.41, 0.32], [0.40, 0.47]]
    },
    {
      "type": "rectangle",
      "x": 0.62,
      "y": 0.20,
      "width": 0.12,
      "height": 0.25
    }
  ]
}
```

A posição da legenda não é editável. Editor e relatório utilizam a mesma renderização SVG: a chamada fica centralizada 12 unidades abaixo do limite das formas, ou acima quando não houver espaço na borda inferior. Colisões entre legendas recebem deslocamentos pequenos e determinísticos. Valores antigos de `geometry.callout` são preservados, mas ignorados na apresentação.

`label` armazena somente a legenda adicional opcional. A primeira linha (`FOTOS: ...`) é calculada a partir das fotografias relacionadas; o texto adicional aparece em até três linhas abaixo dela.

Tipos iniciais:

```text
point
rectangle
polygon
polyline
```

### 7.3 `inspection_location_marker_photos`

Campos principais:

```text
id
organization_id
inspection_id
inspection_location_marker_id
assessment_photo_id
position
created_at
```

Regras:

- não altera a propriedade da fotografia;
- uma foto pode ser referenciada por mais de uma marcação;
- a mesma foto não pode se repetir dentro da mesma marcação;
- ao criar a primeira marcação, a interface pode selecionar por padrão todas as fotos prontas da avaliação;
- antes da revisão, toda fotografia selecionada deve estar com status `ready`.

---

## 8. Armazenamento

Será criado um disco privado específico:

```text
inspection_maps
```

Estrutura lógica:

```text
organizations/{organization_id}/
└── inspections/{inspection_public_id}/
    └── maps/{map_public_id}/
        ├── {ulid-da-origem}.ext
        └── derivatives/{hash-da-origem}/
            ├── background.webp
            └── thumbnail.webp
```

Regras:

- somente caminhos relativos no banco;
- nenhuma imagem em `public/`;
- acesso sempre autorizado pelo Laravel;
- o arquivo de origem não será sobrescrito;
- a prévia anotada será derivada das geometrias persistidas;
- a geometria vetorial continuará sendo a fonte de verdade.

---

## 9. Regras de edição e histórico

### 9.1 Estados editáveis

Mapas e marcações podem ser alterados somente quando a inspeção estiver em:

```text
in_progress
in_correction
```

### 9.2 Envio para verificação

Para avaliações que exigem evidência de localização, o envio será bloqueado quando houver:

- mapa sem imagem-base pronta;
- marcação sem geometria válida;
- avaliação sem marcação;
- marcação vinculada a categoria incompatível;
- fotografia selecionada ainda não processada;
- referência documental removida ou incompatível.

A obrigatoriedade será configurada por categoria. Quando `requires_location_map` estiver ativo, avaliações com condição ativa exigirão ao menos uma marcação.

Condições inicialmente dispensadas:

```text
not_located
not_inspected
```

Para `repaired`, a marcação é necessária quando houver evidência de reparo no relatório.

### 9.3 Após revisão e aprovação

- mapas e marcações ficam bloqueados;
- nenhuma exclusão física é permitida;
- registros removidos durante a edição permanecem recuperáveis por soft delete;
- arquivos de registros removidos só podem ser purgados após a janela de retenção definida no deploy;
- correções exigem retorno formal para `in_correction`;
- o relatório aprovado preserva snapshot da referência, geometria, fotos e ordem.

### 9.4 Reinspeção

Mapas anteriores podem ser copiados como ponto de partida, mas serão criados novos registros.

```text
mapa anterior
→ cópia em rascunho
→ novas marcações ligadas às avaliações atuais
```

Nenhuma marcação histórica será transferida mantendo o mesmo `defect_assessment_id`.

---

## 10. Actions e endpoints previstos

Actions:

```text
CreateInspectionLocationMap
UpdateInspectionLocationMap
DeleteInspectionLocationMap
StoreInspectionLocationMapSource
RenderInspectionLocationMapBackground
CreateInspectionLocationMarker
UpdateInspectionLocationMarker
DeleteInspectionLocationMarker
SyncInspectionLocationMarkerPhotos
ReorderInspectionLocationMaps
ReorderInspectionLocationMarkers
CopyInspectionLocationMapsFromPreviousInspection
ValidateInspectionLocationCoverage
BuildInspectionLocationSnapshot
```

Rotas conceituais:

```text
GET    /inspections/{inspection}/location-maps
POST   /inspections/{inspection}/location-maps
GET    /inspection-location-maps/{map}
PUT    /inspection-location-maps/{map}
DELETE /inspection-location-maps/{map}

POST   /inspection-location-maps/{map}/markers
PUT    /inspection-location-markers/{marker}
DELETE /inspection-location-markers/{marker}
PUT    /inspection-location-markers/{marker}/photos
```

Todas as rotas devem resolver os registros novamente pelo tenant atual.

---

## 11. Telas envolvidas

### 11.1 Aba Localização da inspeção

A aba deixará de projetar uma localização por avaria e passará a exibir:

```text
Categoria
└── Mapas ordenados
    ├── título e referência
    ├── imagem-base
    ├── marcações
    └── avaliações ainda sem marcação
```

### 11.2 Editor de mapa

Funcionalidades do MVP:

- selecionar documento ou enviar imagem;
- adicionar ponto, retângulo, polígono ou linha;
- selecionar uma avaliação publicada, localizável e da mesma categoria;
- informar uma legenda adicional opcional;
- selecionar fotografias;
- reordenar marcações;
- visualizar em tempo real a mesma marcação e legenda usadas no relatório.

Uma avaliação publicada que possua marcações não pode retornar diretamente a rascunho. Ela continua editável pelo fluxo “Salvar e republicar”; os vínculos são preservados e o snapshot é atualizado. Para retornar a rascunho, todas as marcações da avaliação devem ser removidas dos mapas da inspeção.

### 11.3 Tela da avaliação

Exibirá:

- mapas em que a avaliação aparece;
- miniatura de cada marcação;
- ação para criar ou editar marcação enquanto permitido.

### 11.4 Relatório

O relatório será estruturado por:

```text
Categoria
├── mapas de localização
└── documentação fotográfica
```

O compositor poderá colocar mais de um mapa na mesma folha, reproduzindo o caso observado na página 23.

---

## 12. Implementação em etapas

### Etapa 1 — Fundação de dados

- migrations aditivas;
- models e relações;
- Policies;
- validação de tenant, inspeção, equipamento e categoria;
- factories e testes de domínio.

### Etapa 2 — Origem e imagem-base

- seleção de documento de referência;
- upload avulso;
- processamento de PDF/imagem para WEBP;
- armazenamento privado;
- acesso autorizado.

### Etapa 3 — Marcações

- editor vetorial mínimo;
- geometrias normalizadas e versionadas;
- associação com avaliação;
- seleção e ordem de fotografias.

### Etapa 4 — Cobertura e reinspeção

- validação antes da revisão;
- lista de avaliações sem marcação;
- cópia assistida dos mapas anteriores;
- bloqueio por estado da inspeção.

### Etapa 5 — Relatório e auditoria

- composição automática das folhas;
- numeração fotográfica determinística;
- snapshot do mapa e das marcações;
- prévia e PDF usando a mesma fonte de dados.

---

## 13. Testes obrigatórios

- isolamento dos mapas por organização;
- mapa vinculado somente à inspeção e equipamento corretos;
- documento-base deve ser referência da inspeção;
- categoria do mapa compatível com a avaria;
- marcação aceita apenas avaliação da mesma inspeção;
- avaliação pode possuir várias marcações;
- marcação pode conter várias regiões;
- foto deve pertencer à avaliação da marcação;
- ordem dos mapas e marcações é estável;
- numeração fotográfica é determinística;
- geometria permanece correta em resoluções diferentes;
- usuário sem permissão não acessa imagem-base;
- edição bloqueada após envio para verificação;
- cobertura incompleta bloqueia verificação;
- cópia para reinspeção cria novos registros;
- relatório agrupa por categoria e aceita vários mapas por folha;
- snapshot não muda quando documento ou cadastro posterior é alterado.

---

## 14. Critérios de aceite

- [x] evidências do relatório analisadas;
- [x] mapa separado de avaria, avaliação, foto e folha do relatório;
- [x] cardinalidades e regras temporais definidas;
- [x] estratégia de geometria definida;
- [x] estratégia de documento-base e snapshot definida;
- [x] estratégia de numeração fotográfica definida;
- [x] comportamento de reinspeção definido;
- [x] plano técnico de implementação definido;
- [x] migrations implementadas e validadas em SQLite isolado;
- [ ] migration e rollback validados em MySQL isolado;
- [x] CRUD de mapas implementado;
- [x] editor de marcações implementado;
- [x] vínculo de fotos implementado;
- [x] validador de cobertura integrado;
- [x] prévia e relatório migrados para mapas reais;
- [x] hardening de segurança, concorrência, limites e assets privados implementado;
- [x] testes automatizados aprovados;
- [ ] validação manual em desktop e celular;
- [ ] commit criado.

---

## 15. Riscos e brechas

### 15.1 Acoplamento com paginação

Persistir páginas cedo demais tornaria o domínio dependente do layout atual do relatório.

Mitigação: persistir mapas e deixar a paginação para o compositor.

### 15.2 Geometria dependente de pixels

Coordenadas absolutas quebrariam ao gerar miniaturas ou PDFs.

Mitigação: coordenadas normalizadas e schema versionado.

### 15.3 Documento alterado após a inspeção

Uma revisão nova pode mudar a imagem sob marcações históricas.

Mitigação: preservar checksum, snapshot e imagem-base utilizada.

### 15.4 Numeração fotográfica instável

Reordenação pode mudar os intervalos exibidos.

Mitigação: ordem explícita e snapshot no fechamento do relatório.

### 15.5 Editor excessivamente complexo

Tentar reproduzir CAD ou Excel atrasaria o MVP.

Mitigação: limitar o editor a imagem-base, formas simples, chamadas e seleção de fotos.

### 15.6 Mapas históricos reutilizados indevidamente

Editar o mesmo registro em reinspeções destruiria rastreabilidade.

Mitigação: cópia para novos registros e vínculo obrigatório às avaliações atuais.

---

## 16. Checklist final

- [x] Validar páginas 20, 23 e 29 do relatório.
- [x] Validar separação das abas `L.*` e `D.*` na planilha.
- [x] Definir `InspectionLocationMap`.
- [x] Definir `InspectionLocationMarker`.
- [x] Definir relação entre marcação e fotos.
- [x] Manter `location_description` na avaliação.
- [x] Não adicionar localização à avaria permanente.
- [x] Não persistir folha do relatório no MVP.
- [x] Planejar rollout aditivo e ativação por categoria.
- [x] Implementar Etapa 1.
- [x] Implementar Etapa 2.
- [x] Implementar Etapa 3.
- [x] Implementar Etapa 4.
- [x] Implementar Etapa 5.

---

## 17. Commit sugerido

Para a decisão documental:

```bash
git add docs/00-INDICE-E-ROADMAP.md docs/07-AVARIAS-E-REINSPECOES.md docs/07A-CATEGORIAS-E-CLASSIFICACOES.md docs/08-FOTOS-E-ARMAZENAMENTO.md docs/08A-MAPAS-E-LOCALIZACAO-DA-INSPECAO.md docs/08A-PLANO-DE-IMPLEMENTACAO.md
git commit -m "docs: define inspection location maps domain"
```

Para a futura implementação, os commits devem ser separados por etapa e não misturados ao fechamento do módulo 08.

---

## 18. Próximo passo

Concluir os gates ambientais e manuais da Fatia 7: migration/rollback em MySQL isolado, processamento por worker real da fila `images`, conferência em 375, 768, 1024 e 1440 px e impressão da prévia. Depois, decidir explicitamente a ativação de `requires_location_map` para CIVIL em cada organização e criar commits revisáveis.

O plano técnico detalhado está em `08A-PLANO-DE-IMPLEMENTACAO.md`.
