# 01 — Visão Geral e Escopo do MVP

## 1. Objetivo do produto

Criar uma aplicação web responsiva para empresas que realizam vistorias técnicas em equipamentos e estruturas.

O sistema deve substituir gradualmente o processo atual baseado em:

- planilhas Excel;
- arquivos com macros;
- organização manual de fotografias;
- preenchimento repetitivo de comentários e recomendações;
- consolidação manual de quantitativos;
- montagem manual do relatório final em PDF.

O primeiro MVP será focado em inspeções da categoria **CIVIL**.

---

## 2. Visão do produto

A aplicação funcionará como um SaaS multiempresa.

Cada empresa usuária do sistema poderá cadastrar seus próprios:

- funcionários;
- clientes;
- unidades;
- áreas;
- subáreas;
- equipamentos;
- inspeções;
- avarias;
- fotos;
- avaliações;
- relatórios.

A hierarquia principal será:

```text
Organização
└── Clientes
    └── Unidades
        └── Áreas
            └── Subáreas
                └── Equipamentos
                    └── Inspeções
                        ├── Responsáveis
                        ├── Avarias
                        ├── Avaliações
                        ├── Fotos
                        ├── Revisões
                        └── Relatórios
```

---

## 3. Definições do domínio

### 3.1 Organização

É a empresa que utiliza e paga pelo sistema.

Exemplo:

```text
Engenharia Alfa
```

Cada organização terá seus próprios dados e não poderá acessar dados de outras organizações.

---

### 3.2 Cliente

É a empresa contratante do serviço de inspeção.

Exemplo:

```text
Samarco Mineração
```

Uma organização poderá atender vários clientes.

---

### 3.3 Unidade

É a unidade operacional ou planta do cliente.

Exemplo:

```text
Unidade de Ubu
```

---

### 3.4 Área

É uma divisão operacional dentro da unidade.

Exemplo:

```text
Usina III
```

---

### 3.5 Subárea

É uma subdivisão da área.

Exemplo:

```text
Forno de Endurecimento
```

---

### 3.6 Equipamento

É o ativo que será inspecionado e que terá histórico permanente.

Exemplo:

```text
Ventilador U03-06VT002
```

O equipamento terá um identificador interno próprio.

O TAG não será usado como chave primária.

O TAG será único dentro da combinação:

```text
Organização + Cliente + Unidade + TAG
```

---

### 3.7 Inspeção

É uma vistoria realizada em um equipamento em uma determinada data.

Um equipamento poderá possuir várias inspeções ao longo dos anos.

Exemplo:

```text
Equipamento U03-06VT002
├── Inspeção 2024
├── Inspeção 2025
└── Inspeção 2026
```

Uma inspeção poderá ser uma inspeção inicial ou uma reinspeção.

---

### 3.8 Avaria

É a identidade permanente de um problema identificado no equipamento.

Exemplo:

```text
VT009-CV-004
```

O código da avaria:

- será único dentro da organização;
- permanecerá o mesmo nas reinspeções;
- não será usado como chave primária;
- não deverá ser digitado livremente pelo inspetor;
- deverá ser gerado pelo sistema conforme regra configurada.

---

### 3.9 Avaliação da avaria

Representa a condição da avaria em uma inspeção específica.

Exemplo:

```text
VT009-CV-004

2025: CV-3
2026: CV-2 — agravou
2027: reparada
```

A avaria é permanente.

A avaliação é histórica.

---

## 4. Perfis e responsabilidades

### 4.1 Superadministrador

É o administrador geral da plataforma.

Pode:

- criar organizações;
- bloquear organizações;
- consultar informações técnicas da plataforma;
- gerenciar limites;
- acessar logs administrativos;
- definir o administrador principal de cada organização.

Não deve participar diretamente das inspeções.

---

### 4.2 Administrador interno da organização

Cada organização terá pelo menos um administrador interno.

Pode:

