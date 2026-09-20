# 09 — Classificação GUT e Quantitativos

## Objetivo

Este documento define as regras nativas de:

- categorias de avaria;
- campos e opções apresentados ao inspetor;
- critérios de Gravidade, Urgência e Tendência;
- cálculo automático de GUT;
- classificação final por categoria;
- quantitativos e fórmulas automáticas;
- regras de persistência e histórico.

O documento é universal para as categorias **CIVIL (CV)**, **TAC** e **REC**.

Fonte técnica principal para GUT e classificações:

- `T000000-S-2PO006 — Procedimento de Inspeção de Estruturas e Priorização de Avarias — Rev. 04`

As planilhas de quantitativo permanecem como fonte das fórmulas e unidades de quantitativo.

---

# 1. Fonte de verdade no sistema

O catálogo é fixo no código e compartilhado por todas as organizações.

Componentes previstos:

- `App\Enums\DefectCategory`
- `NativeDefectCatalog`
- `DefectClassificationDefinition`
- `GutClassificationResolver`
- `NativeQuantityCatalog`
- `NativeDefectQuantityCalculator`

A versão vigente do catálogo GUT é **2**. A versão vigente das fórmulas nativas de
quantitativo é **1**.

Não existem telas de cadastro ou edição das regras GUT.

Alterações futuras nas regras devem ser versionadas para preservar o histórico.

---

# 2. Princípio de preenchimento pelo inspetor

O inspetor **não escolhe diretamente a classificação final** (`CV-1`, `TA-2`, `IE-3` etc.).

Nos campos técnicos, o sistema apresenta:

```text
NOTA + DESCRIÇÃO TÉCNICA
```

Exemplo:

```text
T = 4 — Perda de espessura localizada acima de 20%
```

Assim, a nota continua visível para o inspetor, mas a escolha é orientada pelo critério técnico oficial.

O sistema calcula automaticamente:

```text
GUT = G × U × T
```

e resolve a classificação final conforme a categoria.

Os campos calculados são somente leitura.

---

# 3. Condição da avaliação

As categorias utilizam as seguintes condições:

- `new` — Nova;
- `reinspected` — Reinspecionada;
- `reclassified` — Reclassificada;
- `canceled` — Cancelada;
- `canceled_sr` — Cancelada S/R;
- `treated` — Tratada.

Nova, Reinspecionada e Reclassificada exigem GUT, quantitativo e fotos. Tratada
exige quantitativo e fotos, limpa o GUT atual e marca a avaria como reparada.
Cancelada e Cancelada S/R exigem motivo, dispensam GUT, quantitativo e fotos e não
alteram o status da avaria. A primeira avaliação pode usar qualquer situação para
registrar avarias preexistentes; reinspeções não aceitam Nova. Os estados `draft`
e `complete` da avaliação e os estados da avaria continuam independentes dessas
condições.

Condições com evidência também exigem mapa processado e localização confirmada.
A cor da localização é automática: usa `classification_snapshot.color` quando
existe classificação final; tratadas e avaliações ainda sem GUT usam o neutro
`#64748B`. Cor e estilo nunca são aceitos do cliente.

---

# 4. Classificações GUT

## 4.1 CIVIL / CV

```text
G = 1 a 5
U = 1 a 5
T = 1 a 5

GUT = G × U × T
```

| Classificação | Faixa GUT | Recomendação |
|---|---:|---|
| CV-1 | 75–125 | Tratar em até 1 ano |
| CV-2 | 36–74 | Tratar em até 2 anos |
| CV-3 | 16–35 | Tratar em até 3 anos |
| CV-4 | 8–15 | Intervenção por oportunidade |
| CV-5 | 1–7 | Registro de condição |

---

## 4.2 TAC

```text
G = 1 a 3
U = 1 a 5
T = 1 a 5

GUT = G × U × T
```

| Classificação | Faixa GUT | Recomendação |
|---|---:|---|
| TA-1 | 45–75 | Tratar em até 1 ano |
| TA-2 | 25–44 | Tratar em até 3 anos |
| TA-3 | 15–24 | Tratar em até 5 anos |
| TA-4 | 9–14 | Intervenção por oportunidade |
| TA-5 | 3–8 | Registro de condição |

