# 11 — Classificação CIVIL e Quantitativos

> **Estado:** as matrizes CIVIL, a resolução de GUT e o cálculo por itens de volume
> estão implementados no catálogo, backend e interface. Este documento conserva a
> referência técnica para as opções e faixas; trechos de comportamento “esperado”
> não devem substituir as limitações verificadas no
> [documento 17](17-AJUSTES-FINAIS-DOCUMENTOS-09-A-13.md).

## Objetivo

Este documento detalha os ajustes necessários para a categoria **CIVIL (CV)** na classificação GUT, com foco em:

1. separar corretamente os contextos da **Urgência (U)**;
2. impedir que a **Tendência (T)** seja tratada como uma lista genérica de 1 a 5;
3. apresentar ao inspetor somente as opções técnicas válidas para o contexto selecionado;
4. vincular cada opção técnica à respectiva nota, sem permitir edição manual da nota.

Fonte técnica:

```text
T000000-S-2PO006 — Procedimento de Inspeção de Estruturas e Priorização de Avarias — Rev. 04
```

Referências principais:

- Tabela 11 — Tabela de Urgência para matriz GUT de Civil;
- Tabela 12 — Tabela de Tendência para matriz GUT de Civil.

---

# 1. Princípio geral

No CIVIL, as notas `U` e `T` não devem ser digitadas livremente.

O inspetor escolhe a **descrição técnica aplicável**, e o sistema associa automaticamente a nota correspondente.

Exemplo:

```text
Tipo de degradação:
Emenda dos trilhos

Condição:
Folga na emenda dos trilhos

Resultado:
T = 1
```

A nota calculada deve ficar somente leitura.

---

# 2. Urgência (U) — fluxo

Antes de escolher o elemento, o inspetor deve selecionar o contexto da Urgência:

```text
Critério de Urgência

- Função
- Caminho de Rolamento de Máquinas
```

As duas listas não devem ficar misturadas.

Fluxo:

```text
URGÊNCIA (U)
│
├── Função
│   └── Elemento / função estrutural
│       └── U automático
│
└── Caminho de Rolamento de Máquinas
    └── Elemento do caminho de rolamento
        └── U automático
```

---

# 3. Urgência — Função

Quando:

```text
Critério de Urgência = Função
```

o sistema utiliza o bloco geral da Tabela 11.

## U = 1 — Estruturas de vedação

- Paredes de alvenaria, sem finalidade estrutural
- Ligações chumbadas sem relevância à segurança, como fixação de guarda-corpos

## U = 2 — Estruturas auxiliares

- Guarda-corpos de concreto
- Estruturas de sustentação de telhados/coberturas
- Ligações chumbadas cuja falha possibilite queda de objetos, como fixação de monovias

## U = 3 — Estruturas secundárias

- Estruturas de escadas
- Ligações chumbadas de componentes essenciais, como equipamentos ou componentes estruturais importantes
- Vigas baldrame
- Blocos de coroamento
- Estruturas auxiliares de estruturas primárias, como mísulas

## U = 4 — Estruturas estabilizantes / elementos de fixação

- Vigas secundárias de elevações
- Colunas de sustentação de equipamentos
- Vigas de sustentação de equipamentos
- Bases de sustentação de equipamentos
- Lajes sem acesso de pessoas
- Estruturas de reforço do solo, como terra armada

## U = 5 — Estruturas principais

- Colunas de sustentação de edifícios
- Vigas principais de elevações
- Pórticos e vigas de ponte rolante
- Tirantes e mão-francesa de estruturas em balanço
- Lajes com acesso de pessoas
- Fundações em geral
- Estruturas de contenção do solo, como muros de arrimo e cortinas atirantadas
- Costado de tanques, silos e chaminés

> As opções acima devem seguir a posição indicada na Tabela 11. Caso haja divergência entre catálogos antigos e esta matriz, deve prevalecer o procedimento técnico vigente.

---

# 4. Urgência — Caminho de Rolamento de Máquinas

Quando:

```text
Critério de Urgência = Caminho de Rolamento de Máquinas
```

o sistema deve apresentar somente as opções específicas desse bloco da Tabela 11.

| U | Elemento |
|---:|---|
| 3 | Estrutura de fixação de componentes |
| 3 | Trilhos |
| 4 | Lastro e Dormentes |
| 4 | Emendas do trilho |

Não existem opções definidas nesse bloco para:

```text
U1
U2
U5
```

Essas notas não devem aparecer como opções selecionáveis nesse contexto.

### Exemplo

```text
Critério de Urgência:
Caminho de Rolamento de Máquinas

Elemento:
Lastro e Dormentes

Resultado:
U = 4
```

