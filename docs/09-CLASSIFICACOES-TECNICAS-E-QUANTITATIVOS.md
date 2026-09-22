# 09 — Classificações Técnicas e Quantitativos

## Objetivo

Este é o documento de referência transversal para as categorias **CIVIL (CV)**, **TAC**, **REC** e **TEL — Telhado/Tapamento**. Ele define a fonte de verdade, o cálculo GUT comum, TAC e as regras compartilhadas de persistência, histórico e arredondamento. As matrizes e quantitativos específicos estão em:

- [10 — Classificação REC e Quantitativos](10-CLASSIFICACAO-REC-E-QUANTITATIVOS.md);
- [11 — Classificação CIVIL e Quantitativos](11-CLASSIFICACAO-CIVIL-E-QUANTITATIVOS.md);
- [12 — Classificação Telhado/Tapamento](12-CLASSIFICACAO-TELHADO-TAPAMENTO.md);
- [13 — Resumo da Classificação do Equipamento e Nota M2](13-RESUMO-CLASSIFICACAO-EQUIPAMENTO-E-NOTA-M2.md).

Fonte técnica principal: `T000000-S-2PO006 — Procedimento de Inspeção de Estruturas e Priorização de Avarias — Rev. 04`. As planilhas de quantitativo são a fonte das fórmulas e unidades aplicáveis.

## Estado de implementação

O catálogo nativo, os resolvedores e as telas já suportam CIVIL, TAC, REC e TEL.
CIVIL, TAC e REC persistem GUT e classificação; TEL persiste pontuação e snapshot
próprios. Avaliações podem ter diversos itens de quantitativo e a publicação grava
snapshots de classificação e total.

Este documento não deve ser entendido como promessa de histórico imutável. A
avaliação `complete` pode voltar a `draft` em uma inspeção editável e o resumo de
classificação ainda forma suas linhas a partir do catálogo nativo vigente. Consulte
o [documento 17](17-AJUSTES-FINAIS-DOCUMENTOS-09-A-13.md) para as lacunas
conhecidas de histórico, resumo e precisão do contrato.

## Fonte de verdade no sistema

O catálogo é fixo no código e compartilhado por todas as organizações. Seus componentes previstos incluem `App\Enums\DefectCategory`, `NativeDefectCatalog`, `DefectClassificationDefinition`, `GutClassificationResolver` e o serviço de quantitativos. Não há tela para cadastrar ou editar regras técnicas; alterações futuras devem ser versionadas.

O inspetor não escolhe diretamente a classificação final. O sistema apresenta `NOTA + DESCRIÇÃO TÉCNICA`, calcula a nota aplicável e deixa os campos calculados como somente leitura.

## Categorias e classificação

As categorias CIVIL, TAC e REC usam:

```text
GUT = G × U × T
```

| Categoria | Notas | Classificação | Quantitativo | Documento específico |
|---|---|---|---|---|
| CIVIL | G, U, T de 1 a 5 | CV-1 a CV-5 | m³ calculado | 11 |
| TAC | G de 1 a 3; U e T de 1 a 5 | TA-1 a TA-5 | m² manual | este documento |
| REC | G, U, T de 1 a 5 | IE-1 a IE-5 | kg calculado ou manual | 10 |
| TEL | Impacto × Risco | TE-1 a TE-4 | não definido aqui | 12 |

### CIVIL e REC

| Classificação | Faixa GUT | Recomendação |
|---|---:|---|
| CV-1 / IE-1 | 75–125 | Tratar em até 1 ano |
| CV-2 / IE-2 | 36–74 | Tratar em até 2 anos |
| CV-3 / IE-3 | 16–35 | Tratar em até 3 anos |
| CV-4 / IE-4 | 8–15 | Intervenção por oportunidade |
| CV-5 / IE-5 | 1–7 | Registro de condição |

Os prazos são anos conforme o procedimento; não devem ser substituídos automaticamente por dias sem regra funcional específica.

### TEL

TEL não usa GUT:

```text
Pontuação TEL = Impacto na Segurança × Risco de Queda de Materiais
```

| Classificação | Faixa | Recomendação |
|---|---:|---|
| TE-1 | 12–15 | Tratar em até 1 ano |
| TE-2 | 9–11 | Tratar em até 2 anos |
| TE-3 | 6–8 | Tratar em até 3 anos |
| TE-4 | 3–5 | Intervenção por oportunidade |

## TAC

| Nota | Origem |
|---|---|
| G = 1, 2, 3 | classe do ativo C/D, B, A |
| U = 1 a 5 | atmosfera C2, C3, C4, C5, CX |
| T = 1 a 5 | ASTM D610: acima de 7; 6/7; 4/5; 2/3; 1/0 |

`G` e `U` vêm dos dados do ativo quando existentes. O inspetor seleciona o grau ASTM D610 e o sistema deriva `T`. A classificação é:

| Classificação | Faixa GUT | Recomendação |
|---|---:|---|
| TA-1 | 45–75 | Tratar em até 1 ano |
| TA-2 | 25–44 | Tratar em até 3 anos |
| TA-3 | 15–24 | Tratar em até 5 anos |
| TA-4 | 9–14 | Intervenção por oportunidade |
| TA-5 | 3–8 | Registro de condição |

O quantitativo TAC é informado manualmente no campo `Área (m²)`; não há fórmula automática de área identificada nas fontes analisadas.

## Status e regras compartilhadas

Os status possíveis são Reinspecionada, Reclassificada, Cancelada, Cancelada S/R, Nova e Tratada. Status é independente da classificação.

Não salvar somente as notas. A avaliação deve registrar as opções técnicas que as originaram, por exemplo `urgency_option`, `trend_damage`, `trend_option`, impactos de segurança/ativo e a nota final de gravidade.

O snapshot de publicação preserva categoria, entradas técnicas, notas, produto GUT
ou pontuação TEL, classificação, recomendação, quantitativo, entradas e resultado da
fórmula, unidade e versões. Relatórios usam esses snapshots para as avaliações
publicadas, mas o resumo ainda depende das faixas do catálogo atual e uma avaliação
publicada não é imutável dentro de uma inspeção editável.

Os cálculos dos itens mantêm precisão interna; arredondamento ocorre na apresentação.
O resumo agregado, porém, converte hoje a soma para `float`; não deve ser usado como
contrato decimal exato até a pendência registrada no documento 17 ser resolvida.

## Validação transversal

Validar limites inclusivos de classificação, cálculo automático, bloqueio de combinações técnicas inexistentes, armazenamento das opções e preservação de snapshots. As validações específicas de REC, CIVIL e TEL estão nos respectivos documentos de categoria.