Os produtos TAC `1` e `2` permanecem sem classificação. Eles ainda podem ser
publicados quando os demais requisitos da avaliação estiverem atendidos.

---

## 4.3 REC

```text
G = 1 a 5
U = 1 a 5
T = 1 a 5

GUT = G × U × T
```

| Classificação | Faixa GUT | Recomendação |
|---|---:|---|
| IE-1 | 75–125 | Tratar em até 1 ano |
| IE-2 | 36–74 | Tratar em até 2 anos |
| IE-3 | 16–35 | Tratar em até 3 anos |
| IE-4 | 8–15 | Intervenção por oportunidade |
| IE-5 | 1–7 | Registro de condição |

> Os prazos devem ser tratados como **anos**, conforme o procedimento. Não substituir automaticamente por 365/720/1080 dias sem regra funcional específica.

---

# 5. Campos GUT — REC

## 5.1 Gravidade — G

O inspetor escolhe duas avaliações separadas:

1. **Impacto na Segurança**
2. **Impacto no Ativo**

Cada opção já possui uma nota associada.

### Impacto na Segurança

| Nota | Descrição |
|---:|---|
| 1 | Sem possibilidade de acidente |
| 2 | Dano em elemento estrutural secundário, com possibilidade de acidente até 2 m |
| 3 | Dano em elemento estrutural secundário, com possibilidade de acidente acima de 2 m |
| 4 | Dano em elemento estrutural primário, com possibilidade de acidente até 2 m |
| 5 | Dano em elemento estrutural primário, com possibilidade de acidente acima de 2 m |

### Impacto no Ativo

| Nota | Descrição |
|---:|---|
| 1 | Dano ou ausência de elemento estrutural secundário que não impacta o ativo |
| 2 | Dano pontual em elemento estrutural primário de ativos de baixa criticidade (C e D) |
| 3 | Dano pontual em elemento estrutural primário de ativos de alta criticidade (A e B) |
| 4 | Dano generalizado ou ausência de elemento estrutural primário de ativos de baixa criticidade (C e D) |
| 5 | Dano generalizado ou ausência de elemento estrutural primário de ativos de alta criticidade (A e B) |

### Regra

```text
G = maior nota entre Impacto na Segurança e Impacto no Ativo
```

Os dois critérios escolhidos e a nota final `G` devem ser armazenados.

---

## 5.2 Urgência — U

O inspetor seleciona a função estrutural.

Cada opção é apresentada com sua nota correspondente.

### U = 1

- Telas de guarda-corpo e de escadas
- Rodapé de guarda-corpo e de escadas
- Grade de escada de marinheiro

### U = 2

- Guarda-corpos
- Chapas/grades de piso
- Estruturas auxiliares de estruturas secundárias
- Tirantes de cobertura e tapamentos laterais
- Base de tanques, silos e chaminés
- Escada de marinheiro

### U = 3

- Estruturas de fixação de componentes
- Vigas de piso secundárias
- Terças de cobertura e fechamentos
- Estruturas de fechamento lateral
- Bases de acionamento instaladas sobre plataformas
- Bases de chutes
- Estruturas auxiliares de estruturas primárias

### U = 4

- Montantes de treliças
- Contraventamentos
- Colunas de sustentação de equipamentos
- Vigas de sustentação de equipamentos
- Bases de sustentação de equipamentos
- Vigas de piso principais
- Teto de tanques/silos
- Cavalete estrutural de sustentação

### U = 5

- Colunas de sustentação de edifícios
- Vigas principais de elevações
- Tesouras de coberturas
- Pórticos e vigas de ponte rolante e talhas de ligação
- Tirantes de estruturas em balanço
- Banzos e diagonais de treliças
- Costado de tanques, silos e chaminés
- Mão francesa e talas de ligação

> Para transportadores de correia do Pátio e Porto existe matriz específica de Urgência no procedimento. Quando aplicável, o sistema deve utilizar esse catálogo específico.

Enquanto essa matriz específica não estiver completa no catálogo, a opção
Transportadores/Outros exige descrição técnica e nota manual de 1 a 5. O sistema
salva os dois valores no snapshot.

---

## 5.3 Tendência — T

Primeiro o inspetor seleciona o **Dano**.

Opções:

- CORTE OU FURO
- DEFORMAÇÃO
- DESCONTINUIDADE
- LIG. PARAFUSADA
- PERDA DE ESPESSURA

