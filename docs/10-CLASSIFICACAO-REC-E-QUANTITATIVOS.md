# 10 — Classificação REC e Quantitativos

> **Estado:** a matriz REC, seus campos técnicos, a resolução de GUT e os
> quantitativos nativos estão implementados no catálogo e no backend. Este texto
> também preserva referência técnica e exemplos de decisão; termos como
> “sugerida”, “esperado” ou “deve” descrevem a regra de produto, não uma garantia
> de que toda evolução prospectiva do documento já exista. Limites de histórico e
> resumo estão no [documento 17](17-AJUSTES-FINAIS-DOCUMENTOS-09-A-13.md).

## Objetivo

Este documento define a classificação e os quantitativos da categoria **REC**, incluindo as duas matrizes de Urgência (U) previstas no procedimento técnico:

1. **Função Estrutural** — matriz geral de Urgência REC;
2. **Transportadores do Pátio e Porto** — matriz específica para transportadores.

A nota `U` não deve ser digitada livremente pelo inspetor.

O inspetor escolhe a opção técnica correspondente e o sistema atribui automaticamente a nota `U`.

---

# 1. Fluxo no aplicativo

Ao preencher a Urgência de uma avaria REC, o primeiro campo deve ser:

## Critério de Urgência

Opções:

```text
- Função Estrutural
- Transportadores do Pátio e Porto
```

O restante do formulário muda conforme a opção escolhida.

---

# 2. Opção: Função Estrutural

Quando o inspetor selecionar:

```text
Critério de Urgência = Função Estrutural
```

o sistema deve apresentar as opções da matriz geral de Urgência REC.

Cada opção já possui uma nota `U` vinculada.

## U = 1 — Outras Estruturas

- Telas de guarda-corpo e de escadas
- Rodapé de guarda-corpo e de escadas
- Grade de escada de marinheiro

## U = 2 — Miscelâneas Estruturais

- Guarda-corpos
- Chapas/grades de piso
- Estruturas auxiliares de estruturas secundárias
- Tirantes de cobertura e tapamentos laterais
- Base de tanques, silos e chaminés
- Escada de marinheiro

## U = 3 — Estruturas Secundárias e suas conexões

- Estruturas de fixação de componentes
- Vigas de piso secundárias
- Terças de cobertura e fechamentos
- Estruturas de fechamento lateral
- Bases de acionamento instaladas sobre plataformas
- Bases de chutes
- Estruturas auxiliares de estruturas primárias

## U = 4 — Estruturas estabilizantes e elementos de fixação

- Montantes de treliças
- Contraventamentos
- Colunas de sustentação de equipamentos
- Vigas de sustentação de equipamentos
- Bases de sustentação de equipamentos
- Vigas de piso principais
- Teto de tanques/silos
- Cavalete estrutural de sustentação

## U = 5 — Estruturas principais e suas conexões

- Colunas de sustentação de edifícios
- Vigas principais de elevações
- Tesouras de coberturas
- Pórticos e vigas de ponte rolante e talhas de ligação
- Tirantes de estruturas em balanço
- Banzos e diagonais de treliças
- Costado de tanques, silos e chaminés
- Mão francesa e talas de ligação

### Exemplo

```text
Critério de Urgência:
Função Estrutural

Elemento:
Vigas de piso principais

Resultado:
U = 4
```

---

# 3. Opção: Transportadores do Pátio e Porto

Quando o inspetor selecionar:

```text
Critério de Urgência = Transportadores do Pátio e Porto
```

o sistema deve solicitar um segundo campo.

## Tipo do transportador

Opções:

```text
- Transportador Elevado — Galeria
- Transportador Elevado — Ponte Treliçada
- Transportador a Nível do Piso
```

Após escolher o tipo, o sistema deve apresentar somente os elementos válidos daquela matriz.

---

# 4. Transportador Elevado — Galeria

| U | Elementos |
|---:|---|
| 1 | — |
| 2 | Transversina |
| 3 | Diagonal / Montante; Viga de piso principal |
| 4 | Mão francesa; Banzo superior; Contraventamento; Montante de apoio |
| 5 | Coluna; Viga principal; Pino de ligação rotulada ou apoio deslizante; Banzo inferior; Talas de ligação |

### Exemplo

```text
Critério de Urgência:
Transportadores do Pátio e Porto

Tipo:
Transportador Elevado — Galeria

Elemento:
Banzo superior

Resultado:
U = 4
```

