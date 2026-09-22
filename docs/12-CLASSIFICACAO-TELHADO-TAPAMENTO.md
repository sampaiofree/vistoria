# 12 — Classificação Telhado/Tapamento

> **Estado:** TEL está implementado como classificação própria, sem GUT e sem
> quantitativo técnico. Impacto, risco, dano e altura são resolvidos no catálogo
> nativo e gravados em `tel_snapshot`; a classificação final também alimenta o
> resumo. As seções de estrutura sugerida e comportamento esperado são referência
> técnica, não funcionalidades adicionais já entregues.

## Objetivo

Este documento define a lógica da categoria **TEL — Telhado/Tapamento**, correspondente à matriz de classificação de:

- telhados;
- tapamentos/fechamentos laterais.

Esta categoria entra na lista principal ao lado de:

```text
CIVIL
TAC
REC
TELHADO/TAPAMENTO
```

Código sugerido:

```text
TEL
```

Nome exibido:

```text
Telhado/Tapamento
```

A categoria TEL **não utiliza a metodologia GUT**.

A classificação é formada pela multiplicação de dois fatores:

```text
Impacto na Segurança
×
Risco de Queda de Materiais
=
Pontuação TEL
```

Fonte técnica:

```text
T000000-S-2PO006 — Procedimento de Inspeção de Estruturas e Priorização de Avarias — Rev. 04
```

Referências principais:

- Tabela 14 — Classificação com base na altura da telha;
- Tabela 15 — Classificação com base em cada tipo de dano nas telhas e fechamento lateral;
- Tabela 16 — Matriz de classificação de avarias para Telhas.

---

# 1. Fluxo geral

```text
TELHADO/TAPAMENTO
│
├── Altura do elemento
│   └── Impacto na Segurança
│
└── Tipo de dano
    └── Condição encontrada
        └── Risco de Queda de Materiais

Impacto × Risco
        ↓
Pontuação TEL
        ↓
TE-1 / TE-2 / TE-3 / TE-4
```

O inspetor não escolhe manualmente a classificação final.

---

# 2. Impacto na Segurança

O Impacto na Segurança é definido pela altura do elemento em relação ao solo.

## Tabela de pontuação

| Pontuação | Altura |
|---:|---|
| 3 | até 10 m, inclusive |
| 4 | acima de 10 m até 15 m, inclusive |
| 5 | acima de 15 m |

## Regra

O inspetor informa:

```text
Altura (m)
```

O sistema calcula automaticamente:

```text
impact_score
```

Exemplo:

```text
Altura = 12,5 m

Impacto na Segurança = 4
```

A pontuação não deve ser editável manualmente.

---

# 3. Risco de Queda de Materiais

O Risco de Queda de Materiais depende do:

1. tipo de dano;
2. condição encontrada.

Fluxo:

```text
Tipo de dano
      ↓
Condição
      ↓
Pontuação de risco
```

Cada condição já possui pontuação associada.

---

# 4. Tipos de dano

Opções:

```text
- Ausência de conjunto de fixação
- Ausência de cumeeira, goiva, calhas, suportes e outros
- Suporte de linha de vida
- Corrosão de telhas
- Deformação/Corte
- Adequação do telhado/fechamento lateral às normas vigentes
```

---

# 5. Ausência de conjunto de fixação

| Risco | Condição |
|---:|---|
| 1 | até 10%, inclusive |
| 2 | acima de 10% até 20%, inclusive |
| 3 | acima de 20% |

Exemplo:

```text
Tipo de dano:
Ausência de conjunto de fixação

Condição:
Maior que 20%

Risco de Queda = 3
```

---

# 6. Ausência de cumeeira, goiva, calhas, suportes e outros

| Risco | Condição |
|---:|---|
| 1 | até 10%, inclusive |
| 2 | acima de 10% até 20%, inclusive |
| 3 | acima de 20% |

---

# 7. Suporte de linha de vida

Na matriz do procedimento, existe condição apenas para pontuação 3.

| Risco | Condição |
|---:|---|
| 3 | Corrosão, deformação, danos de ligação |

Não existem condições definidas para:

```text
Risco 1
Risco 2
```

Essas opções não devem aparecer.

---

# 8. Corrosão de telhas

| Risco | Condição |
|---:|---|
| 1 | Corrosão pontual |
| 2 | Corrosão generalizada que não compromete a sua fixação |
| 3 | Corrosão generalizada que comprometa a sua fixação |

---

# 9. Deformação/Corte