Depois o sistema mostra somente as condições válidas para aquele dano.

### LIG. PARAFUSADA

| T | Descrição |
|---:|---|
| 1 | Ausência ou falta de aperto de até 10% dos elementos de fixação |
| 2 | Abertura ou alargamento de furação não projetada e sem acabamento; substituição por solda |
| 3 | Ausência ou falta de aperto de 10% a 30% dos elementos de ligação ou uso de parafusos não especificados em projeto |
| 4 | Ausência ou falta de aperto de 30% a 50% dos elementos de fixação |
| 5 | Ausência ou falta de aperto que gera perda de função estrutural acima de 50% dos elementos de ligação |

### DESCONTINUIDADE

| T | Descrição |
|---:|---|
| 4 | Trinca visível em inspeção visual ou deposição insuficiente de solda |
| 5 | Rompimento total do perfil ou trinca ao longo do perfil/ligação |

Notas 1, 2 e 3 não possuem condição definida nessa matriz.

### DEFORMAÇÃO

| T | Descrição |
|---:|---|
| 1 | Amassamento pontual da seção do perfil sem causar excentricidade de carga |
| 2 | Amassamento global da seção do perfil sem causar excentricidade de carga |
| 3 | Leve: menor que 1/500 do vão ou 1% da dimensão do elemento |
| 4 | Moderada: entre 1/500 e 1/200 do vão ou entre 1% e 5% da dimensão do elemento |
| 5 | Severa: maior que 1/200 do vão ou maior que 5% da dimensão do elemento |

### PERDA DE ESPESSURA

| T | Descrição |
|---:|---|
| 2 | Perda de espessura localizada de 10% a 20% |
| 3 | Perda de espessura generalizada de 10% a 20% |
| 4 | Perda de espessura localizada acima de 20% |
| 5 | Perda de espessura generalizada acima de 20% |

Notas:

```text
Localizada:
perfil metálico → até 30% da seção
chapa → até 30% da área

Generalizada:
perfil metálico → acima de 30% da seção
chapa → acima de 30% da área
```

### CORTE OU FURO

| T | Descrição |
|---:|---|
| 1 | Corte ou furo não previsto abaixo de 10% da área da seção |
| 2 | Ausência de componentes secundários |
| 3 | Corte ou furo não previsto entre 10% e 20% da área da seção |
| 4 | Corte ou furo não previsto entre 20% e 50% da área da seção |
| 5 | Corte ou furo não previsto acima de 50% da área da seção ou ausência de componente principal |

---

# 6. Campos GUT — CIVIL

## 6.1 Gravidade — G

O CIVIL segue a mesma lógica de dois critérios:

1. Impacto de Segurança
2. Impacto do Ativo

O sistema usa:

```text
G = maior nota entre Impacto de Segurança e Impacto do Ativo
```

Os dois critérios escolhidos devem ser armazenados separadamente.

### Impacto de Segurança

| Nota | Descrição |
|---:|---|
| 1 | Sem possibilidade de acidente |
| 2 | Dano em elemento estrutural secundário, com possibilidade de acidente até 2 m |
| 3 | Dano em elemento estrutural secundário, com possibilidade de acidente acima de 2 m |
| 4 | Dano em elemento estrutural primário, com possibilidade de acidente até 2 m |
| 5 | Dano em elemento estrutural primário, com possibilidade de acidente acima de 2 m |

### Impacto do Ativo

| Nota | Descrição |
|---:|---|
| 1 | Dano ou ausência de elemento estrutural secundário que não impacta o ativo |
| 2 | Dano pontual em elemento estrutural primário de ativos de baixa criticidade (C e D) |
| 3 | Dano pontual em elemento estrutural primário de ativos de alta criticidade (A e B) |
| 4 | Dano generalizado ou ausência de elemento estrutural primário de ativos de baixa criticidade (C e D) |
| 5 | Dano generalizado ou ausência de elemento estrutural primário de ativos de alta criticidade (A e B) |

---

## 6.2 Urgência — U

O inspetor seleciona a função/criticidade do elemento.

### U = 1 — Estruturas de vedação

- Paredes de alvenaria sem finalidade estrutural
- Ligações chumbadas sem relevância à segurança, como fixação de guarda-corpos

### U = 2 — Estruturas auxiliares

