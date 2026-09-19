# 10 — Resumo da Classificação do Equipamento – GUT

> Atualizado em 18/09/2026 após revisão específica das páginas 24–26 do `T000000-S-2PO006_R-04` e confronto com o relatório real `U03-06VT002`.

## 1. Objetivo

Implementar a seção **“RESUMO DA CLASSIFICAÇÃO DO EQUIPAMENTO – GUT”** da prévia e do relatório de inspeção, reproduzindo a lógica do padrão técnico atual sem duplicar cálculos já existentes nas avaliações.

A seção deverá consolidar, para a inspeção atual:

- dados resumidos do equipamento;
- quantidade de avarias por classificação;
- prazo/data recomendada de tratamento;
- quantitativo agregado por classificação;
- Nota M2 (SAP) vinculada ao grupo de avarias;
- classificação mais crítica de cada categoria;
- quantitativo total por categoria.

Escopo inicial deste documento:

- TAC;
- REC;
- CIVIL / CV.

TELHADOS/TAPAMENTOS, ENDs, CI e demais blocos do modelo de relatório permanecem fora deste escopo.

---

## 2. Fontes funcionais

Fontes principais utilizadas nesta definição:

- `T000000-S-2PO006_R-04` — Procedimento de Inspeção de Estruturas e Priorização de Avarias;
- `PADRÃO DE RELATÓRIO TAC - REC - CIVIL 2026-1 1.docx`;
- relatório real `U0306VT-G-6RI002_R-04.pdf`;
- `09 — Classificação GUT e Quantitativos`;
- documentação atual de equipamentos, inspeções, avarias, snapshots e relatório.

### Regra de precedência

O resumo **não deve recalcular GUT a partir das regras atuais do catálogo**.

Ele deve consumir os dados já persistidos na avaliação/snapshot da inspeção:

```text
categoria
G
U
T
produto GUT
classificação
prioridade da classificação
recomendação
quantitativo
unidade
versão do catálogo
versão da fórmula
```

Isso preserva relatórios históricos mesmo que regras futuras sejam alteradas.

---

## 3. Princípio de arquitetura

O resumo é um **read model derivado da inspeção**, e não uma nova fonte de verdade.

Não criar tabela para armazenar:

- quantidade de avarias;
- quantitativo total;
- classificação mais crítica;
- data de tratamento calculada.

Esses dados devem ser calculados no momento de montar a prévia do relatório.

Persistir apenas o que é manual e não existe atualmente em outra entidade, principalmente a **Nota M2 (SAP)** do grupo de classificação.

Serviço sugerido:

```text
BuildInspectionGutSummary
```

Responsabilidade:

```text
Inspection
+ snapshots do equipamento
+ avaliações completas da inspeção
+ snapshots GUT/quantitativos
+ notas M2 cadastradas
= InspectionGutSummary DTO
```

A página Vue deve apenas renderizar o contrato recebido do backend.

---

# 4. Tabela 1 — Resumo do equipamento

O modelo do relatório contém os campos:

```text
Área
Subárea
Local de instalação
Cód. ABC
Data da Insp.
Criticidade
Equipamento
TAG
Desenho Geral
Ordem
Proc. Inspeção
```

## 4.1 Origem dos campos

| Campo do relatório | Origem proposta |
|---|---|
| Área | snapshot do equipamento → `area_name` |
| Subárea | snapshot do equipamento → `subarea_name` |
| Local de instalação | snapshot do equipamento → `installation_location` |
| Cód. ABC | snapshot do equipamento → `abc_code` |
| Data da Insp. | `inspection.inspected_on` |
| Criticidade | **regra pendente de confirmação — ver seção 14** |
| Equipamento | snapshot do equipamento → `name` |
| TAG | snapshot do equipamento → `tag` |
| Desenho Geral | documento de referência da inspeção identificado como desenho geral; sem documento → `—` |
| Ordem | ordem de serviço da inspeção |
| Proc. Inspeção | código/revisão da fonte técnica do catálogo GUT, atualmente `T000000-S-2PO006_R-04` |

### Histórico

Dados cadastrais da Tabela 1 devem preferir o **snapshot da inspeção**, e não o cadastro atual do equipamento.