- cadastrar usuários;
- editar usuários;
- desativar usuários;
- cadastrar clientes;
- cadastrar unidades;
- cadastrar áreas;
- cadastrar subáreas;
- cadastrar equipamentos;
- distribuir inspeções;
- consultar todos os dados da própria organização;
- configurar regras permitidas.

Usuários não devem ser excluídos quando deixarem a empresa.

Devem ser marcados como inativos para preservar o histórico.

---

### 4.3 Membro da organização

É um usuário comum da organização.

As responsabilidades técnicas não serão fixas no cadastro do usuário.

O mesmo usuário poderá, dependendo da inspeção, atuar como:

- inspetor;
- revisor;
- aprovador;
- liberador.

Exemplo:

```text
Inspeção A
- Inspetor: João
- Revisor: Maria
- Aprovador: Carlos
- Liberador: Carlos

Inspeção B
- Inspetor: João
- Revisor: João
- Aprovador: João
- Liberador: João
```

---

## 5. Fluxo principal da inspeção

O fluxo inicial será:

```text
Planejada
↓
Em inspeção
↓
Aguardando revisão
↓
Em correção
↓
Aprovada
↓
Relatório gerado
↓
Liberada
```

### 5.1 Planejada

O coordenador ou administrador informa:

- cliente;
- unidade;
- área;
- subárea;
- equipamento;
- ordem de serviço;
- data prevista;
- responsáveis;
- documentos e desenhos de referência.

---

### 5.2 Em inspeção

O inspetor registra em campo:

- novas avarias;
- situação das avarias anteriores;
- fotografias;
- localização;
- tipo de dano;
- elemento;
- dimensões;
- quantitativos;
- gravidade;
- urgência;
- tendência;
- comentários;
- recomendações.

---

### 5.3 Aguardando revisão

O inspetor informa que terminou o trabalho de campo.

A inspeção deixa de ficar livremente editável.

---

### 5.4 Em correção

O revisor poderá devolver itens específicos.

Exemplo:

```text
Avaria VT009-CV-004

Problema:
- foto desfocada;
- medida não informada;
- comentário incompatível com a recomendação.
```

---

### 5.5 Aprovada

A avaliação técnica foi revisada e aprovada.

Os dados devem ficar bloqueados contra alteração silenciosa.

---

### 5.6 Relatório gerado

O sistema gera o PDF usando somente dados aprovados.

---

### 5.7 Liberada

O responsável final libera oficialmente o relatório.

---

## 6. Reinspeção

Uma reinspeção não deve começar com um formulário vazio.

O sistema deverá carregar as avarias anteriores do equipamento.

Para cada avaria anterior, o inspetor deverá informar uma situação:

- permanece igual;
- agravou;
- melhorou parcialmente;
- reparada;
- não localizada;
- não foi possível inspecionar.

Também será possível registrar novas avarias.

### Regras

- não copiar automaticamente uma avaria como se ainda existisse;
- exigir confirmação do inspetor;
- exigir nova avaliação GUT;
- exigir novas fotografias, salvo justificativa;
- preservar todas as fotos antigas;
- manter o código original da avaria;
- não apagar avarias reparadas;
- permitir criar uma nova avaria quando um problema reaparecer após reparo concluído.

---

## 7. Escopo incluído no MVP

### 7.1 Multiempresa

- cadastro de organizações;
- usuários vinculados a uma única organização;
- administrador interno;
- isolamento de dados;
- superadministrador da plataforma.

### 7.2 Estrutura operacional

- clientes;
- unidades;
- áreas;
- subáreas;
- equipamentos.

### 7.3 Equipamentos

- cadastro;
- TAG;
- nome;
- localização;
- desenhos de referência;
- documentos;
- histórico de inspeções;
- status ativo ou inativo.

### 7.4 Inspeções

- criação;
- atribuição de responsáveis;
- alteração de status;
- histórico de status;
- inspeção inicial;
- reinspeção;
- snapshots de dados cadastrais.

