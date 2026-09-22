# 05 — Equipamentos e histórico de relatório

## Equipamento

Um equipamento pertence à organização e ao cliente único dela, ambos definidos no
servidor. Seus atributos operacionais incluem plano e item de manutenção, TAG,
prefixo de avaria, descrição, código ABC, local de instalação e os campos textuais
`area_code`, `area_name`, `subarea_code` e `subarea_name`.

Também são mantidos grupo e contador da lista de tarefas, status, dados de baixa e
metadados de auditoria. Área e subárea não possuem cadastro, chave estrangeira ou
seletor próprio.

## Identificadores e cadastro

- `maintenance_item_code` é obrigatório na edição/criação e único por organização;
- TAG é normalizada para busca e pode se repetir;
- `defect_code_prefix` é obrigatório, único no tenant e não pode mudar depois da
  primeira avaria;
- o equipamento usa `public_id` nas URLs e IDs internos nas relações.

A importação CSV mostra prévia de mapeamento e cria apenas novas linhas válidas para
o cliente ativo. Item de manutenção, TAG, denominação, prefixo de avaria e campos de
manutenção podem ser mapeados; a importação não atualiza registros existentes.

## Estados e permissões

| Estado | Efeito |
|---|---|
| `active` | Pode receber inspeção se o cliente também estiver ativo. |
| `inactive` | Preserva histórico e bloqueia novas inspeções. |
| `decommissioned` | Preserva histórico, data, responsável e motivo de baixa. |

Administradores criam, editam cadastros sem inspeções ou avarias e trocam o status.
Membros podem consultar. Após existir uma inspeção ou avaria, os dados técnicos do
cadastro ficam imutáveis; não há rota de exclusão.

## Revisões e relatórios

Não existe mais CRUD nem tabela de revisões independentes de equipamento. O
histórico exibido para o equipamento é composto pelas inspeções. Cada inspeção pode
guardar `report_revision`, único por organização e equipamento, e os responsáveis
da inspeção formam a cronologia de revisão exibida no equipamento e no relatório.

Novas inspeções capturam snapshot de equipamento e cliente; inspeções liberadas
permanecem no histórico do ativo.