- Guarda-corpos de concreto
- Estruturas de sustentação de telhados/coberturas
- Ligações chumbadas cuja falha possibilite queda de objetos, como fixação de monovias

### U = 3 — Estruturas secundárias

- Estruturas de escadas
- Ligações chumbadas de componentes essenciais
- Vigas baldrame
- Blocos de coroamento
- Estruturas auxiliares de estruturas primárias, como mísulas
- Lajes sem acesso de pessoas
- Estruturas de reforço do solo, como terra armada
- Estrutura de fixação de componentes
- Trilhos, quando aplicável ao caminho de rolamento

### U = 4 — Estruturas estabilizantes / elementos de fixação

- Vigas secundárias de elevações
- Colunas de sustentação de equipamentos
- Vigas de sustentação de equipamentos
- Bases de sustentação de equipamentos
- Lajes com acesso de pessoas
- Fundações em geral
- Estruturas de contenção do solo, como muros de arrimo e cortinas atirantadas
- Costado de tanques, silos e chaminés
- Emendas do trilho, quando aplicável ao caminho de rolamento

### U = 5 — Estruturas principais

- Colunas de sustentação de edifícios
- Vigas principais de elevações
- Pórticos e vigas de ponte rolante
- Tirantes e mão-francesa de estruturas em balanço

> A lista acima deve ser definida no catálogo nativo conforme a matriz oficial. Itens adicionais devem manter a nota definida na Tabela 11 do procedimento.

---

## 6.3 Tendência — T

O inspetor escolhe primeiro o tipo de degradação e depois a condição correspondente.

Tipos previstos na matriz:

- Fissuração
- Segregação e Desagregação
- Corrosão em armaduras
- Efeitos químicos
- Deformação permanente e deslocamentos
- Execução em desconformidade com projeto
- Infiltração
- Chumbadores
- Inclinação longitudinal dos trilhos
- Curvatura vertical dos trilhos
- Curvatura lateral dos trilhos
- Desnível entre trilhos
- Desgaste dos trilhos
- Fixação dos trilhos
- Dormentes
- Emenda dos trilhos

Cada condição deve ser apresentada já vinculada à respectiva nota `T = 1...5`.

O catálogo completo deve reproduzir a Tabela 12 do procedimento, sem permitir combinações não existentes.

Quando a matriz não definir uma condição aplicável, o inspetor deve informar uma
descrição técnica e uma nota manual de 1 a 5; ambas ficam no snapshot.

---

# 7. Campos GUT — TAC

## 7.1 Gravidade — G

A Gravidade vem do Código ABC do equipamento, exposto no cadastro como seleção
`A`, `B`, `C` ou `D`.

| G | Classe do ativo |
|---:|---|
| 1 | C e D |
| 2 | B |
| 3 | A |

O inspetor não precisa selecionar novamente esta informação na avaliação. Código
ausente ou fora desse domínio bloqueia o GUT TAC e orienta a correção do
equipamento.

---

## 7.2 Urgência — U

A Urgência vem da classificação de atmosfera/corrosividade da inspeção, exposta
como seleção `C2`, `C3`, `C4`, `C5` ou `CX`.

| U | Atmosfera |
|---:|---|
| 1 | C2 |
| 2 | C3 |
| 3 | C4 |
| 4 | C5 |
| 5 | CX |

O inspetor não precisa selecionar novamente esta informação na avaliação. Valor
ausente ou inválido bloqueia o GUT TAC e orienta a correção da inspeção.

---

## 7.3 Tendência — T

O inspetor seleciona o grau de oxidação conforme ASTM D610.

| T | Grau ASTM D610 |
|---:|---|
| 1 | Acima de 7 |
| 2 | 6 ou 7 |
| 3 | 4 ou 5 |
| 4 | 2 ou 3 |
| 5 | 1 ou 0 |

O sistema determina `T` automaticamente a partir da opção selecionada.

---

# 8. Estrutura dos quantitativos

Cada avaliação possui um único total final e uma única unidade determinada pela
categoria, mas pode conter um ou mais itens. A avaliação é o agrupador e
`DefectAssessmentQuantity` representa cada item. Não existe outro total vivo na
avaliação: o servidor soma os totais brutos dos itens sem arredondamento intermediário.

