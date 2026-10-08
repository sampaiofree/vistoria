# Relatórios e exportações

## Prévia da inspeção

A rota `GET /inspections/{inspection}/report-preview` monta o conteúdo com
serviços do backend e o componente `ReportPreview.vue` pagina a prévia em A4 no
navegador. Ela inclui capa, quadro de revisões, aspectos gerais, resumo de
classificação, mapas, documentação fotográfica, quantitativos e anexos aplicáveis
ao conteúdo da inspeção. A ordem e a numeração dos anexos são derivadas do mesmo
plano usado pelo read model do servidor.

As fotos da vista geral ficam em blocos com dois slots; os quatro primeiros são
necessários para avançar no fluxo, e a seção aceita páginas adicionais. Fotos de
avaria publicadas, mapas e classificações vêm dos registros ou snapshots da
avaliação. Em reinspeção seletiva, avarias fora da seleção mantêm a referência à
avaliação publicada anterior. Os nomes documentais do relatório estão em
[Inspeções](inspecoes.md#responsáveis-exibidos-no-relatório).

Em **Conteúdo do relatório → Aspectos gerais do equipamento**, o editor aceita
texto, até dez imagens em blocos próprios e tabelas de até seis colunas e
cinquenta linhas. As imagens são selecionadas ou coladas e precisam terminar o
processamento antes de salvar. Uma imagem isolada não atende à exigência de
conteúdo textual; texto em tabela atende. A versão 2 do documento também é usada
nos modelos reutilizáveis novos ou editados. Modelos e documentos antigos da
versão 1 continuam legíveis. Modelos podem conter imagens e tabelas, inclusive
campos automáticos e trechos manuais em vermelho nas células. Ao aplicar um
modelo, suas imagens prontas são copiadas para a inspeção; a edição ou exclusão
posterior do modelo não altera o conteúdo salvo na inspeção. A vista geral
fotográfica é uma seção separada.

Na paginação A4, imagens ficam inteiras e limitadas à área útil. Tabelas
continuam em outras páginas, repetem a primeira linha quando ela é cabeçalho e
dividem o texto de uma linha longa em fragmentos com as mesmas colunas. Se uma
imagem ou tabela não puder ser acomodada, a prévia sinaliza erro de layout e
bloqueia a exportação, evitando corte silencioso.

## Arquivos disponíveis

| Saída | Implementação | Persistência |
|---|---|---|
| PDF | Captura das páginas A4 no navegador com html2canvas/jsPDF | Download local |
| DOCX | Imagens das páginas A4 em seções do documento com `docx` | Download local |
| Quantitativo XLS | `BuildInspectionQuantitativeWorksheet` e `ExportInspectionQuantitativeWorksheet` no servidor | Download em resposta HTTP |

A planilha está em `GET /inspections/{inspection}/quantitative` e a exportação
em `GET /inspections/{inspection}/quantitative/export`. Ela lista avaliações
publicadas elegíveis de CIVIL, REC e TEL. Exportar PDF, DOCX ou XLS não muda o
estado da inspeção. O servidor não arquiva um relatório oficial gerado, e
`report_generated_at` não prova que um arquivo tenha sido exportado.

## Layout e limitações

A prévia e PDF/DOCX usam as páginas renderizadas pelo mesmo componente. O DOCX é
uma composição de imagens de página, não um documento com texto técnico editável.
A visualização depende dos recursos e fontes disponíveis no navegador. Um modelo
Word externo não é fonte executável do layout; mudanças visuais devem ser feitas
nos componentes e estilos do relatório.

A exportação usa as verificações de conteúdo oferecidas pela interface; a
liberação e as políticas da inspeção continuam sendo ações separadas. Nenhum
arquivo de exportação é armazenado em `storage` pela aplicação.
