# 08A — Mapas e localização da inspeção

## Modelo atual

Um mapa pertence simultaneamente a organização, equipamento, inspeção e categoria.
Uma marcação pertence ao mapa e referencia uma avaliação da mesma inspeção,
equipamento, organização e categoria.

```text
Inspection
└── InspectionLocationMap
    └── InspectionLocationMarker
        ├── DefectAssessment
        └── AssessmentPhoto (N:N ordenado)
```

Mapas e marcações usam `public_id`, soft delete, posição e `lock_version`.

## Permissões e estado editável

Somente um preparador atribuído pode criar ou editar mapas e marcações, e apenas
quando a inspeção está em `in_progress` ou `in_correction`. Leitura é permitida a
usuários ativos do tenant. Superadministradores não acessam o módulo.

A categoria do mapa é um código nativo: `CV`, `TAC` ou `REC`. A avaliação da
marcação precisa pertencer à mesma organização, inspeção, equipamento e categoria.

## Criação e origem

O mapa é criado com título, descrição, categoria e posição. A interface atual
recebe uma imagem PNG, JPEG ou WEBP com até 50 MB. A imagem também deve respeitar:

- no máximo 30.000 px por lado;
- no máximo 80 milhões de pixels;
- recorte normalizado inteiramente dentro da imagem, quando informado.

O upload atual define `source_kind=upload`. O schema conserva campos para
`reference_document`, página de PDF, recorte e snapshot de referência por
compatibilidade histórica, mas a rota atual de nova origem aceita somente imagem.

## Processamento

`ProcessInspectionLocationMap` roda na fila `images`, com três tentativas, timeout
de 180 segundos e backoff de 10, 60 e 300 segundos.

O Job:

1. confirma que o mapa e o checksum ainda representam a origem esperada;
2. reivindica atomicamente um mapa `pending`;
3. valida tamanho e dimensões;
4. lê a primeira imagem ou a página histórica de PDF indicada;
5. aplica recorte, quando existir;
6. gera fundo WEBP com até 3000 px e thumbnail com até 640 px;
7. publica os derivados somente se a origem não mudou;
8. remove o upload temporário após sucesso.

Uma tentativa intermediária devolve o mapa a `pending`. Depois da última falha, o
mapa passa a `failed`, os arquivos temporários controlados são removidos e os
usuários relacionados recebem notificação. A recuperação é feita com outra
imagem; não há endpoint de retry manual.

Os assets são privados e servidos por Controller após tenant e Policy. Validações
de caminho impedem usar dados persistidos inconsistentes para ler ou excluir
arquivos fora da raiz esperada.

## Limites de capacidade

| Recurso | Limite atual |
|---|---:|
| Mapas por inspeção | 100 |
| Mapas por categoria na inspeção | 25 |
| Marcações por mapa | 500 |
| Avaliações carregadas no editor | 1000 |
| Fotografias por avaliação no editor | 100 |
| Formas por marcação | 50 |
| Pontos por polígono ou linha | 100 |
| Tamanho combinado de geometria e estilo | 65.536 bytes |

## Editor e geometria

A geometria tem versão 1 e usa coordenadas normalizadas entre 0 e 1, independentes
da resolução do fundo. Uma marcação contém de uma a cinquenta formas:

- ponto;
- retângulo;
- polígono;
- polilinha.

Uma chamada opcional guarda posição e âncora. O estilo aceita borda, preenchimento,
espessura, opacidade e tracejado. Cores usam hexadecimal; pontos e linhas exigem
borda visível. Propriedades desconhecidas, números não finitos e formas fora do
mapa são rejeitados.

O editor possui alternativa textual e controles de teclado. A posição ordena as
marcações independentemente da geometria.

## Concorrência

Criação, edição, exclusão, reordenação e vínculo de fotos avançam `lock_version`.
O cliente precisa enviar as versões atuais do mapa e, quando aplicável, da
marcação. Uma divergência gera erro de conflito e exige recarregar o editor.

O checksum da origem protege também contra um Job antigo sobrescrever derivados
de um upload mais novo.

## Fotografias da marcação

Somente fotos da avaliação vinculada, na mesma inspeção e tenant, podem ser
selecionadas. A lista elimina duplicidades e persiste a ordem no pivot.

Para a cobertura obrigatória, cada marcação precisa ter ao menos uma foto e todas
devem estar `ready`. Uma marcação sem avaliação atual pode existir após cópia de
reinspeção, mas permanece pendente até ser resolvida.

## Cópia para reinspeção

A cópia é permitida quando:

- existe inspeção anterior do mesmo equipamento e organização;
- a inspeção atual está editável;
- o usuário pode editar os mapas;
- a inspeção atual ainda não possui mapas.

O sistema copia mapas, fundo processado, geometria, estilo e ordem. Cada marcação é
vinculada à avaliação corrente da mesma avaria quando ela já existe; caso
contrário, fica pendente. Fotografias antigas não são copiadas para o novo vínculo.

## Mapas e conclusão

O catálogo nativo não configura obrigatoriedade de mapas. O campo
`requires_location_map` e a validação de cobertura por categoria foram removidos.
Mapas continuam agrupados pelo código da categoria; marcações continuam sujeitas
às validações de contexto, geometria, fotografias e concorrência do editor.
O redesenho desse agrupamento fica para uma mudança posterior.

## Relatório e numeração

O compositor ordena categorias, mapas e marcações e intercala cada mapa com a
documentação fotográfica das avaliações encontradas pela primeira vez. A
numeração reinicia por categoria; TAC reserva 1 a 4 para as fotografias da vista
geral e inicia as fotografias de avaliação em 5.

Somente avaliações completas e fotos prontas recebem número. A legenda compacta
sequências em valores ou intervalos. Uma fotografia publicada sem índice de mapa
é sinalizada e bloqueia a exportação atual.

## Exclusão

Excluir um mapa aplica soft delete ao mapa e às marcações e tenta remover origem e
derivados pertencentes ao módulo. Caminhos inconsistentes e documentos apenas
referenciados nunca são usados em exclusões. Excluir marcação também usa
concorrência otimista e libera sua restrição de vínculo ativo.

Não existe limpeza agendada de mapas excluídos; uma falha de remoção imediata fica
registrada em log.

## Cobertura automatizada

Há testes para fundação, limites, upload, processamento, assets privados, rotas,
editor, geometrias, concorrência, reordenação, fotos, cópia, cobertura, relatório,
numeração e hardening de tenant e caminhos.
