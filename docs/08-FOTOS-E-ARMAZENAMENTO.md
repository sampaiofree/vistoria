# 08 — Fotografias e armazenamento

## Tipos de fotografia

O sistema mantém dois conjuntos independentes:

- fotografias de uma avaliação de avaria (`AssessmentPhoto`);
- quatro fotografias da vista geral da inspeção, divididas em dois blocos e dois
  slots por bloco (`InspectionOverviewPhoto`).

Fotografias de avaliação podem ter tipo informativo `overview`, `detail`,
`context`, `repair_evidence` ou `other`, além de legenda e data de captura. Esses
tipos não definem slots especiais no relatório; a ordem canônica da galeria é
`position`, seguida pelo ID.

## Upload

O backend aceita JPEG, PNG ou WEBP com até 25 MB. O proxy e o PHP-FPM devem aceitar
corpos de pelo menos 30 MB para acomodar o multipart.

Antes do envio, o navegador tenta:

1. reduzir o maior lado para no máximo 2048 px;
2. codificar WEBP com qualidade progressiva de 0,8 até 0,5;
3. buscar aproximadamente 2 MB;
4. usar JPEG quando o navegador não respeitar WEBP;
5. manter o original quando ele já for menor ou quando a otimização falhar.

A otimização no cliente reduz tráfego, mas não substitui validação e limites do
servidor.

## Persistência e processamento

O upload é salvo no disco privado `inspection_photos` em um caminho segregado por
organização, inspeção, avaliação e fotografia. O registro começa em `pending` e o
Job é enviado à fila `images` após o commit.

Estados:

- `pending`;
- `processing`;
- `ready`;
- `failed`.

O processador usa Imagick, corrige orientação e rejeita dimensões inseguras:

- no máximo 16.384 px por lado;
- no máximo 60 milhões de pixels;
- limites de memória, map, disco e threads definidos em `config/photos.php`.

Quando bem-sucedido, gera:

- `optimized.webp`, lado máximo de 3000 px, qualidade 82;
- `thumbnail.webp`, lado máximo de 480 px, qualidade 78.

Checksum e dimensões são persistidos. O arquivo original temporário é removido
depois que os derivados ficam prontos.

## Tentativas e falhas

Os Jobs de fotografia tentam três vezes, com backoff de 10, 60 e 300 segundos e
timeout de 180 segundos. Erros intermediários são propagados para que a fila faça
a próxima tentativa.

Na falha definitiva:

- derivados e original restantes são removidos quando possível;
- o registro passa a `failed`;
- o erro é persistido;
- uploader e responsáveis ativos da inspeção recebem notificação no sistema.

Não existe endpoint de retry manual. Para recuperar uma falha definitiva, o
usuário substitui ou envia outra imagem.

## Acesso e privacidade

Somente `optimized` e `thumbnail` são servidos. O Controller:

1. resolve a fotografia dentro do tenant;
2. aplica a Policy;
3. exige status `ready` e arquivo existente;
4. transmite WEBP com cache privado por uma hora.

O original não possui rota pública e normalmente já terá sido removido após o
processamento.

## Regras das avaliações

- upload, reordenação e remoção exigem permissão de atualização da avaliação;
- a inspeção deve estar em `in_progress` ou `in_correction` e o usuário deve ser
  o Inspetor responsável com permissão de conteúdo de campo;
- fotografias não podem ser removidas ou reordenadas fora dos estados editáveis de
  campo;
- a lista completa de IDs é validada durante a reordenação para detectar tela
  desatualizada;
- para publicar uma condição observável, são exigidas ao menos duas fotos e todas
  as fotos da avaliação devem estar `ready`;
- o envio para verificação repete essa validação de cobertura.

Uma avaliação publicada ainda pode ser devolvida a rascunho quando seu conteúdo é
alterado nos estados editáveis. Portanto, fotos publicadas não são um conjunto
append-only nesta versão.

## Regras da vista geral

- existem somente os blocos 1 e 2 e os slots 1 e 2;
- um novo upload substitui e remove o arquivo anterior daquele slot;
- a edição é permitida a administrador ou responsável enquanto a inspeção não for
  final;
- o relatório só libera impressão/exportação quando os quatro slots estão
  preenchidos e prontos, além dos textos obrigatórios da seção.

## Relação com mapas e relatório

Não existe seleção de fotos na localização. Todas as fotografias prontas da
avaliação localizada são incluídas automaticamente, seguindo a ordem da galeria.
A numeração final segue categoria e sequência da avaria; TAC começa em 5.

Avaliações com evidência obrigatória não podem ser publicadas sem mapa pronto e
localização confirmada, portanto suas fotografias publicadas possuem uma origem
de numeração no relatório.

## Operação

O worker da fila `images` é obrigatório em todos os ambientes que recebem upload.
Não há comando agendado de limpeza de uploads: os Jobs e as ações de substituição
ou exclusão removem os arquivos que controlam. Falhas de remoção são registradas
em log para tratamento operacional.

## Cobertura automatizada

Os testes usam filesystems falsos e processadores reais ou controlados para cobrir
upload, variantes, orientação, limites, falhas, notificações, autorização,
reordenação, substituição, remoção e integração com mapas e relatório.
