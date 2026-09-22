# 15 — Padrão visual e layout do relatório técnico

> **Tipo:** referência visual e especificação. As regras abaixo descrevem o padrão
> pretendido para prévia, impressão e exportação; não significam que cada detalhe
> do documento Word já esteja automatizado no renderer atual.

## 1. Objetivo

Este documento define o padrão visual que deve ser reproduzido pelo módulo de relatório do **Vistoria**, tomando como referência o arquivo:

`PADRÃO DE RELATÓRIO TAC - REC - CIVIL 2026-1 1.docx`

O objetivo é transformar a formatação existente no Word em regras explícitas e previsíveis para a prévia A4, impressão e exportação do relatório.

> O arquivo Word utiliza bastante formatação direta. Portanto, o sistema não deve copiar cegamente os estilos internos do Word. A referência deve ser a aparência efetivamente aplicada no documento.

---

## 2. Formato das páginas

O relatório utiliza papel **A4** e possui páginas em **retrato** e **paisagem**.

### 2.1 A4 — Retrato

Dimensões:

```text
Largura:  21,0 cm
Altura:   29,7 cm
```

Margens identificadas no documento:

```text
Superior: 1,0 cm
Direita:  0,8 cm
Inferior: 1,0 cm
Esquerda: 2,5 cm
```

Distâncias:

```text
Cabeçalho: 1,0 cm do topo
Rodapé:    aproximadamente 0,5 cm da borda inferior
```

Esse deve ser o padrão das páginas internas em orientação retrato.

---

### 2.2 A4 — Paisagem

O documento também utiliza páginas A4 em paisagem para conteúdos largos, especialmente tabelas de quantitativos.

Dimensões:

```text
Largura:  29,7 cm
Altura:   21,0 cm
```

Margens identificadas:

```text
Superior: 2,5 cm
Direita:  1,0 cm
Inferior: 0,8 cm
Esquerda: 1,0 cm
```

O gerador de relatório deve suportar páginas retrato e paisagem dentro do mesmo relatório.

---

## 3. Tipografia principal

A fonte base efetiva do documento é:

```text
Times New Roman
```

O próprio DOCX define **Times New Roman** como fonte padrão dos caracteres.

### 3.1 Texto corrido

Padrão:

```text
Fonte: Times New Roman
Tamanho: 12 pt
Peso: normal
Entrelinha: 1,5
Alinhamento: predominantemente à esquerda
Espaçamento antes: 0 pt
Espaçamento depois: 0 pt
```

Em várias áreas de conteúdo existe recuo de aproximadamente:

```text
1,0 cm à esquerda
```

Esse recuo deve ser aplicado quando o layout original apresentar texto descritivo deslocado em relação ao número/título da seção.

---

## 4. Títulos e subtítulos

Os títulos técnicos devem manter a mesma família tipográfica do conteúdo.

### Título principal de seção

Exemplo:

```text
2 DESCRIÇÃO DOS ASPECTOS GERAIS DO EQUIPAMENTO
```

Padrão visual:

```text
Fonte: Times New Roman
Tamanho: 12 pt
Cor: preta
Alinhamento: esquerda
```

O uso de caixa alta deve seguir o próprio título.

### Subtítulo

Exemplo:

```text
2.1 Descrição das características da estrutura
```

Padrão visual:

```text
Fonte: Times New Roman
Tamanho: 12 pt
Cor: preta
Alinhamento: esquerda
```

### Regra importante

Os estilos internos `Heading 1`, `Heading 2` etc. do arquivo Word não devem ser tratados como fonte de verdade.

Há estilos internos associados a Arial, porém o conteúdo efetivamente apresentado possui formatação direta em Times New Roman.

No aplicativo, títulos e subtítulos devem ter CSS próprio.

---

## 5. Capa

A capa utiliza uma composição diferente das páginas internas.

O bloco principal central contém textos como:

```text
UBU – GERAL
EMPILHAMENTO DE PRODUTO
EQUIPAMENTO – TAG
INSPEÇÃO DE INTEGRIDADE ESTRUTURAL
RELATÓRIO DE INSPEÇÃO
```

Padrão predominante:

```text
Fonte: Times New Roman
Tamanho: 14 pt
Peso: negrito
Entrelinha: aproximadamente 2,0
```

Existem informações variáveis destacadas em vermelho.

A capa não deve reutilizar automaticamente o mesmo componente de conteúdo das páginas internas.