- CIVIL: vários itens somados em `m³`;
- TAC: vários lançamentos de área somados em `m²`;
- REC: diferentes elementos somados em `kg`.

Cada item possui posição automática, descrição opcional, entradas, quantidade, valor
unitário, total, modo, unidade, versão e snapshot da fórmula. Novos itens são anexados
ao final. Ao excluir um item, os restantes são renumerados sem alterar sua ordem
relativa. Não há reordenação manual.

Ao publicar, o snapshot do quantitativo usa um envelope agregado com versão própria,
categoria, unidade, quantidade de itens, total bruto e a lista integral dos itens.
Snapshots históricos no formato singular continuam válidos e são interpretados como
um agregado de um item, sem recálculo.

Relatórios e resumos exibem somente o total final. A tela da avaliação e o histórico
exibem os itens para auditoria.

---

# 9. Quantitativo CIVIL

Unidade principal:

```text
m³
```

Campos:

- Comprimento (m)
- Altura (m)
- Largura (m)
- Quantidade

Fórmulas:

```text
M³ unitário = Comprimento × Altura × Largura

M³ total = M³ unitário × Quantidade
```

Exemplo:

```text
Comprimento = 2,00 m
Altura = 0,50 m
Largura = 0,30 m
Quantidade = 2

M³ unitário = 0,30 m³
M³ total = 0,60 m³
```

`M³ unitário` e `M³ total` são somente leitura.

A quantidade deve ser positiva e pode ser fracionária.

Exemplo composto:

```text
Item 1 = 2,00 × 0,50 × 0,30 × 2 = 0,60 m³
Item 2 = 1,00 × 0,40 × 0,20 × 3 = 0,24 m³
Total da avaliação = 0,84 m³
```

---

# 10. Quantitativo TAC

Unidade:

```text
m²
```

Não foi identificada fórmula automática de área nas fontes analisadas.

Regra definida:

- o inspetor informa manualmente o quantitativo em `m²`;
- o sistema armazena o valor;
- não existe cálculo automático de área neste momento.

Campo:

```text
Área (m²)
```

---

# 11. Quantitativo REC

Unidade principal:

```text
kg
```

O **Elemento REC é obrigatório em cada item**. Uma avaliação pode combinar elementos
diferentes; o total final é a soma dos pesos de todos eles.

O elemento controla:

- quais campos dimensionais aparecem;
- qual fórmula é utilizada;
- se o peso é calculado ou informado manualmente.

Densidade utilizada nas fórmulas de aço:

```text
7.850 kg/m³
```

Regra geral para elementos calculáveis:

```text
Peso total = Peso unitário × Quantidade
```

A fórmula de PERFIL W usa literalmente `(A − 2 × EA) × EA`, conforme a decisão
funcional desta versão do documento.

A quantidade deve ser positiva e pode ser fracionária. O sistema rejeita
geometrias impossíveis, inclusive diâmetro interno tubular menor ou igual a zero.

---

## 11.1 PERFIL W

Campos:

- Mesa `M` (mm)
- Espessura da mesa `EM` (mm)
- Alma `A` (mm)
- Espessura da alma `EA` (mm)
- Comprimento `C` (m)
- Quantidade

```text
Área =
(M × EM × 2)
+ ((A - 2 × EA) × EA)

Peso unitário =
7.850 × (Área / 1.000.000) × C

Peso total =
Peso unitário × Quantidade
```

---

## 11.2 PERFIL L

Campos:

- Largura `L` (mm)
- Espessura `E` (mm)
- Comprimento `C` (m)
- Quantidade

```text
Área =
(L × E)
+ ((L - E) × E)

Peso unitário =
7.850 × (Área / 1.000.000) × C

Peso total =
Peso unitário × Quantidade
```

Exemplo validado:

```text
L = 76 mm
E = 6 mm
C = 2,8 m
Quantidade = 2

Peso unitário = 19,25448 kg
Peso total = 38,50896 kg

Apresentação = 38,51 kg
```

---

## 11.3 PERFIL U

Campos:

- Altura `A` (mm)
- Espessura da alma `EA` (mm)
- Largura `L` (mm)
- Espessura da mesa `EM` (mm)
- Comprimento `C` (m)
- Quantidade

```text
Área =
(A × EA)
+ ((L - EA) × EM × 2)

Peso unitário =
7.850 × (Área / 1.000.000) × C

Peso total =
Peso unitário × Quantidade
```