---

# 5. Transportador Elevado — Ponte Treliçada

| U | Elementos |
|---:|---|
| 1 | — |
| 2 | Transversina |
| 3 | Diagonal / Montante; Perfil passadiço |
| 4 | Mão francesa; Montante de apoio; Contraventamento; Viga de piso secundário |
| 5 | Coluna; Viga de piso principal; Banzos; Pino de ligação rotulada ou apoio deslizante; Talas de ligação |

### Exemplo

```text
Critério de Urgência:
Transportadores do Pátio e Porto

Tipo:
Transportador Elevado — Ponte Treliçada

Elemento:
Diagonal / Montante

Resultado:
U = 3
```

---

# 6. Transportador a Nível do Piso

| U | Elementos |
|---:|---|
| 1 | — |
| 2 | Viga |
| 3 | Travessa / Longarina |
| 4 | Montante / Coluneta |
| 5 | — |

### Exemplo

```text
Critério de Urgência:
Transportadores do Pátio e Porto

Tipo:
Transportador a Nível do Piso

Elemento:
Montante / Coluneta

Resultado:
U = 4
```

---

# 7. Regra de seleção

As duas matrizes não devem ser misturadas em uma única lista.

O fluxo deve ser:

```text
URGÊNCIA (U)
│
├── Função Estrutural
│   └── Elemento da matriz geral
│       └── U automático
│
└── Transportadores do Pátio e Porto
    └── Tipo do transportador
        └── Elemento da matriz específica
            └── U automático
```

Isso é necessário porque elementos semelhantes podem possuir notas diferentes conforme o contexto estrutural.

---

# 8. Regra de edição

A nota `U`:

- não deve ser digitável;
- não deve possuir override manual;
- deve ser resultado da opção técnica selecionada.

O inspetor escolhe a descrição técnica.

O sistema resolve a nota.

---

# 9. Persistência

Para permitir histórico e auditoria, não salvar apenas:

```text
U = 4
```

Salvar também a origem da nota.

## Matriz geral

Exemplo:

```text
urgency_matrix: structural_function
urgency_option: main_floor_beam
urgency_label: Vigas de piso principais
urgency_score: 4
```

## Transportadores

Exemplo:

```text
urgency_matrix: patio_port_transporter
transporter_type: elevated_truss_bridge
urgency_option: diagonal_or_post
urgency_label: Diagonal / Montante
urgency_score: 3
```

---

# 10. Estrutura sugerida do catálogo

A matriz geral e a matriz de transportadores devem ser mantidas separadamente.

Exemplo conceitual:

```text
REC_URGENCY
├── structural_function
│   ├── option...
│   └── option...
│
└── patio_port_transporter
    ├── elevated_gallery
    │   ├── option...
    │   └── option...
    │
    ├── elevated_truss_bridge
    │   ├── option...
    │   └── option...
    │
    └── floor_level
        ├── option...
        └── option...
```

Não adicionar os elementos da matriz específica diretamente à lista geral `REC_URGENCY`, pois isso pode criar conflitos de nota.

---

# 11. Comportamento do frontend

## Estado inicial

```text
Critério de Urgência
[ selecione ]
```

Nenhuma nota U deve aparecer antes da escolha.

## Função Estrutural

```text
Critério de Urgência
Função Estrutural

Elemento
[ selecione uma função estrutural ]

U
[ calculado automaticamente ]
```

## Transportadores do Pátio e Porto

```text
Critério de Urgência
Transportadores do Pátio e Porto

Tipo do transportador
[ selecione ]

Elemento
[ selecione ]

U
[ calculado automaticamente ]
```

---

# 12. Validação

Testes mínimos:

- impedir nota U sem opção técnica;
- impedir alteração manual da nota;
- carregar matriz geral quando `structural_function`;
- carregar matriz específica quando `patio_port_transporter`;
- exigir tipo do transportador quando matriz específica;
- exibir somente elementos válidos para o tipo selecionado;
- resolver corretamente a nota de cada opção;
- preservar matriz, tipo, opção, descrição e nota no snapshot;
- não permitir elementos da matriz geral dentro da matriz específica e vice-versa;
- preservar avaliações históricas caso o catálogo seja alterado no futuro.

---

# 13. Fonte técnica

Baseado no procedimento:

```text
T000000-S-2PO006 — Procedimento de Inspeção de Estruturas e Priorização de Avarias — Rev. 04
```

