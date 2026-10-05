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