### 7.5 Avarias CIVIL

- criação de avaria;
- código permanente;
- avaliação por inspeção;
- vínculo com avaliações anteriores;
- situação da avaria;
- tipo de dano;
- elemento;
- quantidade;
- dimensão;
- unidade de medida;
- comentário;
- recomendação.

### 7.6 Fotografias

- captura pelo celular;
- upload;
- compressão;
- redimensionamento;
- ordenação;
- associação com a avaliação;
- miniaturas;
- armazenamento privado.

### 7.7 Classificação CIVIL

- gravidade;
- urgência;
- tendência;
- pontuação GUT;
- classificação CV;
- modelos de comentários;
- modelos de recomendações.

### 7.8 Revisão e aprovação

- envio para revisão;
- devolução para correção;
- aprovação;
- liberação;
- registro de responsáveis;
- histórico de alterações.

### 7.9 Relatório

- PDF simplificado;
- dados da organização;
- dados do cliente;
- dados do equipamento;
- resumo da inspeção;
- relação de avarias;
- avaliações;
- fotos;
- comentários;
- recomendações;
- quantitativos;
- responsáveis;
- número da revisão.

---

## 8. Escopo excluído do MVP

Não será implementado inicialmente:

- categoria TAC;
- categoria REC;
- cálculo de peso de perfis metálicos;
- editor visual de desenhos técnicos;
- marcação interativa de regiões;
- inteligência artificial;
- reconhecimento automático de danos;
- geração automática de comentários por IA;
- aplicativo Android;
- aplicativo iOS;
- funcionamento offline;
- cobrança recorrente;
- planos comerciais;
- limites automáticos por plano;
- domínio personalizado por organização;
- assinatura eletrônica avançada;
- importação automática completa de planilhas antigas;
- reprodução exata das 34 páginas do relatório atual.

---

## 9. Regras de fotografia

As imagens não serão armazenadas diretamente no banco.

O banco armazenará metadados e caminho do arquivo.

Processamento inicial sugerido:

- corrigir orientação;
- limitar o maior lado da imagem;
- converter para formato adequado;
- gerar miniatura;
- comprimir;
- registrar tamanho original;
- registrar tamanho final;
- registrar dimensões;
- registrar usuário;
- registrar data;
- registrar avaliação relacionada.

Meta inicial:

```text
300 KB a 800 KB por fotografia
```

Essa meta deverá ser validada com imagens reais.

---

## 10. Regras de segurança

### 10.1 Isolamento

Todos os registros operacionais deverão possuir vínculo com a organização.

Nenhum usuário poderá acessar registros de outra organização.

### 10.2 Usuários

- cada usuário pertence a uma única organização;
- e-mail será único no sistema;
- usuário inativo não poderá entrar;
- usuário não deverá ser apagado quando possuir histórico.

### 10.3 Alterações

Toda alteração técnica relevante deverá registrar:

- usuário;
- data e hora;
- valor anterior;
- valor novo;
- motivo, quando aplicável.

### 10.4 Relatórios

Um relatório aprovado e liberado não poderá ser alterado silenciosamente.

Uma alteração posterior deverá gerar nova revisão.

---

## 11. Requisitos não funcionais

### 11.1 Plataforma

- Laravel 13;
- PHP 8.3 ou superior;
- MySQL 8;
- Inertia;
- Vue;
- aplicação web responsiva.

### 11.2 Infraestrutura

Desenvolvimento local.

Produção prevista em servidor dedicado Hetzner.

### 11.3 Desempenho

- páginas principais responsivas em celular;
- upload com indicador de progresso;
- processamento de imagem fora da requisição principal quando necessário;
- geração de PDF em fila;
- consultas paginadas;
- índices adequados no banco.

### 11.4 Disponibilidade

O primeiro MVP será online-first.

Não haverá suporte offline.

---