Alterar TAG, área, nome ou outro cadastro no futuro não pode reescrever um relatório histórico.

---

# 5. Avaliações que entram no resumo

Uma avaliação entra na consolidação somente quando:

```text
assessment.inspection_id = inspeção atual
assessment.status = complete
classification_code != null
quantitativo principal válido
```

Na modelagem atual:

```text
new
unchanged
worsened
improved
```

possuem GUT/classificação quando publicadas.

As condições:

```text
repaired
not_located
not_inspected
```

não possuem GUT/classificação atual e, portanto, **não entram em nenhuma linha de classificação do resumo**.

A consulta não deve usar a última avaliação global da avaria; deve usar exclusivamente a avaliação pertencente à inspeção do relatório.

---

# 6. Ordem das categorias e classificações

A ordem da página deve ser estável e compatível com o relatório:

```text
1. TAC
2. REC
3. CIVIL
```

Dentro da categoria, ordenar da maior para a menor prioridade.

### TAC

```text
TA-1
TA-2
TA-3
TA-4
TA-5
```

### REC

```text
IE-0  // reservado para RGI; ver seção 12
IE-1
IE-2
IE-3
IE-4
IE-5
```

### CIVIL

```text
CV-0  // reservado para RGI; ver seção 12
CV-1
CV-2
CV-3
CV-4
CV-5
```

Linhas sem ocorrência continuam visíveis no relatório e exibem `—` nos campos de detalhe.

---

# 7. Regra de agregação por linha

A chave de agrupamento é:

```text
inspection_id
+ category
+ classification_code
```

Exemplo:

```text
Inspeção 2026
TAC
TA-2
```

Todas as avaliações TAC classificadas como TA-2 na inspeção atual pertencem ao mesmo grupo do resumo.

Para cada grupo calcular:

```text
Qtde avarias = COUNT(avaliações elegíveis)
Quantitativo = SUM(quantitativo bruto persistido)
```

Não contar:

- quantidade de fotos;
- quantidade de elementos físicos informada dentro de um quantitativo;
- avaliações de inspeções anteriores;
- avarias reparadas sem classificação atual;
- avaliações em rascunho.

---

# 8. Quantitativo por categoria

Unidades definidas no sistema:

| Categoria | Unidade do resumo |
|---|---|
| TAC | m² |
| REC | kg |
| CIVIL | m³ |

O agregado deve utilizar o valor bruto persistido no snapshot do quantitativo.

Exemplo REC:

```text
IE-4
Avaria 1 = 19,25448 kg
Avaria 2 = 19,25448 kg

Resumo IE-4 = 38,50896 kg
Exibição = 38,51 kg
```

### Arredondamento

Somar primeiro os valores brutos.

Arredondar apenas para apresentação.

Não somar valores previamente arredondados.

---

# 9. Total da categoria

Ao final de cada categoria:

```text
Total TAC
Total REC
Total CIVIL
```

Regra:

```text
category_total = soma dos quantitativos de todas as avaliações elegíveis da categoria
```

Equivalente à soma das linhas classificadas.

Exemplo real do relatório U03-06VT002:

```text
TAC
TA-2 = 98,50 m²
TA-3 = 125,20 m²
Total TAC = 223,70 m²
```

A mesma lógica se aplica a REC e CIVIL.

---

# 10. Classificação do dano mais crítico

Cada categoria exibe:

```text
Classificação do dano mais crítico: X
```

Regra:

> Selecionar a classificação de **maior prioridade** entre as classificações presentes na inspeção.

Não comparar pelo texto do código.

Usar a prioridade persistida no snapshot da classificação.

Exemplos:

```text
TA-2 + TA-3 → TA-2
IE-4 + IE-5 → IE-4
CV-2 + CV-3 + CV-4 + CV-5 → CV-2
```

Se nenhuma avaliação da categoria possuir classificação:

```text
—
```

---

# 11. Coluna Data — regra ainda não fechada

O procedimento `T000000-S-2PO006_R-04` define os **prazos recomendados por classificação**, mas **não define qual evento inicia a contagem do prazo nem a regra da coluna `Data` do resumo**.

O relatório real `U03-06VT002` apresenta um comportamento coerente com:

