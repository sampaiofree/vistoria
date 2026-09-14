# 06 — Dashboard, inspeções e fluxo operacional

## Dashboard

A dashboard possui dois modos:

- **global**: superadministrador recebe acesso à administração de empresas e não
  vê indicadores operacionais;
- **operacional**: usuários de uma organização veem inspeções, prioridades,
  atividades recentes e uma inspeção em destaque.

Para administradores, os indicadores abrangem a empresa. Para membros, as
consultas são filtradas pelas responsabilidades do usuário. Prioridades distinguem
planejadas atrasadas, aguardando verificação, em correção e aguardando aprovação.

Os blocos de contagem, inspeções, fluxo e atividades usam propriedades deferred
independentes do Inertia e podem ser recarregados separadamente. A inspeção em
destaque prioriza uma reinspeção em andamento quando houver.

## Criação da inspeção

Somente um administrador da empresa cria inspeções. A Action:

1. bloqueia a linha do equipamento;
2. confirma que equipamento e cadeia operacional estão ativos;
3. impede mais de uma inspeção aberta por equipamento;
4. localiza a última inspeção liberada do equipamento;
5. define `initial` quando não há anterior ou `reinspection` quando há;
6. cria número no formato `INS-AAAA-NNNNNN`;
7. captura o snapshot do equipamento e da estrutura;
8. registra o estado inicial no histórico;
9. cria dois blocos vazios para a vista geral do relatório.

Na criação podem ser informados equipamento, ordem de serviço, número externo do
relatório, procedimento, classificação atmosférica e data programada.

## Responsabilidades

Uma inspeção aceita vários usuários em cada função:

| Valor | Função |
|---|---|
| `preparer` | Preparador |
| `reviewer` | Verificador |
| `approver` | Aprovador |
| `releaser` | Liberador |

O primeiro usuário de cada função torna-se principal. O administrador pode trocar
o principal, adicionar ou remover atribuições enquanto a inspeção não estiver
liberada nem cancelada. Depois que uma etapa começa, porém, não pode remover o
único usuário de uma função já exigida pelo estado atual. A mesma pessoa pode
exercer mais de uma função.

Cada transição técnica exige que o ator esteja atribuído à função correspondente;
ser administrador, por si só, não substitui essa atribuição.

## Estados e transições

```text
planned
  ├── in_progress
  └── canceled

in_progress ── awaiting_review ── awaiting_approval ── approved
                    │                    │                  │
                    └── in_correction ◀──┘                  └── report_generated ── released
                              │
                              └── awaiting_review
```

Qualquer estado não final pode ir para `canceled`. `released` e `canceled` são
finais.

| Transição | Ator e requisitos principais |
|---|---|
| Planejada → Em inspeção | Preparador atribuído; equipamento ainda apto |
| Em inspeção/correção → Aguardando verificação | Preparador; preparador e verificador presentes; coberturas completas |
| Aguardando verificação → Em correção | Verificador; justificativa |
| Aguardando verificação → Aguardando aprovação | Verificador; verificador e aprovador presentes |
| Aguardando aprovação → Em correção | Aprovador; justificativa |
| Aguardando aprovação → Aprovada | Aprovador atribuído |
| Aprovada → Relatório gerado | Administrador; emissão definida e principal em cada função |
| Relatório gerado → Liberada | Liberador atribuído e data de geração registrada |
| Não final → Cancelada | Administrador ou responsável; justificativa |

O histórico armazena origem, destino, ator, motivo e data. Datas resumidas da
inspeção são preenchidas nas etapas correspondentes.

## Cobertura antes da verificação

O envio para verificação executa três validadores:

- toda avaria pertencente ao escopo da inspeção, nova ou herdada, precisa ter
  avaliação completa no ciclo corrente;
- avaliações com evidência obrigatória já precisam de quantitativo e pelo menos
  duas fotos prontas para serem publicadas; o envio repete a validação das fotos;
- categorias ativas com `requires_location_map` exigem mapa pronto, marcação
  consistente e ao menos uma foto pronta selecionada em cada marcação aplicável.

As condições `not_located` e `not_inspected` não exigem marcação. Regras de GUT e
conclusão da avaliação são aplicadas antes desses validadores.

## Tela e navegação contextual

A inspeção possui páginas próprias para:

- visão geral;
- vista geral fotográfica do relatório;
- avarias;
- localização;
- equipe;
- fotografias;
- documentos;
- histórico;
- relatório.

As abas preservam o contexto da inspeção e expõem apenas ações autorizadas. A
listagem oferece busca e filtros por número, cliente, unidade, equipamento, estado,
tipo, responsável, função e períodos programado ou inspecionado.

## Conteúdo técnico e metadados

Administradores mantêm metadados do relatório, inclusive número externo, data,
tipo de emissão, projetista, número do Projetista I e texto da primeira página.
Aspectos gerais usam um documento estruturado editado com Tiptap e armazenado em
`general_notes`.

A vista geral possui dois blocos, cada um com comentário, recomendação e dois
slots de fotografia. Usuários operacionais podem editá-la enquanto a inspeção não
estiver em estado final se forem administradores ou responsáveis da inspeção.

Documentos de referência são versões de documentos do mesmo equipamento. Eles
podem ser mantidos por administradores enquanto a inspeção não estiver final.

## Prévia, PDF e DOCX

A página de relatório monta capa, sumário, aspectos gerais, vista geral, mapas e
documentação fotográfica em páginas A4. O navegador espera fontes, imagens e
paginação estabilizarem antes de habilitar impressão ou exportação.

Para exportar, são exigidos:

- número externo do relatório;
- número do Projetista I;
- quatro fotos prontas na vista geral;
- comentário e recomendação nos dois blocos;
- nenhuma foto publicada sem numeração derivada dos mapas.

Outros avisos, como avaliações pendentes, responsáveis principais ausentes ou
fotos publicadas ainda processando, aparecem na validação da prévia, mas não são
todos usados pelo mesmo bloqueio cliente-side de exportação.

PDF e DOCX são gerados no navegador como páginas rasterizadas. A transição
`generate-report` é independente: ela não recebe nem persiste o arquivo exportado;
apenas valida o workflow, registra data e muda o estado para `report_generated`.

## Limites atuais

- não há reabertura de inspeção liberada ou cancelada;
- não há assinatura digital nem armazenamento backend do relatório exportado;
- o read model operacional ainda herda uma classe com nome histórico
  `ViewFirstDemoPresenter`, mas não existe modo demo nem dado provisório paralelo;
- a prévia contém textos e estruturas específicos do modelo técnico atual, mesmo
  com taxonomia configurável.

## Cobertura automatizada

Os testes cobrem criação concorrente, snapshots, responsáveis, rotas, transições,
pré-condições, histórico, reinspeção, vista geral, metadados, navegação, paginação e
composição do relatório.