Referências principais:

- Tabela 2 — Tabela de Urgência para matriz GUT de REC;
- Tabela 3 — Tabela de Urgência para matriz GUT de REC específica para transportadores do Pátio e Porto.

## Gravidade, Tendência e quantitativos REC

### Gravidade (G)

O inspetor registra **Impacto na Segurança** e **Impacto no Ativo**; a nota é a maior das duas.

| Nota | Impacto na Segurança | Impacto no Ativo |
|---:|---|---|
| 1 | Sem possibilidade de acidente | Dano/ausência secundária sem impacto no ativo |
| 2 | Elemento secundário; acidente até 2 m | Dano pontual em primário de ativo C/D |
| 3 | Elemento secundário; acidente acima de 2 m | Dano pontual em primário de ativo A/B |
| 4 | Elemento primário; acidente até 2 m | Dano generalizado/ausência em primário de ativo C/D |
| 5 | Elemento primário; acidente acima de 2 m | Dano generalizado/ausência em primário de ativo A/B |

```text
G = maior nota entre Impacto na Segurança e Impacto no Ativo
```

### Tendência (T)

O inspetor seleciona primeiro o dano e depois apenas uma condição válida. A matriz contém os grupos **Ligação parafusada**, **Descontinuidade**, **Deformação**, **Perda de espessura** e **Corte ou furo**. As notas não devem ser digitadas livremente.

- Ligação parafusada: T1 até 10% sem aperto; T2 abertura/alargamento não projetado ou solda em substituição; T3 de 10% a 30% ou parafuso não especificado; T4 de 30% a 50%; T5 perda de função acima de 50%.
- Descontinuidade: somente T4 (trinca visível ou solda insuficiente) e T5 (rompimento ou trinca ao longo do perfil/ligação).
- Deformação: T1 pontual sem excentricidade; T2 global sem excentricidade; T3 leve; T4 moderada; T5 severa.
- Perda de espessura: T2 localizada de 10% a 20%; T3 generalizada de 10% a 20%; T4 localizada acima de 20%; T5 generalizada acima de 20%.
- Corte ou furo: T1 abaixo de 10% da seção; T2 ausência de componente secundário; T3 de 10% a 20%; T4 de 20% a 50%; T5 acima de 50% ou ausência de componente principal.

Localizada significa até 30% da seção do perfil ou da área da chapa; generalizada, acima de 30%.

### Quantitativos

A unidade principal é `kg`, com densidade de aço de `7.850 kg/m³`. O **Elemento REC** é obrigatório e determina os campos e a fórmula. Cada elemento calculável é registrado como um item independente e recebe uma Quantidade inteira positiva; itens legados com quantidade decimal permanecem editáveis para preservar seu histórico. Elementos de peso manual não usam multiplicador.

```text
Peso total = Peso unitário × Quantidade
```

| Elemento | Peso unitário |
|---|---|
| Perfil W | `7.850 × (((M × EM × 2) + ((A - 2 × EA) × EA)) / 1.000.000) × C` |
| Perfil L | `7.850 × (((L × E) + ((L - E) × E)) / 1.000.000) × C` |
| Perfil U | `7.850 × ((A × EA) + ((L - EA) × EM × 2)) / 1.000.000 × C` |
| Perfil UE | `7.850 × ((A × EA) + 2 × ((M - EA) × EM) + 2 × ((D - EM) × EM)) / 1.000.000 × C` |
| Chapa lisa, barra chata, chapa xadrez | `7.850 × L × C × E / 1.000.000.000` |
| Guarda-corpo | `C × 30` |
| Escada marinheiro | `C × 60` |
| Perfil tubular | `((π × (D² - (D - 2 × E)²) / 4) / 1.000.000) × (C / 1000) × 7.850` |
| Perfil L desigual | `7.850 × ((A1 × E) + ((A2 - E) × E)) / 1.000.000 × C` |
| Metalon | `7.850 × (((A1 - 2 × E) × E × 2) + (A2 × E × 2)) × C / 1.000.000` |
| Perfil T | `7.850 × ((M × E) + ((A - E) × E)) / 1.000.000 × C` |

`LIGAÇÃO PARAF.`, `TELHAS` e `GRADE DE PISO` não têm fórmula nas fontes analisadas: o inspetor informa o peso total manual em kg, identificado como manual. O arredondamento é apenas de apresentação, conforme o documento 09.