---

# 5. Tendência (T) — regra geral

A Tendência CIVIL depende do **tipo de degradação**.

Fluxo:

```text
Tipo de degradação
        ↓
carrega somente as condições válidas
        ↓
inspetor seleciona a condição
        ↓
T = nota vinculada à condição
```

Não deve existir um seletor genérico:

```text
T = 1 / 2 / 3 / 4 / 5
```

sem relação com o tipo de degradação.

---

# 6. Tipos de degradação e notas permitidas

| Tipo de degradação | Notas T válidas |
|---|---|
| Fissuração | 1, 2, 3, 4, 5 |
| Segregação e Desagregação | 1, 2, 3, 4, 5 |
| Corrosão em armaduras | 1, 2, 3, 4, 5 |
| Efeitos químicos | 1, 2, 3 |
| Deformação permanente e deslocamentos | 1, 2, 3, 4, 5 |
| Execução em desconformidade com projeto | 1, 2, 3, 4, 5 |
| Infiltração | 1, 2, 3, 4, 5 |
| Chumbadores | 1, 2, 3, 4, 5 |
| Inclinação longitudinal dos trilhos | 1, 2, 3, 4, 5 |
| Curvatura vertical dos trilhos | 1, 2, 3, 4, 5 |
| Curvatura lateral dos trilhos | 1, 2, 3, 4, 5 |
| Desnível entre trilhos | 1, 2, 3, 4, 5 |
| Desgaste dos trilhos | 1, 2, 3 |
| Fixação dos trilhos | 1, 2, 3 |
| Dormentes | 1, 2, 3 |
| Emenda dos trilhos | 1, 2 |

---

# 7. Tendência — Fissuração

| T | Descrição |
|---:|---|
| 1 | Fissuração superficial, cuja orientação não remeta a estados limites |
| 2 | Fissuras em elementos de concreto armado com abertura compatível com a CAA do ativo |
| 3 | Trincas em elementos de concreto armado com abertura entre a admitida para a CAA e 2,0 mm |
| 4 | Rachaduras em elementos de concreto armado com abertura superior a 2,0 mm |
| 5 | Rachaduras não superficiais em elementos protendidos e/ou relacionadas a mecanismos estruturais |

---

# 8. Tendência — Segregação e Desagregação

| T | Descrição |
|---:|---|
| 1 | Perda da camada de proteção superficial do concreto, tal como pintura |
| 2 | Abrasão superficial do concreto, com perda de parte do cobrimento nominal sem armadura aparente |
| 3 | Perda de concreto em pequenas áreas, entre 0,1 m² e 0,5 m², com ou sem armadura aparente |
| 4 | Perda de concreto em área superior a 0,5 m², com ou sem armadura aparente |
| 5 | Rompimento do concreto, com armadura aparente |

---

# 9. Tendência — Corrosão em armaduras

| T | Descrição |
|---:|---|
| 1 | Cobrimento insuficiente com ou sem desplacamento de concreto. Armadura não exposta |
| 2 | Armadura exposta, sem indício de corrosão ou perda de espessura |
| 3 | Armadura principal exposta e corroída, com perda de seção inferior a 15% da seção transversal original; ou armadura secundária exposta e corroída com perda de seção maior que 15% |
| 4 | Armadura principal exposta e corroída, com perda de seção entre 15% e 50% da seção transversal original; elementos estruturais com deformação acima do permitido em norma |
| 5 | Perda de mais de 50% da seção transversal do vergalhão em pelo menos uma das barras da armadura principal; armadura protendida exposta; chumbador exposto, com ou sem corrosão |

---

# 10. Tendência — Efeitos químicos

| T | Descrição |
|---:|---|
| 1 | Lixiviação e carbonatação no concreto não generalizada |
| 2 | Lixiviação no concreto e eflorescência com formação de estalactites de maneira não generalizada |
| 3 | Lixiviação no concreto e eflorescência, com ou sem formação de estalactites, de maneira generalizada |

Não existem condições definidas na matriz para:

```text
T4
T5
```

Essas opções não devem ser exibidas.

---

# 11. Tendência — Deformação permanente e deslocamentos

| T | Descrição |
|---:|---|
| 1 | Deformação leve, causada por evento não recorrente |
| 2 | Deformação média, causada por evento não recorrente |
| 3 | Deformação leve em região de manutenção e/ou passagem de veículos e equipamentos |
| 4 | Deformação severa em região de manutenção e/ou passagem de veículos |
| 5 | Deformação com influência global no elemento, causada por excentricidade de carga |

---

# 12. Tendência — Execução em desconformidade com projeto