---

## 6. Cabeçalho das páginas internas

As páginas internas possuem cabeçalho técnico fixo contendo:

- logotipo Samarco;
- logotipo SEND;
- identificação do Projetista II;
- número Samarco;
- revisão;
- número da página.

Visualmente, o cabeçalho funciona como uma tabela horizontal.

A tipografia predominante dos campos pequenos do cabeçalho é:

```text
Fonte: Arial
Tamanho aproximado: 8 pt
```

O cabeçalho deve manter:

- bordas visíveis;
- alinhamentos centralizados conforme a célula;
- logos proporcionalmente dimensionados;
- número Samarco e revisão destacados conforme o modelo;
- linha inferior mais forte separando o cabeçalho do conteúdo.

### Regra de implementação

O cabeçalho deve ser um componente próprio do relatório e não deve depender de posicionamento manual em cada página.

---

## 7. Rodapé

O documento utiliza pouco conteúdo de rodapé nas páginas internas.

A distância identificada da borda inferior é aproximadamente:

```text
0,5 cm
```

O rodapé não deve reduzir a área útil do relatório além do necessário.

---

## 8. Cores

O relatório utiliza principalmente:

- preto;
- vermelho;
- azul-escuro;
- cinza claro;
- branco;
- cores da classificação GUT.

### 8.1 Vermelho

O vermelho aparece em informações variáveis e textos destacados.

Cor identificada no documento:

```text
#FF0000
```

O sistema não deve assumir que qualquer dado dinâmico precisa obrigatoriamente ser vermelho. O uso deve seguir o modelo de cada seção.

---

### 8.2 Azul-escuro

Os anexos fotográficos utilizam barras horizontais azul-escuras em títulos como:

```text
DOCUMENTAÇÃO FOTOGRÁFICA - TAC
LOCALIZAÇÃO FOTOGRÁFICA - TAC
LOCALIZAÇÃO FOTOGRÁFICA - REC
```

Essas barras devem ser tratadas como componente visual próprio.

---

### 8.3 Cinza

O cinza claro é utilizado como fundo de cabeçalhos e linhas auxiliares de tabelas.

---

## 9. Tabelas

As tabelas são parte estrutural do relatório e devem evitar redimensionamento automático imprevisível.

Características predominantes:

- bordas finas;
- separadores mais fortes em algumas seções;
- conteúdo centralizado em campos quantitativos;
- textos de identificação alinhados conforme o modelo;
- cabeçalhos com fundo cinza em determinadas tabelas;
- altura de linha controlada;
- células mescladas quando necessário.

### Regra

Cada tabela técnica importante deve possuir layout próprio.

Não utilizar uma única tabela genérica para:

- resumo GUT;
- quantitativo REC;
- quantitativo CIVIL;
- documentação fotográfica;
- legendas;
- cabeçalho institucional.

---

## 10. Classificação GUT

As células de classificação utilizam cores para facilitar a identificação visual das prioridades.

O sistema deve preservar as cores oficiais utilizadas para:

- TAC;
- REC;
- CIVIL;
- telhados/tapamentos quando aplicável.

A cor deve ser determinada pela classificação registrada no relatório, e não digitada manualmente pelo usuário.

---

## 11. Documentação fotográfica

As páginas de documentação fotográfica utilizam uma estrutura própria.

Elementos identificados:

- barra azul de título;
- duas colunas para fotografias;
- número da foto;
- TAG/equipamento;
- localização;
- área reservada para imagem;
- comentário;
- recomendações.

Em uma página padrão podem existir dois blocos verticais, cada um contendo duas fotografias lado a lado.

### Regra de layout

A fotografia deve ser ajustada dentro da área disponível mantendo proporção.

Não deve:

- deformar;
- extrapolar a célula;
- alterar a paginação inesperadamente.

Quando necessário, utilizar `object-fit: contain`.

---

## 12. Mapas de localização

As páginas de localização fotográfica possuem:

- título em barra azul;
- identificação do projeto de referência;
- área principal para desenho/mapa;
- legenda GUT;
- tabela quantitativa;
- observações na parte inferior.

O mapa deve ocupar o máximo possível da área definida sem alterar a geometria original.

---

## 13. Sumário

O sumário possui:

- título `Sumário`;
- numeração hierárquica;
- texto do capítulo;
- pontilhado;
- número da página alinhado à direita.

