# Classificação TEL — Telhado/Tapamento

> Matriz técnica aplicada pelo catálogo nativo. TEL usa pontuação própria
> e não possui quantitativo técnico.

## Objetivo

Este documento define a lógica da categoria **TEL — Telhado/Tapamento**, correspondente à matriz de classificação de:

- telhados;
- tapamentos/fechamentos laterais.

A categoria aparece no sistema ao lado de:

```text
CIVIL
TAC
REC
TELHADO/TAPAMENTO
ESTRUTURAS SOLIDÁRIAS
```

Código persistido:

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

## Fluxo geral

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

## Impacto na Segurança

O Impacto na Segurança é definido pela altura do elemento em relação ao solo.

### Tabela de pontuação

| Pontuação | Altura |
|---:|---|
| 3 | até 10 m, inclusive |
| 4 | acima de 10 m até 15 m, inclusive |
| 5 | acima de 15 m |

### Regra

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

## Risco de Queda de Materiais

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

## Tipos de dano

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

## Ausência de conjunto de fixação

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

## Ausência de cumeeira, goiva, calhas, suportes e outros

| Risco | Condição |
|---:|---|
| 1 | até 10%, inclusive |
| 2 | acima de 10% até 20%, inclusive |
| 3 | acima de 20% |

---

## Suporte de linha de vida

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

## Corrosão de telhas

| Risco | Condição |
|---:|---|
| 1 | Corrosão pontual |
| 2 | Corrosão generalizada que não compromete a sua fixação |
| 3 | Corrosão generalizada que comprometa a sua fixação |

---

## Deformação/Corte

| Risco | Condição |
|---:|---|
| 1 | Deformação pontual ou corte de até 20%, inclusive, que não comprometa sua fixação |
| 2 | Deformação ou corte acima de 20% da área e que não comprometa sua fixação |
| 3 | Deformação severa ou corte que comprometa a sua fixação |

---

## Adequação do telhado/fechamento lateral às normas vigentes

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

## Cálculo da pontuação TEL

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

## Classificação final

| Classificação | Faixa | Recomendação |
|---|---:|---|
| TE-1 | 12–15 | Tratar em até 1 ano |
| TE-2 | 9–11 | Tratar em até 2 anos |
| TE-3 | 6–8 | Tratar em até 3 anos |
| TE-4 | 3–5 | Intervenção por oportunidade |

O sistema resolve automaticamente a classificação conforme a pontuação.

---