---

## 11.4 PERFIL UE

Campos:

- Altura `A` (mm)
- Espessura da alma `EA` (mm)
- Mesa `M` (mm)
- Dobra da mesa `D` (mm)
- Espessura da mesa `EM` (mm)
- Comprimento `C` (m)
- Quantidade

```text
Área =
(A × EA)
+ 2 × ((M - EA) × EM)
+ 2 × ((D - EM) × EM)

Peso unitário =
7.850 × (Área / 1.000.000) × C

Peso total =
Peso unitário × Quantidade
```

---

## 11.5 CHAPA LISA

Campos:

- Largura `L` (mm)
- Comprimento `C` (mm)
- Espessura `E` (mm)
- Quantidade

```text
Peso unitário =
7.850 × L × C × E / 1.000.000.000

Peso total =
Peso unitário × Quantidade
```

---

## 11.6 BARRA CHATA

Campos:

- Largura `L` (mm)
- Comprimento `C` (mm)
- Espessura `E` (mm)
- Quantidade

```text
Peso unitário =
7.850 × L × C × E / 1.000.000.000

Peso total =
Peso unitário × Quantidade
```

---

## 11.7 CHAPA XADREZ

Campos:

- Largura `L` (mm)
- Comprimento `C` (mm)
- Espessura `E` (mm)
- Quantidade

```text
Peso unitário =
7.850 × L × C × E / 1.000.000.000

Peso total =
Peso unitário × Quantidade
```

---

## 11.8 GUARDA-CORPO

Campos:

- Comprimento `C` (m)
- Quantidade

```text
Peso unitário = C × 30

Peso total = Peso unitário × Quantidade
```

Constante:

```text
30 kg/m
```

---

## 11.9 ESCADA MARINHEIRO

Campos:

- Comprimento `C` (m)
- Quantidade

```text
Peso unitário = C × 60

Peso total = Peso unitário × Quantidade
```

Constante:

```text
60 kg/m
```

---

## 11.10 PERFIL TUBULAR

Campos:

- Diâmetro externo `D` (mm)
- Espessura `E` (mm)
- Comprimento `C` (mm)
- Quantidade

```text
Di = D - 2 × E

Área =
π × (D² - Di²) / 4

Peso unitário =
(Área / 1.000.000)
× (C / 1000)
× 7.850

Peso total =
Peso unitário × Quantidade
```

---

## 11.11 PERFIL L DESIGUAIS

Campos:

- Aba 1 `A1` (mm)
- Aba 2 `A2` (mm)
- Espessura `E` (mm)
- Comprimento `C` (m)
- Quantidade

```text
Área =
(A1 × E)
+ ((A2 - E) × E)

Peso unitário =
7.850 × (Área / 1.000.000) × C

Peso total =
Peso unitário × Quantidade
```

---

## 11.12 METALON

Campos:

- Aba 1 `A1` (mm)
- Aba 2 `A2` (mm)
- Espessura `E` (mm)
- Comprimento `C` (m)
- Quantidade

```text
Área =
((A1 - 2 × E) × E × 2)
+ (A2 × E × 2)

Peso unitário =
7.850 × Área × C / 1.000.000

Peso total =
Peso unitário × Quantidade
```

---

## 11.13 PERFIL T

Campos:

- Mesa `M` (mm)
- Alma `A` (mm)
- Espessura `E` (mm)
- Comprimento `C` (m)
- Quantidade

```text
Área =
(M × E)
+ ((A - E) × E)

Peso unitário =
7.850 × (Área / 1.000.000) × C

Peso total =
Peso unitário × Quantidade
```

---

## 11.14 Elementos REC com peso manual

Os seguintes elementos não possuem fórmula de peso na planilha analisada:

- LIGAÇÃO PARAF.
- TELHAS
- GRADE DE PISO

Regra definida:

- o elemento continua sendo obrigatório;
- o inspetor informa manualmente o peso em `kg`;
- o sistema não tenta calcular o peso;
- o valor informado deve ficar identificado como `manual`.

Campo:

```text
Peso total (kg)
```

---

# 12. Comportamento dos campos no frontend

## Campos escolhidos pelo inspetor

### REC

