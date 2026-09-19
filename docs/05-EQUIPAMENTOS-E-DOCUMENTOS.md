# 05 — Equipamentos, documentos e revisões

## Equipamento

O equipamento pertence a uma organização e ao cliente único dela, resolvido
automaticamente no servidor.

Campos principais:

- plano e item de manutenção;
- TAG e prefixo de código de avaria;
- códigos e nomes de área/subárea como atributos textuais, sem cadastros ou relações;
- grupo de lista de tarefas e numerador de grupos;
- nome e descrição;
- código ABC, localização de instalação e status;
- metadados de descomissionamento.

## Identificadores e unicidade

- o item de manutenção é obrigatório ao criar e editar e único por organização, inclusive nos equipamentos excluídos logicamente;
- a TAG é normalizada para busca, mas pode se repetir;
- o prefixo de avaria é obrigatório e único na organização; nunca é gerado automaticamente;
- `public_id` é usado nas URLs;
- IDs internos continuam sendo usados nos vínculos e constraints.

Alterar a TAG não altera códigos de avarias já criadas. O prefixo é a base para
novos códigos e a avaria preserva sua identidade permanente. Depois da primeira avaria, o prefixo não pode ser alterado.

## Correspondência com a planilha de ativos

| Coluna da planilha | Campo |
|---|---|
| Plano de manutenção | `maintenance_plan_code` |
| Item manutenção | `maintenance_item_code` |
| Campo de ordenação (TAG) | `tag` |
| Descrição item de manutenção | `description` |
| Local de instalação | `installation_location` |
| Area(usina) | `area_code` |
| Area.nome | `area_name` |
| Sub-area | `subarea_code` |
| sub-area.nome | `subarea_name` |
| Denominação do loc.instalação | `name` |
| GrpLisTar. | `task_list_group` |
| Numerador de grupos | `task_list_group_counter` |
| Código ABC | `abc_code` |
| Prefixo de avaria | `defect_code_prefix` |

Os oito novos campos são anuláveis no banco para preservar os registros anteriores.
Os novos códigos usam texto de até 80 caracteres; os nomes de área/subárea, até
180. Zeros à esquerda (`08`) e códigos alfanuméricos (`T5`, ABC `D`) são preservados.
Campos opcionais vazios são armazenados como `NULL`.

A migração não inventa itens ou prefixos para equipamentos antigos. Ambos devem
ser informados na próxima edição quando estiverem ausentes. Os novos atributos integram
os snapshots de novas inspeções; os snapshots históricos não recebem esses campos.

Para aplicar, execute `php artisan migrate`. As migrações anteriores pendentes de
remoção de áreas, subáreas e unidades descartam esses cadastros e suas chaves nos
snapshots históricos de forma irreversível; os atributos textuais novos não
recuperam esses dados.

## Estados

| Estado | Efeito |
|---|---|
| `active` | Pode receber inspeção se toda a estrutura estiver ativa |
| `inactive` | Preserva histórico e impede nova inspeção |
| `decommissioned` | Preserva histórico e registra data, usuário e motivo |

Ativação, inativação e descomissionamento são ações de administrador da empresa.
Um equipamento descomissionado não é excluído.

Mesmo com status `active`, `canReceiveInspection()` exige que o cliente único esteja
operacionalmente ativo. Novos equipamentos não aceitam nem exibem `client_id`:
sem cliente cadastrado, o administrador deve cadastrá-lo em Configurações.

## Importação CSV

Administradores podem importar equipamentos pela listagem. O sistema lê o CSV,
sugere o mapeamento dos cabeçalhos e mostra uma amostra antes da confirmação.
Item manutenção, TAG, Denominação do loc.instalação e Prefixo de avaria devem ser
mapeados. Linhas inválidas — inclusive sem prefixo — são ignoradas e relatadas;
as válidas são criadas para o cliente único ativo da organização. A importação não
atualiza equipamentos existentes.

## Permissões

- administradores da empresa criam, editam cadastros sem inspeções ou avarias e alteram status;
- membros podem listar e visualizar equipamentos do tenant;
- superadministradores não acessam equipamentos;
- não existe rota de exclusão do equipamento.

Quando houver pelo menos uma inspeção ou avaria, o cadastro técnico do equipamento
fica imutável para preservar a rastreabilidade. A mudança de status continua sendo
uma ação administrativa separada.

## Documentos

Documentos pertencem ao equipamento e são privados. Tipos aceitos:

- desenho geral, de montagem ou técnico;
- manual;
- ficha técnica;
- procedimento;
- relatório anterior;
- memorial;
- outro.

O upload aceita PDF, XLSX, XLSM, DOC, DOCX, PNG, JPEG e WEBP, com até 25 MB. São
armazenados nome original, MIME, extensão, tamanho, checksum, número, revisão,
data de emissão, descrição, tipo, status e usuário do upload.

O download e a visualização passam por Policy e Controller; o caminho privado não
é exposto diretamente.

### Versionamento de arquivos

`document_group` reúne versões do mesmo documento. Quando não informado, recebe
um ULID. `is_current` indica a versão corrente; a ação correspondente mantém a
escolha dentro do equipamento e da organização. O status do arquivo é `active` ou
`inactive`.

Um documento referenciado por uma inspeção ou por dados históricos continua
preservado. A interface não oferece exclusão destrutiva de documentos.

## Histórico de revisões do equipamento

As revisões são registros estruturados separados dos arquivos. Cada uma contém:

- tipo de emissão (`A`, `B`, `C`, `D`, `E`, `F`, `G`, `H` ou `L`);
- data da revisão;
- nomes de preparador, verificador, aprovador e liberador;
- autores técnicos da criação e última atualização do registro.

Os nomes são snapshots textuais e não vínculos atuais com usuários. O histórico é
ordenado por data e ID, pode ser criado, editado ou removido por administradores e
alimenta a cronologia exibida no equipamento e no relatório.

## Relação com inspeções

- apenas um equipamento apto pode receber uma nova inspeção;
- a criação da inspeção captura um snapshot da estrutura e dos dados relevantes;
- documentos do mesmo equipamento, inclusive versões inativas preservadas no
  histórico, podem ser selecionados como referências da inspeção;
- inspeções liberadas compõem o histórico do equipamento;
- mapas, avarias e revisões permanecem associados ao equipamento original.

## Cobertura automatizada

Os testes verificam CRUD, unicidade, hierarquia, transições de status, documentos
privados, versão corrente, revisões, isolamento entre tenants e bloqueio para
perfis sem permissão.
