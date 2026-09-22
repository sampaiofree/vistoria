# 08A — Mapas e localização por avaria

## Modelo atual

Cada avaria (`Defect`) possui no máximo um mapa lógico, compartilhado por todas
as suas avaliações. Cada upload cria uma versão imutável da imagem e somente a
avaliação em edição passa a apontar para a nova versão.

```text
Defect
└── DefectLocationMap (1:1)
    └── DefectLocationMapVersion (1:N)
        └── DefectAssessment (N:1)
            └── DefectAssessmentLocation (1:1)
```

`defect_location_maps` garante unicidade por avaria;
`defect_assessment_locations` garante uma localização por avaliação. A
localização contém uma ou várias regiões geométricas da mesma avaria, uma legenda
opcional, confirmação e `lock_version`.

As tabelas antigas de mapas livres, marcações múltiplas e seleção de fotografias
foram descartadas sem migração de conteúdo legado.

## Fluxo na avaliação

A seção **Mapa e localização** aparece antes dos registros fotográficos. O
Inspetor envia PNG, JPEG ou WebP, aguarda o processamento, abre o editor da
avaliação, desenha uma ou várias regiões, informa uma legenda opcional e salva.
Salvar também confirma a localização.

Não há seletor de avaria, criação independente, título/categoria/ordem editáveis,
cor manual, múltiplas marcações nem seleção manual de fotos. Título, categoria e
ordem vêm da avaria. Todas as fotos prontas da avaliação são usadas
automaticamente no relatório.

A aba **Localização** da inspeção é somente uma visão consolidada por categoria e
avaria. Seus links levam à avaliação ou ao editor e não existe botão **Novo mapa**.

## Cor automática

O cliente nunca envia nem persiste estilo editável. Avaliações com classificação
usam `classification_snapshot.color`, inclusive TEL. Avaliações tratadas e rascunhos
ainda sem classificação usam `#64748B`. Alterar a classificação muda a cor
automaticamente.

## Publicação e reinspeção

`new`, `reinspected`, `reclassified` e `treated` exigem uma versão pronta e uma
localização confirmada, além dos demais requisitos de evidência. `canceled` e
`canceled_sr` são dispensadas e não entram na documentação de localização.

Uma reinspeção herda a versão pronta usada pela avaliação anterior e copia sua
geometria e legenda. A cópia começa sem `confirmed_at`: o Inspetor precisa
confirmá-la explicitamente antes de publicar.

Substituir a imagem cria nova versão e mantém a geometria como prévia, mas invalida
sua confirmação. Se a avaliação estava publicada, ela volta a rascunho e limpa os
snapshots de publicação; a mesma linha é reutilizada, não uma revisão append-only.
Avaliações de inspeções anteriores continuam apontando para a versão histórica.

## Upload, processamento e segurança

O upload aceita imagens de até 50 MB, no máximo 30.000 px por lado e 80 milhões
de pixels. `ProcessInspectionLocationMap` trabalha na fila `images`, com três
tentativas, timeout de 180 segundos e backoff de 10, 60 e 300 segundos.

O Job reivindica uma versão pendente, valida origem e checksum, gera fundo WebP
de até 3000 px e thumbnail de até 640 px e só publica derivados se versão e
checksum ainda forem os esperados. Um Job antigo nunca escreve em outra versão.
Após sucesso, o upload temporário é removido; falha definitiva marca a versão e
notifica os usuários relacionados.

Arquivos ficam no disco privado, em diretório que inclui organização, avaria,
mapa e versão. A leitura valida tenant, estado pronto e caminho controlado antes
de transmitir com cache privado.

## Geometria e concorrência

A geometria usa coordenadas normalizadas de 0 a 1 e schema 1. São aceitos ponto,
retângulo, polígono e polilinha, com até 50 formas e 100 pontos por
polígono/linha.

Atualização e exclusão usam `lock_version`. Uma gravação obsoleta exige recarregar
o editor. `style`, `color` e `defect_assessment_id` são proibidos nas requisições.

## Relatório e ciclo de arquivos

O relatório gera uma folha por avaliação completa, localizada e não cancelada,
ordenada pela categoria e sequência da avaria. A folha usa a versão histórica,
uma geometria e todas as fotos prontas da avaliação. A numeração reinicia por
categoria; TAC reserva 1 a 4 e começa em 5.

Versões referenciadas nunca são removidas. Uma versão falha, removida ou
substituída só tem registros e arquivos eliminados quando nenhuma avaliação
aponta para ela. O mapa lógico é eliminado apenas quando fica sem versões.

## Cobertura automatizada

Os testes cobrem unicidade, upload e substituição, invalidação de publicação,
concorrência, rejeição de cor, assets privados, isolamento multiempresa, herança
de reinspeção, confirmação obrigatória, limpeza de versões, relatório histórico,
fotografias automáticas e reserva TAC.