| T | Descrição |
|---:|---|
| 1 | Inconformidade de elementos não estruturais |
| 2 | Redução das dimensões em até 10% para estruturas auxiliares ou secundárias; diâmetro da armação secundária diminuído para o primeiro diâmetro comercial abaixo do especificado em projeto; espaçamento das armações aumentado em até 10% em lajes, cisalhamento e pele |
| 3 | Redução das dimensões em até 20% para estruturas auxiliares ou secundárias; redução das dimensões em até 10% para elementos principais; alterações significativas das premissas e dimensionamento que não prejudicam a segurança estrutural |
| 4 | Inclusão de solicitações adicionais em até 20% das cargas previstas em projeto; redução das dimensões em até 15% para elementos principais e superior a 20% para demais elementos; diâmetro das armaduras principais diminuído para o primeiro diâmetro comercial abaixo do especificado em projeto |
| 5 | Inclusão de solicitações que ultrapassem 20% das cargas previstas ou cargas significativas de difícil mensuração; alterações significativas das premissas de projeto; corpos de prova não atendem à resistência de projeto; dimensões de estruturas principais e armação severamente abaixo do indicado em projeto |

---

# 13. Tendência — Infiltração

| T | Descrição |
|---:|---|
| 1 | Infiltração insignificante e localizada. Ausência de consequências estruturais ou superficiais para o concreto |
| 2 | Infiltração recorrente. Presença não generalizada de concreto deteriorado por abrasão |
| 3 | Infiltração recorrente com fluxo sem pressão. Acúmulo de água dificulta a operação do local. Pontos de abrasão generalizada, sem exposição de armadura. Presença mínima ou inexistente de trincas |
| 4 | Infiltração generalizada. Presença de trincas pontuais por onde extravasa água com pressão. Acúmulo de água paralisa as operações do local recorrentemente |
| 5 | Infiltração generalizada. Presença generalizada de trincas e rachaduras, com extravasamento de água com pressão. Desplacamento de concreto com armadura exposta devido à pressão da água. Acúmulo de água compromete a segurança das pessoas e a integridade dos ativos |

---

# 14. Tendência — Chumbadores

| T | Descrição |
|---:|---|
| 1 | Corrosão generalizada do chumbador sem perda de função estrutural |
| 2 | Perda de espessura de até 30% na rosca e porca |
| 3 | Perda de espessura superior a 30% na rosca e porca |
| 4 | Fissura ou trincas no corpo do chumbador |
| 5 | Ausência de chumbador ou perda total de rosca e porca |

---

# 15. Tendência — Caminho de Rolamento de Máquinas

## 15.1 Inclinação longitudinal dos trilhos

| T | Descrição |
|---:|---|
| 1 | Até 3 mm acima do admissível |
| 2 | Até 6 mm acima do admissível |
| 3 | Até 12 mm acima do admissível |
| 4 | Até 18 mm acima do admissível |
| 5 | Mais de 18 mm acima do admissível |

## 15.2 Curvatura vertical dos trilhos

| T | Descrição |
|---:|---|
| 1 | Até 2 mm acima do admissível |
| 2 | Até 3 mm acima do admissível |
| 3 | Até 5 mm acima do admissível |
| 4 | Até 8 mm acima do admissível |
| 5 | Mais de 8 mm acima do admissível |

## 15.3 Curvatura lateral dos trilhos

| T | Descrição |
|---:|---|
| 1 | Até 2 mm acima do admissível |
| 2 | Até 3 mm acima do admissível |
| 3 | Até 5 mm acima do admissível |
| 4 | Até 8 mm acima do admissível |
| 5 | Mais de 8 mm acima do admissível |

## 15.4 Desnível entre trilhos

| T | Descrição |
|---:|---|
| 1 | Até 3 mm acima do admissível |
| 2 | Até 5 mm acima do admissível |
| 3 | Até 10 mm acima do admissível |
| 4 | Até 15 mm acima do admissível |
| 5 | Mais de 15 mm acima do admissível |

## 15.5 Desgaste dos trilhos

| T | Descrição |
|---:|---|
| 1 | Desgaste vertical |
| 2 | Desgaste lateral |
| 3 | Corrugação |

Não existem condições definidas para `T4` e `T5`.

## 15.6 Fixação dos trilhos

| T | Descrição |
|---:|---|
| 1 | Falta de aperto em fixadores |
| 2 | Corrosão com perda de espessura superior a 20% |
| 3 | Ausência de fixadores |

Não existem condições definidas para `T4` e `T5`.

## 15.7 Dormentes

| T | Descrição |
|---:|---|
| 1 | Rachaduras |
| 2 | Assentamento desnivelado |
| 3 | Recalque diferencial |