- Status
- Impacto na Segurança
- Impacto no Ativo
- Função Estrutural
- Dano
- Condição do Dano
- Elemento REC
- Dimensões do elemento, quando aplicável
- Quantidade
- Peso manual, quando aplicável

### CIVIL

- Status
- Impacto de Segurança
- Impacto do Ativo
- Função/Criticidade do Elemento
- Tipo de Degradação
- Condição da Degradação
- Comprimento
- Altura
- Largura
- Quantidade

### TAC

- Status
- Grau de Oxidação ASTM D610
- Área (m²)

`G` e `U` do TAC são derivados de dados já existentes no sistema.

---

## Campos automáticos / somente leitura

### REC

- G
- U
- T
- GUT
- Classificação IE
- Peso unitário
- Peso total, quando existir fórmula

### CIVIL

- G
- U
- T
- GUT
- Classificação CV
- M³ unitário
- M³ total

### TAC

- G
- U
- T
- GUT
- Classificação TA

---

# 13. Persistência das escolhas técnicas

Não salvar apenas:

```text
U = 4
T = 5
```

Também devem ser preservados os critérios escolhidos.

Exemplo:

```text
urgency_score: 4
urgency_option: "Vigas de piso principais"

trend_score: 5
trend_damage: "PERDA DE ESPESSURA"
trend_option: "Perda de espessura generalizada acima de 20%"
```

Para Gravidade REC/CIVIL:

```text
safety_impact_score
safety_impact_option

asset_impact_score
asset_impact_option

gravity_score
```

Isso permite reproduzir historicamente por que a nota foi atribuída.

---

# 14. Snapshots e versionamento

A avaliação deve preservar:

- categoria;
- opções técnicas escolhidas;
- notas G, U e T;
- produto GUT;
- classificação;
- recomendação;
- quantitativo;
- dados de entrada do quantitativo;
- resultado calculado;
- unidade;
- versão do catálogo GUT;
- versão da fórmula de quantitativo.

Relatórios históricos devem utilizar os dados salvos no snapshot.

Não recalcular automaticamente avaliações antigas quando regras futuras forem alteradas.

---

# 15. Regras de arredondamento

Cálculos devem utilizar a precisão interna necessária.

Arredondamento deve ocorrer somente para apresentação, sempre com duas casas
decimais nos resultados de quantitativo.

Exemplo:

```text
38,50896 kg
```

pode ser exibido como:

```text
38,51 kg
```

O valor bruto calculado deve permanecer disponível para persistência e auditoria.

---

# 16. Resumo

| Categoria | G | U | T | Classificação | Quantitativo | Entrada |
|---|---|---|---|---|---|---|
| CIVIL | 1–5 | 1–5 | 1–5 | CV-1 a CV-5 | m³ | Calculado |
| TAC | 1–3 | 1–5 | 1–5 | TA-1 a TA-5 | m² | Manual |
| REC | 1–5 | 1–5 | 1–5 | IE-1 a IE-5 | kg | Calculado ou manual conforme elemento |

---

# 17. Validação mínima

## GUT

Testar:

- limites inclusivos das classificações;
- REC e CIVIL com notas de 1 a 5;
- TAC com G de 1 a 3;
- TAC com U e T de 1 a 5;
- `GUT = G × U × T`;
- classificação automática;
- `G = maior impacto` em REC;
- `G = maior impacto` em CIVIL;
- impossibilidade de selecionar combinações de Tendência inexistentes;
- preservação das opções técnicas nos snapshots.

## Quantitativos

Testar:

- CIVIL: m³ unitário e total;
- CIVIL composto: `0,60 + 0,24 = 0,84 m³`;
- cada fórmula REC;
- elementos REC diferentes na mesma avaliação e soma final em `kg`;
- unidades de entrada;
- densidade `7.850 kg/m³`;
- aplicação da quantidade após cálculo unitário;
- peso manual de Ligação Parafusada, Telhas e Grade de Piso;
- TAC com m² manual;
- múltiplos lançamentos TAC e soma final em `m²`;
- descrição opcional, posições automáticas e renumeração após exclusão;
- unicidade da posição por organização e avaliação;
- arredondamento somente na apresentação;
- versionamento das fórmulas;
- snapshot agregado e compatibilidade com snapshots singulares;
- preservação histórica dos resultados e relatório sem multiplicação duplicada.