| Risco | Condição |
|---:|---|
| 1 | Deformação pontual ou corte de até 20%, inclusive, que não comprometa sua fixação |
| 2 | Deformação ou corte acima de 20% da área e que não comprometa sua fixação |
| 3 | Deformação severa ou corte que comprometa a sua fixação |

---

# 10. Adequação do telhado/fechamento lateral às normas vigentes

A matriz apresenta:

| Risco | Condição |
|---:|---|
| 1 | Não |

Não existem condições definidas para:

```text
Risco 2
Risco 3
```

Essas opções não devem aparecer.

Referência normativa indicada no procedimento:

```text
NBR 14331
```

---

# 11. Cálculo da pontuação TEL

Fórmula:

```text
Pontuação TEL =
Impacto na Segurança × Risco de Queda de Materiais
```

Faixa possível:

```text
Mínimo:
3 × 1 = 3

Máximo:
5 × 3 = 15
```

---

# 12. Classificação final

| Classificação | Faixa | Recomendação |
|---|---:|---|
| TE-1 | 12–15 | Tratar em até 1 ano |
| TE-2 | 9–11 | Tratar em até 2 anos |
| TE-3 | 6–8 | Tratar em até 3 anos |
| TE-4 | 3–5 | Intervenção por oportunidade |

O sistema resolve automaticamente a classificação conforme a pontuação.

---

# 13. Exemplo completo

```text
Categoria:
TELHADO/TAPAMENTO

Altura:
12 m

Impacto na Segurança:
4

Tipo de dano:
Corrosão de telhas

Condição:
Corrosão generalizada que comprometa a sua fixação

Risco de Queda:
3

Pontuação:
4 × 3 = 12

Classificação:
TE-1

Recomendação:
Tratar em até 1 ano
```

---


# 14. Campos escolhidos pelo inspetor

O inspetor informa:

```text
Status
Altura (m)
Tipo de dano
Condição encontrada
```

---

# 15. Campos automáticos

O sistema calcula:

```text
Impacto na Segurança
Risco de Queda de Materiais
Pontuação TEL
Classificação TE
Recomendação
```

Esses campos são somente leitura.

---

# 16. Comportamento esperado no frontend

## Estado inicial

```text
Altura
[          ]

Tipo de dano
[ selecione ]
```

Após preencher a altura:

```text
Impacto na Segurança
[ automático ]
```

Após selecionar o tipo de dano:

```text
Condição encontrada
[ opções válidas daquele dano ]
```

Após selecionar a condição:

```text
Risco de Queda
[ automático ]

Pontuação TEL
[ automático ]

Classificação
[ automático ]

Recomendação
[ automático ]
```

---

# 17. Persistência

Não salvar somente a pontuação final.

Salvar também a origem do cálculo.

Exemplo:

```text
category: TEL

height_m: 12.0

impact_score: 4

damage_group: roof_corrosion
damage_group_label: Corrosão de telhas

damage_option: generalized_compromises_fixing
damage_option_label: Corrosão generalizada que comprometa a sua fixação

fall_risk_score: 3

classification_score: 12
classification_code: TE-1
classification_recommendation: Tratar em até 1 ano

catalog_version: 1
```

---

# 18. Estrutura sugerida do catálogo

Estrutura conceitual:

```text
TEL_CATALOG
├── impact_by_height
│   ├── up_to_10m_inclusive → 3
│   ├── above_10_to_15m_inclusive → 4
│   └── above_15m → 5
│
└── fall_risk_groups
    ├── missing_fixing_set
    │   └── options 1..3
    ├── missing_roof_components
    │   └── options 1..3
    ├── lifeline_support
    │   └── option 3
    ├── roof_corrosion
    │   └── options 1..3
    ├── deformation_or_cut
    │   └── options 1..3
    └── standards_compliance
        └── option 1
```

---

# 19. Validação mínima

Testar:

## Impacto

- altura até 10 m, inclusive → impacto 3;
- altura acima de 10 m até 15 m, inclusive → impacto 4;
- altura acima de 15 m → impacto 5;
- impedir edição manual do impacto.

## Risco

- carregar somente condições válidas do tipo de dano;
- Suporte de linha de vida aceitar somente risco 3;
- Adequação às normas aceitar somente risco 1;
- impedir risco manual sem condição válida.

## Classificação

- calcular corretamente `impacto × risco`;
- TE-1 entre 12 e 15;
- TE-2 entre 9 e 11;
- TE-3 entre 6 e 8;
- TE-4 entre 3 e 5;

## Histórico

- preservar altura;
- preservar tipo e condição do dano;
- preservar as pontuações;
- preservar classificação e recomendação;
- preservar versão do catálogo.