```text
Data da inspeção: 11/05/2026
TA-2: tratar em até 3 anos → 11/05/2029
TA-3: tratar em até 5 anos → 11/05/2031
CV-2: tratar em até 2 anos → 11/05/2028
```

Isso é **evidência de prática**, não uma regra normativa suficiente para implementar automaticamente.

Antes de fechar a lógica, precisam ser confirmados:

1. qual data é a base: início da inspeção, conclusão de campo, data do relatório, abertura da M2 ou outra;
2. se uma reinspeção reinicia ou preserva o prazo original de uma avaria ainda não tratada;
3. se uma reclassificação (ex.: IE-3 → IE-2) gera novo prazo ou preserva a referência histórica;
4. se a data registrada no SAP/M2 pode prevalecer sobre a data calculada.

## 11.1 Prazos normativos conhecidos

### TAC

```text
TA-1 → tratar em até 1 ano
TA-2 → tratar em até 3 anos
TA-3 → tratar em até 5 anos
TA-4 → intervenção por oportunidade
TA-5 → registro de condição
```

### REC

```text
IE-1 → tratar em até 1 ano
IE-2 → tratar em até 2 anos
IE-3 → tratar em até 3 anos
IE-4 → intervenção por oportunidade
IE-5 → registro de condição
```

### CIVIL

```text
CV-1 → tratar em até 1 ano
CV-2 → tratar em até 2 anos
CV-3 → tratar em até 3 anos
CV-4 → intervenção por oportunidade
CV-5 → registro de condição
```

O prazo deve continuar estruturado no catálogo/snapshot; não extrair anos do texto da recomendação.

**Não implementar ainda o cálculo automático da coluna `Data` até a regra funcional ser confirmada.**

---

# 12. IE-0 / CV-0 — Risco Grave e Iminente

O procedimento define de forma explícita o fluxo de **Risco Grave e Iminente (RGI)**:

1. o **inspetor identifica** a possível condição RGI;
2. a ocorrência deve ser **comunicada imediatamente** pelos meios de comunicação da Samarco;
3. a classificação definitiva depende de **avaliação e validação do comitê técnico de engenharia**;
4. quando validada como RGI, deve ser aberta Nota M2 com classificação:

```text
IE-0
CV-0
TE-0
```

5. a tratativa deve começar imediatamente com ações de mitigação de risco conforme o procedimento `T000000-S-2PO002`;
6. **TAC não possui classificação RGI**.

Portanto:

- `IE-0` e `CV-0` não são resultados possíveis da multiplicação GUT comum;
- só podem existir após validação técnica do RGI;
- o resumo deve ser capaz de exibir essas linhas quando o domínio de RGI estiver implementado;
- não criar automaticamente classificação zero com base em G, U ou T.

A implementação completa do fluxo de aprovação/registro de RGI continua sendo uma funcionalidade própria, mas sua regra técnica já está definida pelo procedimento.

---

# 13. Nota M2 (SAP)

## 13.1 O que o procedimento define

A Nota M2 é uma **nota de avaria no SAP usada para controlar os prazos de execução**.

O procedimento estabelece que as notas sejam abertas por **grupos de avarias do mesmo ativo e com a mesma classificação**, de modo que danos com o mesmo prazo de execução fiquem agrupados.

Grupos previstos:

```text
REC   → IE-1, IE-2, IE-3, IE-4
TAC   → TA-1, TA-2, TA-3, TA-4
CIVIL → CV-1, CV-2, CV-3, CV-4
TEL   → TE-1, TE-2, TE-3, TE-4
```

Exemplo:

```text
Ativo U03-06VT002
Avaria A → IE-2
Avaria B → IE-2
Avaria C → IE-3

Grupo IE-2 → uma Nota M2 para A + B
Grupo IE-3 → outra Nota M2 para C
```

O procedimento também informa que, quando houver danos, as notas devem ser cadastradas nos **centros de trabalho dos responsáveis pela manutenção da área**, escolhidos pelo programador conforme a disciplina Mecânica, Elétrica ou Civil.

## 13.2 Classes 5

Para:

```text
IE-5
CV-5
TA-5
```

o procedimento determina que **não é necessária a abertura de Nota M2**, sendo realizado apenas o registro da condição no relatório.

