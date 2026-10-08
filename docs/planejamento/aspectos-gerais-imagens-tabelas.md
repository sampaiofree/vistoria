# Imagens e tabelas nos Aspectos Gerais

**Estado:** implementado no editor da inspeção e nos modelos reutilizáveis, incluindo a barra de ícones e a cópia de imagens ao aplicar o modelo.

## Local e alcance

O ajuste pertence ao campo **Conteúdo do relatório → Aspectos gerais do equipamento** da inspeção e aos seus modelos reutilizáveis. O documento mantém o Tiptap e o JSON estruturado. A seção de fotografias **Vista Geral** continua independente.

## Editor da inspeção — implementado

- Inserir imagens como blocos próprios entre os textos, por seleção de arquivo ou colagem de uma imagem da área de transferência. Não haverá legenda, alinhamento nem controle manual de tamanho.
- Centralizar cada imagem, preservar sua proporção e limitar automaticamente sua largura à área disponível, sem ampliar imagens pequenas. Aceitar JPG, PNG e WebP, com até 25 MB por arquivo antes da otimização e até 10 imagens no documento.
- Reaproveitar a redução antes do envio e o processamento de variantes já usados nas fotografias de avarias. Mostrar o estado de envio/processamento; **Salvar** aguarda todas as imagens referenciadas ficarem prontas. Em caso de falha, permitir remover ou substituir a imagem.
- Permitir tabelas pelo próprio editor: inserir/excluir tabela, adicionar/remover linhas e colunas e ativar/desativar a primeira linha como cabeçalho. O cabeçalho começa ativado. Limites: até 6 colunas e 50 linhas por tabela.
- Distribuir a largura das colunas automaticamente e quebrar o texto dentro das células. Permitir somente texto, negrito e itálico nas células. Não incluir imagens, listas, tabelas internas, mesclagem, cores livres, ajuste manual da largura nem colagem de tabelas do Excel/Word nesta etapa.
- Manter a digitação livre nas células, sem limite de 300 caracteres. Texto dentro de uma tabela conta para a exigência de conteúdo dos Aspectos Gerais; uma imagem isolada não conta.
- Exibir abaixo do editor uma explicação breve sobre o limite de 6 colunas e 50 linhas, a largura automática e a continuação das tabelas nas páginas do relatório.

## Arquivos, persistência e autorização da inspeção

Criar um registro próprio para cada imagem dos Aspectos Gerais, vinculado à organização e à inspeção, separado das fotos de avarias e da Vista Geral. Usar o disco privado `inspection_photos`, que já aponta para Cloudflare R2 quando `USER_IMAGES_STORAGE=r2`, em uma pasta própria por inspeção. A instalação local continua usando o mesmo disco configurado para armazenamento local. Não é necessário bucket público nem novo bucket.

O JSON do Tiptap armazena apenas a referência `assetId` do bloco de imagem. O servidor rejeita Base64, URL externa, caminhos de storage, atributos não previstos e referências a imagens de outra inspeção ou organização. A leitura da miniatura e da versão otimizada passa por rota autenticada e autorizada; a prévia e a exportação usam a versão otimizada.

Os documentos da inspeção são salvos na versão 2 do esquema. A leitura dos documentos existentes da versão 1 e dos modelos atuais permanece disponível, sem migração em massa. O salvamento reconcilia as referências dos arquivos. O comando diário `general-aspects:cleanup-images` remove, após sete dias, uploads abandonados e imagens retiradas do documento; registros em processamento ou presentes no documento salvo são preservados.

## Paginação e exportação da inspeção

A prévia A4 e as exportações PDF/DOCX continuam usando as mesmas páginas geradas no navegador. A paginação deve aguardar o carregamento e as dimensões das imagens antes de medir o conteúdo. Uma imagem fica inteira em uma página; quando não cabe no espaço restante, passa à seguinte, com altura máxima limitada à área útil e sem corte.

Tabelas continuam entre páginas, com o cabeçalho repetido quando estiver ativo. Linhas que não cabem no espaço restante passam à página seguinte. Se uma única linha exceder a altura de uma página, seu texto continua nas páginas seguintes, preservando as colunas e a formatação permitida. A prévia e os arquivos exportados não podem ocultar conteúdo por `overflow` ou cortar células silenciosamente.

## Verificação da etapa da inspeção

- Confirmar seleção e colagem de imagem, limite de 10, otimização, estados de processamento, falha e bloqueio de **Salvar** até todas as imagens referenciadas ficarem prontas.
- Validar rejeição de URL/Base64, imagem pendente e `assetId` inexistente ou pertencente a outro tenant/inspeção; conferir leitura autorizada no armazenamento local e no disco R2 configurado.
- Exercitar operações e limites das tabelas, conteúdo permitido nas células, cabeçalho opcional e texto da explicação abaixo do editor.
- Conferir documentos antigos e modelos reutilizáveis da versão 1; testar texto apenas em tabela como conteúdo válido e imagem isolada como insuficiente.
- Verificar visualmente a prévia A4 e os PDFs/DOCXs com imagens próximas ao rodapé, tabelas em várias páginas, uma linha maior que uma página e cabeçalho repetido, sem corte de conteúdo.

## Barra de ferramentas — implementado

O Tiptap fornece os comandos, mas não impõe ícones ou uma barra visual nativa. A barra usa SVGs do `UiIcon.vue`, sem acrescentar biblioteca de ícones.