O sumário do aplicativo deve ser gerado a partir da paginação real do relatório.

Não deve utilizar números de página fixos cadastrados manualmente.

---

## 14. Padrão CSS de referência

Valores base recomendados para implementação:

```css
.report-page {
    box-sizing: border-box;
    background: #fff;
    font-family: "Times New Roman", Times, serif;
    font-size: 12pt;
}

.report-page--portrait {
    width: 210mm;
    height: 297mm;
    padding: 10mm 8mm 10mm 25mm;
}

.report-page--landscape {
    width: 297mm;
    height: 210mm;
    padding: 25mm 10mm 8mm 10mm;
}

.report-body {
    line-height: 1.5;
}

.report-section-title,
.report-subsection-title {
    font-family: "Times New Roman", Times, serif;
    font-size: 12pt;
}

.report-cover-title {
    font-family: "Times New Roman", Times, serif;
    font-size: 14pt;
    font-weight: 700;
    line-height: 2;
}

.report-header {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 8pt;
}
```

Esses valores devem ser mantidos em tokens/componentes centralizados, evitando medidas repetidas diretamente nos componentes Vue.

---

## 15. Arquitetura recomendada do relatório

O frontend deve separar o relatório em componentes de layout.

Exemplo:

```text
ReportDocument
├── ReportCoverPage
├── ReportPortraitPage
│   └── ReportHeader
├── ReportLandscapePage
│   └── ReportHeader
├── ReportSummary
├── ReportGutSummary
├── ReportGeneralAspects
├── ReportPhotoDocumentation
├── ReportLocationMap
├── ReportRecQuantitative
└── ReportCivilQuantitative
```

As medidas de página, margens, tipografia e cabeçalho não devem ser duplicadas em cada seção.

---

## 16. Regras para paginação

A paginação deve respeitar a área útil real de cada página.

Não permitir que:

- títulos fiquem isolados no fim da página;
- linhas importantes de tabela sejam cortadas;
- uma fotografia seja dividida entre páginas;
- barras de título fiquem separadas do conteúdo correspondente;
- mapa, legenda e quantitativo sejam separados quando a composição exigir unidade visual.

Quando um bloco não couber na área restante, deve começar na próxima página.

---

## 17. Exportação PDF e DOCX

A prévia, o PDF e o DOCX devem utilizar a mesma origem visual.

A regra esperada é:

```text
mesmos dados
+ mesmos componentes
+ mesmas dimensões
+ mesma paginação
= mesma composição visual
```

Diferenças significativas entre a prévia e o arquivo exportado devem ser consideradas defeito.

---

## 18. Fonte de verdade visual

Para esta etapa, a referência visual é o arquivo:

```text
PADRÃO DE RELATÓRIO TAC - REC - CIVIL 2026-1 1.docx
```

Caso exista conflito entre:

1. estilos internos do Word;
2. nomes dos estilos;
3. configuração herdada;
4. aparência efetivamente renderizada;

deve prevalecer a **aparência efetivamente renderizada**, desde que a regra seja registrada explicitamente neste documento.

---

## 19. Pontos que ainda precisam de validação

Antes de considerar o padrão fechado, ainda é necessário validar com o cliente:

- se o vermelho representa obrigatoriamente dados variáveis ou apenas marcação do modelo;
- se os logotipos e proporções do cabeçalho devem ser exatamente os mesmos;
- se existe fonte oficial corporativa diferente do modelo analisado;
- se as cores GUT devem seguir exatamente os tons do documento;
- se páginas adicionais futuras poderão utilizar outras orientações ou margens;
- se o relatório precisa permanecer visualmente idêntico ao Word ou apenas equivalente dentro de tolerância definida.

Esses itens não devem ser presumidos pelo sistema.

---

## 20. Critério de aceite

O padrão visual pode ser considerado implementado quando:

1. páginas retrato e paisagem possuem as dimensões e margens definidas;
2. tipografia principal corresponde ao padrão identificado;
3. cabeçalho permanece consistente em todas as páginas internas;
4. tabelas não extrapolam a área útil;
5. fotografias preservam proporção;
6. mapas preservam proporção e posição;
7. sumário utiliza a paginação real;
8. nenhuma seção invade cabeçalho, rodapé ou margem;
9. a prévia e a exportação apresentam a mesma composição;
10. a comparação visual com o documento de referência não apresenta diferenças estruturais relevantes.