Entretanto, existe uma divergência prática importante: no relatório real `U03-06VT002`, as linhas `IE-5` e `CV-5` apresentam a mesma Nota M2 `12063059`.

Portanto, a regra do sistema deve ser:

- classe 5 **nunca exige** abertura de M2;
- não tornar a M2 obrigatória para classe 5;
- manter pendente a decisão sobre permitir **vínculo opcional de uma M2 existente** em classe 5 para reproduzir casos legados/operacionais.

## 13.3 RGI

Quando um RGI for validado pelo comitê técnico, a própria regra técnica exige Nota M2 com:

```text
IE-0
CV-0
TE-0
```

TAC não possui classe zero/RGI.

## 13.4 Modelagem — não vincular a Nota M2 diretamente a uma única avaria

A Nota M2 pode representar várias avarias. Portanto, não usar `sap_m2_number` como atributo principal de uma única avaliação.

Também não é seguro definir a Nota M2 como pertencente exclusivamente a uma inspeção, porque o procedimento fala em **mesmo ativo + mesma classificação** e não esclarece o ciclo de vida da nota entre reinspeções.

Modelagem recomendada, capaz de preservar histórico e suportar reutilização:

```text
SapM2Note
- id
- public_id
- organization_id
- equipment_id
- sap_number
- created_by
- updated_by
- created_at
- updated_at

InspectionGutM2Link
- id
- organization_id
- inspection_id
- category
- classification_code
- sap_m2_note_id
- created_by
- created_at
```

Com isso:

- uma M2 pode aparecer em mais de uma inspeção quando a regra operacional permitir;
- uma inspeção preserva qual número M2 foi mostrado em cada grupo;
- o mesmo número pode ser referenciado por mais de um grupo em casos legados, sem duplicar a identidade da nota;
- alterações futuras não reescrevem relatórios já liberados.

A regra de unicidade entre `equipamento + classificação + M2` **não deve ser endurecida no banco antes da confirmação funcional**, porque o relatório real já mostra reutilização da mesma Nota M2 em categorias diferentes.

## 13.5 Questões ainda abertas sobre M2

O procedimento **não responde**:

1. se a mesma M2 deve ser mantida nas reinspeções;
2. se uma nova avaria da mesma classificação entra na M2 existente ou gera nova M2;
3. se podem existir duas M2 simultâneas para a mesma classificação e mesmo ativo;
4. o que ocorre com a M2 quando a avaria é reclassificada;
5. quem, dentro do fluxo do sistema Vistoria, digita/vincula o número da M2;
6. se a ausência de M2 deve bloquear revisão/liberação/exportação ou apenas gerar alerta.

Essas regras precisam de confirmação antes de criar validações rígidas.

---

# 14. Campo Criticidade da Tabela 1 — pendência funcional

O padrão do relatório possui um campo chamado `Criticidade`.

No relatório real U03-06VT002:

```text
Criticidade = CV-2
```

Na mesma inspeção, o resumo CIVIL informa:

```text
Classificação do dano mais crítico = CV-2
```

A interpretação mais consistente é:

```text
Criticidade = classificação CIVIL mais crítica da inspeção
```

Porém, o procedimento e a documentação atual não definem explicitamente esse campo da Tabela 1.

**Não implementar essa derivação sem confirmação funcional.**

Opções possíveis a confirmar:

```text
A) classificação CIVIL mais crítica da inspeção atual;
B) criticidade histórica do equipamento cadastrada separadamente;
C) outra regra SAP/Samarco ainda não documentada.
```

---

# 15. Divergência da unidade CIVIL no modelo de relatório

Existe uma inconsistência entre as fontes:

- a lógica atual de quantitativo CIVIL e o Anexo E trabalham com **m³**;
- a Tabela 2 do padrão de relatório mostra `Total CIVIL [m²]`.

Para consistência técnica do sistema, este documento adota provisoriamente:

```text
CIVIL = m³
```

A legenda da Tabela 2 deve ser confirmada com o cliente antes de fechar o layout final.

---

# 16. Dependência — TAC TA-4 e TA-5

O padrão técnico e o documento funcional mais recente contemplam:

```text
TA-1 a TA-5
```

A implementação anterior do catálogo nativo possuía lacuna para TAC abaixo de GUT 15, sem TA-4/TA-5.