## 12. Critérios gerais de aceite do MVP

O MVP será considerado funcional quando:

- uma organização puder ser criada;
- um administrador interno puder gerenciar usuários;
- um cliente puder ser cadastrado;
- uma unidade puder ser cadastrada;
- áreas e subáreas puderem ser cadastradas;
- um equipamento puder ser cadastrado;
- uma inspeção puder ser criada;
- responsáveis puderem ser atribuídos;
- uma avaria CIVIL puder ser registrada;
- fotografias puderem ser enviadas pelo celular;
- GUT e classificação CV forem calculados;
- uma reinspeção puder reutilizar o histórico;
- uma avaria puder manter o mesmo código;
- revisão e aprovação funcionarem;
- um PDF simplificado puder ser gerado;
- uma organização não puder acessar dados de outra;
- testes automatizados das regras críticas estiverem passando.

---

## 13. Riscos e brechas

### 13.1 Escopo excessivo

O maior risco é tentar implementar CIVIL, TAC, REC, desenhos e relatório completo ao mesmo tempo.

Mitigação:

- manter o MVP restrito a CIVIL;
- validar o fluxo antes de expandir.

### 13.2 Regras fixas no código

Classificações, comentários e recomendações podem mudar.

Mitigação:

- preparar estruturas configuráveis;
- registrar versão das regras aplicadas.

### 13.3 Vazamento entre organizações

Esse é o principal risco de segurança.

Mitigação:

- `organization_id` em todas as tabelas operacionais;
- policies;
- validações explícitas;
- testes de isolamento;
- cuidado com filas, relatórios e comandos Artisan.

### 13.4 Histórico alterado

Alterações cadastrais futuras não podem modificar relatórios antigos.

Mitigação:

- snapshots dos dados na inspeção;
- revisão;
- hash do PDF;
- bloqueio após liberação.

### 13.5 Documentação divergente

A documentação pode ficar diferente do código.

Mitigação:

- atualizar documentação e código no mesmo commit;
- não concluir etapa sem revisão do documento.

---

## 14. Decisões já aprovadas

- aplicação web responsiva;
- internet estável;
- online-first;
- Laravel 13;
- MySQL;
- Inertia e Vue;
- hospedagem futura em servidor dedicado Hetzner;
- SaaS multiempresa;
- cada usuário pertence a uma única organização;
- cada organização terá administrador interno;
- organização atende vários clientes;
- cliente possui várias unidades;
- unidade possui áreas e subáreas;
- TAG único por unidade do cliente;
- equipamento possui histórico;
- equipamento pode ter várias inspeções;
- reinspeção consulta avarias anteriores;
- avaria mantém o mesmo código;
- código da avaria é único por organização;
- responsabilidades são definidas por inspeção;
- a mesma pessoa pode executar várias etapas;
- MVP inicial somente com CIVIL;
- PDF inicial será simplificado.

---

## 15. Checklist deste documento

- [x] Objetivo do produto definido.
- [x] Hierarquia principal definida.
- [x] Conceitos do domínio definidos.
- [x] Perfis definidos.
- [x] Fluxo da inspeção definido.
- [x] Regra de reinspeção definida.
- [x] Escopo do MVP definido.
- [x] Exclusões definidas.
- [x] Requisitos não funcionais definidos.
- [x] Critérios gerais de aceite definidos.
- [x] Riscos iniciais registrados.

---

## 16. Próximo documento

```text
02-ARQUITETURA-E-PADROES.md
```

Esse documento deverá definir:

- organização do código Laravel;
- módulos;
- convenções;
- uso de enums;
- actions;
- services;
- policies;
- form requests;
- resources;
- jobs;
- events;
- testes;
- isolamento multiempresa;
- padrões de banco de dados.

---

## 17. Commit sugerido

```bash
git add docs/01-VISAO-GERAL-E-ESCOPO.md
git commit -m "docs: define visão geral e escopo do MVP"
```