- Trocar o texto dos botões **Inserir imagem** e **Inserir tabela** por ícones: reutilizar o ícone de foto do projeto e adicionar um ícone de grade para tabela.
- Quando o cursor estiver dentro de uma tabela, usar ícones de grade que distingam **adicionar linha**, **remover linha**, **adicionar coluna** e **remover coluna** por posição e sinal `+` ou `−`. Usar também ícones para **ativar/desativar cabeçalho** e **excluir tabela**, agrupando visualmente as ações da tabela.
- Manter os botões compactos e coerentes com a barra atual, com estados de hover, foco e desativado visíveis. Cada botão de ícone deve ter nome acessível em português (`aria-label`) e dica de texto (`title` ou tooltip). Os limites de 50 linhas e 6 colunas continuam desativando as ações correspondentes.
- O ajuste é apenas visual: preservar os comandos, limites, regras de conteúdo e comportamento de inserção existentes. Verificar teclado, leitor de tela e disposição da barra em larguras menores.

## Modelos reutilizáveis — implementado

Esta etapa altera somente os modelos de Aspectos Gerais e sua aplicação no campo da inspeção. Não altera a seção de fotos Vista Geral.

### Editor e documento do modelo

- Habilitar imagens e tabelas no editor do modelo, com a mesma experiência e os mesmos limites da inspeção: blocos de imagem sem legenda ou largura manual; seleção ou colagem de JPG, PNG e WebP de até 25 MB; até 10 imagens; tabelas com até 6 colunas e 50 linhas, primeira linha como cabeçalho por padrão e largura automática. Manter as demais restrições de conteúdo e estrutura das tabelas.
- **Salvar modelo** deve aguardar todas as imagens referenciadas ficarem prontas. Imagens com falha podem ser removidas ou substituídas. Imagem isolada não substitui o conteúdo textual exigido.
- Salvar os modelos novos ou editados na versão 2 do JSON, com imagem contendo apenas `assetId`. Modelos existentes da versão 1 continuam legíveis e aplicáveis, sem migração em massa; ao editar e salvar um deles, gravá-lo na versão 2.
- Permitir nas células, além de texto, negrito e itálico, os **Campos do item de manutenção** já disponíveis no modelo e os trechos manuais em vermelho que indicam conteúdo a preencher. Esses são os únicos acréscimos à formatação permitida nas células: sem cores arbitrárias, imagens, listas, tabelas internas, mesclagem ou largura manual.
- Cada campo automático inserido numa célula representa um único valor, inclusive quando há texto antes ou depois dele. Não criar linhas automaticamente a partir dos campos. Ao aplicar o modelo, substituir os campos pelos valores do contexto da inspeção. Se um valor estiver ausente, mostrar `[Preencher: nome do campo]` em vermelho, com a mesma exigência de correção já usada fora das tabelas. Os trechos manuais em vermelho seguem essa mesma regra.

### Imagens, aplicação e ciclo de vida

- Criar arquivos e registros de imagem **próprios do modelo**, vinculados à organização e ao modelo, no disco privado `inspection_photos` já configurado para R2 ou armazenamento local. Usar a mesma otimização no navegador, processamento de variantes, estados `pending`, `processing`, `ready` e `failed`, e leitura por rota autorizada. Não usar Base64, URL externa nem URL pública do R2 no JSON.
- Permitir upload durante a criação de um modelo ainda não salvo por meio de vínculo temporário protegido. Ao salvar, validar imagens prontas e da mesma organização e associá-las ao modelo. Uploads temporários abandonados e imagens removidas de modelos entram na limpeza após sete dias; imagens em processamento ou ainda referenciadas por um modelo são preservadas.
- Ao confirmar **Usar modelo** na inspeção, resolver os campos automáticos e **copiar** as variantes prontas de cada imagem do modelo para registros e caminhos próprios da inspeção, trocando os `assetId` no documento aplicado. Não repetir o processamento da imagem. Conferir autorização, organização e limite de 10 imagens antes da cópia. Aplicar o documento apenas quando todas as cópias terminarem; uma falha não deve deixar conteúdo aplicado parcialmente.
- A aplicação continua substituindo o conteúdo em edição, como ocorre hoje. O inspetor pode revisar e editar antes de salvar. Cópias não usadas porque a edição foi cancelada seguem a limpeza de imagens abandonadas da inspeção após sete dias.
- Depois de aplicado e salvo, o documento e as imagens da inspeção são independentes do modelo. Editar ou excluir o modelo não altera inspeções existentes nem apaga suas cópias. A prévia, o PDF e o DOCX usam a paginação A4 já implementada para imagens e tabelas da inspeção.

### Verificação dos modelos

- Criar, editar e aplicar modelos v2 com imagens e tabelas; conferir leitura e aplicação de modelos v1 e conversão ao salvá-los após edição.
- Confirmar campos automáticos e trechos manuais em vermelho dentro das células, inclusive valores ausentes; verificar que precisam ser corrigidos antes de salvar os Aspectos Gerais da inspeção. Confirmar que campos não geram linhas adicionais.
- Testar limites, formatos, estados e falhas de imagem no modelo; bloquear **Salvar modelo** enquanto houver imagem referenciada não pronta. Rejeitar no servidor `assetId` alheio, URL externa e Base64.
- Testar aplicação com cópia de imagens, troca dos `assetId`, falha de cópia, cancelamento antes de salvar, nova aplicação, edição/exclusão posterior do modelo e isolamento entre organizações. Conferir a limpeza após sete dias sem remover imagens ainda referenciadas.
- Conferir visualmente prévia, PDF e DOCX de uma inspeção criada a partir de modelo com imagem perto do rodapé, tabela longa, cabeçalho repetido e linha maior que uma página.