Não existem condições definidas para `T4` e `T5`.

## 15.8 Emenda dos trilhos

| T | Descrição |
|---:|---|
| 1 | Folga na emenda dos trilhos |
| 2 | Alinhamento na emenda dos trilhos |

Não existem condições definidas para:

```text
T3
T4
T5
```

Essas opções não devem ser exibidas.

---

# 16. Comportamento esperado no frontend

## Urgência

### Estado inicial

```text
Critério de Urgência
[ selecione ]
```

### Função

```text
Critério de Urgência
Função

Elemento / Função estrutural
[ selecione ]

U
[ automático ]
```

### Caminho de Rolamento de Máquinas

```text
Critério de Urgência
Caminho de Rolamento de Máquinas

Elemento
[ selecione ]

U
[ automático ]
```

---

## Tendência

### Estado inicial

```text
Tipo de degradação
[ selecione ]
```

Após selecionar:

```text
Tipo de degradação
Emenda dos trilhos

Condição
- T1 — Folga na emenda dos trilhos
- T2 — Alinhamento na emenda dos trilhos

T
[ automático ]
```

Não mostrar notas sem descrição válida.

---

# 17. Persistência

Não salvar apenas a nota.

## Urgência

Exemplo:

```text
urgency_context: machine_running_path
urgency_option: rail_joint
urgency_label: Emendas do trilho
urgency_score: 4
```

## Tendência

Exemplo:

```text
trend_group: rail_joint
trend_group_label: Emenda dos trilhos

trend_option: joint_gap
trend_option_label: Folga na emenda dos trilhos

trend_score: 1
```

O snapshot histórico deve preservar:

- contexto;
- código da opção;
- descrição exibida;
- nota;
- versão do catálogo.

---

# 18. Ajuste necessário no catálogo atual

O catálogo CIVIL não deve manter uma única lista plana de Urgência contendo simultaneamente:

- opções de Função;
- opções de Caminho de Rolamento de Máquinas.

Também não deve manter grupos de Tendência sem suas opções.

Estrutura conceitual sugerida:

```text
CIVIL_URGENCY
├── function
│   └── options[]
└── machine_running_path
    └── options[]

CIVIL_TREND_GROUPS
├── cracking
│   └── options T1...T5
├── chemical_effects
│   └── options T1...T3
├── rail_wear
│   └── options T1...T3
├── rail_joint
│   └── options T1...T2
└── ...
```

---

# 19. Validação mínima

## Urgência

Testar:

- exigir escolha do contexto;
- carregar somente opções daquele contexto;
- `Função` não exibir opções de caminho de rolamento;
- `Caminho de Rolamento de Máquinas` exibir somente:
  - Estrutura de fixação de componentes → U3
  - Trilhos → U3
  - Lastro e Dormentes → U4
  - Emendas do trilho → U4
- impedir edição manual de U.

## Tendência

Testar:

- cada tipo de degradação carrega somente notas existentes;
- Efeitos químicos não aceita T4/T5;
- Desgaste dos trilhos não aceita T4/T5;
- Fixação dos trilhos não aceita T4/T5;
- Dormentes não aceita T4/T5;
- Emenda dos trilhos aceita somente T1/T2;
- impedir edição manual de T;
- salvar opção e descrição além da nota;
- preservar o snapshot histórico.

## Gravidade e quantitativos CIVIL

### Gravidade (G)

O inspetor informa **Impacto de Segurança** e **Impacto do Ativo**. Cada opção tem uma nota técnica associada e o sistema calcula:

```text
G = maior nota entre Impacto de Segurança e Impacto do Ativo
```

As opções e as duas notas de entrada devem ser preservadas no snapshot, além da gravidade calculada.

### Quantitativo

A unidade principal é `m³`. O inspetor registra cada medição como um item independente e informa Comprimento, Altura, Largura e Quantidade. Para novos itens, a quantidade é um número inteiro positivo; itens legados com quantidade decimal permanecem editáveis para preservar seu histórico.

```text
M³ do item = Comprimento × Altura × Largura × Quantidade
```

Exemplo: 2,00 m × 0,50 m × 0,30 m × 2 resulta em 0,60 m³ para o item. Uma segunda ocorrência pode ser registrada como outro item e entra na soma da avaliação.

### Classificação e validação complementar

Aplicar `GUT = G × U × T` e as faixas CV do documento 09. Validar o cálculo de volume unitário e total, a impossibilidade de alterar G/U/T calculados, a persistência dos impactos e das escolhas de urgência/tendência, e o uso do snapshot em relatórios históricos.