O resumo deve ser implementado **depois** da atualização do catálogo TAC para TA-4 e TA-5, caso essa atualização ainda não esteja aplicada no código.

Não criar classificação apenas dentro do resumo para compensar uma lacuna do domínio.

---

# 17. Contrato de saída sugerido

Exemplo simplificado:

```json
{
  "equipment": {
    "area": "USINA III",
    "subarea": "FORNO DE ENDURECIMENTO",
    "installation_location": "SM_MNU_U03_06EN_07VT_VT00_VT02",
    "abc_code": "A",
    "inspected_on": "2026-05-11",
    "criticality": "CV-2",
    "name": "VENTILADOR",
    "tag": "U03-06VT002",
    "general_drawing": "U030600-S-551729",
    "work_order": "3500762191",
    "inspection_procedure": "T000000-S-2PO006_R-04"
  },
  "categories": [
    {
      "code": "TAC",
      "unit": "m²",
      "most_critical": "TA-2",
      "total": 223.70,
      "rows": [
        {
          "classification": "TA-2",
          "defect_count": 2,
          "due_date": null,
          "quantity": 98.50,
          "sap_m2_number": "11503853"
        }
      ]
    }
  ]
}
```

O frontend não recebe avaliações completas para refazer os agrupamentos.

O backend entrega o resumo já consolidado.

---

# 18. Serviço sugerido

```text
app/Services/Reports/BuildInspectionGutSummary.php
```

Responsabilidades:

1. validar inspeção e tenant;
2. carregar snapshot do equipamento;
3. carregar avaliações completas da inspeção;
4. ignorar avaliações sem classificação atual;
5. agrupar por categoria + classificação;
6. somar quantitativos brutos;
7. contar avarias;
8. resolver a coluna Data conforme a regra funcional aprovada (ainda pendente);
9. resolver classificação mais crítica pela prioridade salva;
10. carregar Nota M2 do grupo;
11. montar totais por categoria;
12. retornar DTO imutável para o compositor do relatório.

Não é responsabilidade deste serviço:

- calcular G/U/T;
- resolver novamente a faixa GUT;
- recalcular quantitativos;
- modificar avaliações;
- criar Nota M2 no SAP.

---

# 19. Integração com o relatório atual

O `report-preview` já é o ponto de leitura do relatório.

O resumo deve ser incorporado ao read model existente antes da paginação no frontend.

Fluxo:

```text
GET /inspections/{inspection}/report-preview
    ↓
Report Presenter / Composer
    ↓
BuildInspectionGutSummary
    ↓
prop `gut_summary`
    ↓
Vue renderiza página A4
```

Não criar um endpoint público separado apenas para a página do resumo, salvo se houver necessidade real de reutilização em outra tela.

---

# 20. Validações mínimas

Testar obrigatoriamente:

### Agrupamento

- duas avarias TA-2 geram uma linha TA-2 com `defect_count = 2`;
- TA-2 e TA-3 geram linhas separadas;
- inspeções diferentes nunca se misturam;
- categorias diferentes nunca se misturam.

### Quantitativo

- soma usa valor bruto;
- TAC totaliza em m²;
- REC totaliza em kg;
- CIVIL totaliza na unidade aprovada;
- arredondamento somente na apresentação.

### Criticidade por categoria

- TA-2 + TA-3 → TA-2;
- IE-4 + IE-5 → IE-4;
- CV-2 + CV-4 → CV-2;
- categoria vazia → `—`.

### Data

Os testes da coluna `Data` devem ser escritos **somente depois da confirmação da regra funcional**.

No mínimo deverão cobrir:

- data-base escolhida;
- anos-calendário, quando aplicável;
- reinspeção de avaria ainda aberta;
- reclassificação;
- classes por oportunidade/registro de condição (`N/A`, se confirmado);
- 29/02 sem overflow, caso a regra continue baseada em soma de anos.

### Condições sem GUT

- repaired não entra no agrupamento;
- not_located não entra;
- not_inspected não entra;
- draft não entra.

### Histórico

- mudança posterior no catálogo não altera classificação histórica;
- mudança posterior no cadastro do equipamento não altera dados do snapshot do relatório;
- alteração posterior de quantitativo em outra inspeção não afeta a inspeção histórica.

