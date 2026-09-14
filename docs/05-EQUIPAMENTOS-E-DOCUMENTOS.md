# 05 — Equipamentos, documentos e revisões

## Equipamento

O equipamento pertence a uma organização, cliente, unidade e área. A subárea é
opcional, mas, quando informada, deve pertencer à área selecionada.

Campos principais:

- TAG e prefixo de código de avaria;
- nome e descrição;
- fabricante, modelo e número de série;
- código patrimonial e código ABC;
- localização de instalação e data de comissionamento;
- observações e status;
- metadados de descomissionamento.

## Identificadores e unicidade

- a TAG é normalizada e única no conjunto organização, cliente e unidade;
- o prefixo de avaria é um código técnico obrigatório e único na organização;
- `public_id` é usado nas URLs;
- IDs internos continuam sendo usados nos vínculos e constraints.

Alterar a TAG não altera códigos de avarias já criadas. O prefixo é a base para
novos códigos e a avaria preserva sua identidade permanente.

## Estados

| Estado | Efeito |
|---|---|
| `active` | Pode receber inspeção se toda a estrutura estiver ativa |
| `inactive` | Preserva histórico e impede nova inspeção |
| `decommissioned` | Preserva histórico e registra data, usuário e motivo |

Ativação, inativação e descomissionamento são ações de administrador da empresa.
Um equipamento descomissionado não é excluído.

Mesmo com status `active`, `canReceiveInspection()` exige cliente, unidade e área
operacionalmente ativos e, quando usada, subárea operacionalmente ativa.

## Permissões

- administradores da empresa criam, editam e alteram status;
- membros podem listar e visualizar equipamentos do tenant;
- superadministradores não acessam equipamentos;
- não existe rota de exclusão do equipamento.

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