### Nota M2

- o vínculo do resumo é feito por grupo `inspeção + categoria + classificação`;
- a identidade da Nota M2 é separada do vínculo com a inspeção;
- classes 1–4 seguem a regra de agrupamento prevista no procedimento;
- classe 5 não exige abertura de nota;
- mesma nota SAP pode aparecer em grupos distintos sem violar unicidade global;
- validação de obrigatoriedade/bloqueio só deve ser ativada depois da decisão funcional;
- relatórios liberados preservam o número M2 utilizado naquele ciclo.

### Tenant

- nenhum dado ou Nota M2 de outra organização pode entrar no resumo.

---

# 21. Critérios de aceite

A implementação estará concluída quando:

1. a prévia reproduzir a estrutura da Tabela 1 e Tabela 2 do padrão de relatório;
2. os números forem derivados exclusivamente da inspeção atual e de seus snapshots;
3. as quantidades por classificação coincidirem com as avaliações publicadas;
4. os quantitativos por classificação e categoria fecharem matematicamente;
5. a classificação mais crítica seguir a prioridade do snapshot;
6. a coluna `Data` seguir a regra funcional aprovada, preservando histórico entre inspeções;
7. a Nota M2 for modelada como entidade própria, vinculável ao grupo do resumo e não duplicada por avaria;
8. o relatório histórico permanecer reproduzível após mudanças futuras no cadastro ou catálogo;
9. testes de tenant, histórico, agrupamento, data e arredondamento estiverem aprovados.

---

# 22. Decisões que ainda precisam ser fechadas

O procedimento já resolveu parte das dúvidas, principalmente agrupamento de M2, classes 5 e RGI. Permanecem abertas: 

1. **Unidade CIVIL no resumo:** exibir `m²` como o template/relatório real ou `m³` como o quantitativo CIVIL calculado nos anexos;
2. **Avarias que entram no resumo:** confirmar o tratamento de avarias tratadas, canceladas, canceladas S/R e demais estados funcionais no resumo atual;
3. **Coluna Data:** definir qual data inicia a contagem do prazo;
4. **Reinspeção:** confirmar se o prazo original é preservado ou reiniciado quando a avaria permanece com a mesma classificação;
5. **Reclassificação:** confirmar como recalcular/preservar o prazo quando a classificação muda;
6. **M2 em reinspeções:** confirmar se a mesma Nota M2 é reaproveitada ou se é criada outra;
7. **Novas avarias no mesmo grupo:** confirmar se entram na M2 existente;
8. **Múltiplas M2:** confirmar se pode haver mais de uma Nota M2 para o mesmo ativo e mesma classificação;
9. **Classe 5 com M2:** o procedimento dispensa a abertura, mas há relatório real com M2 em IE-5/CV-5; confirmar se o sistema deve permitir vínculo opcional de nota já existente;
10. **Responsável pela M2 no Vistoria:** definir quem informa/vincula o número e em qual etapa;
11. **Validação:** definir se ausência de M2 obrigatória gera bloqueio ou apenas alerta;
12. **Criticidade da Tabela 1:** confirmar se corresponde à classificação CIVIL mais crítica ou a outro dado;
13. **Desenho Geral:** definir qual referência usar quando houver mais de um desenho do equipamento;
14. **Proc. Inspeção:** definir se a revisão do procedimento deve ser snapshot da inspeção para preservar relatórios históricos;
15. **Escopo futuro:** decidir quando incorporar Telhados/Tapamentos e o bloco `END’s, Trabalhos de Engenharia e CI’s`.

## 22.1 Pontos que não precisam mais de confirmação

Já estão definidos pelo `T000000-S-2PO006_R-04`:

- M2 agrupa avarias do **mesmo ativo + mesma classificação/prazo**;
- IE-1 a IE-4, TA-1 a TA-4, CV-1 a CV-4 e TE-1 a TE-4 são grupos sujeitos à abertura de nota;
- IE-5, CV-5 e TA-5 não exigem abertura de M2;
- RGI é identificado pelo inspetor e validado pelo comitê técnico;
- RGI validado usa IE-0, CV-0 ou TE-0;
- TAC não possui RGI;
- a tratativa de um RGI deve começar imediatamente com mitigação de risco.
