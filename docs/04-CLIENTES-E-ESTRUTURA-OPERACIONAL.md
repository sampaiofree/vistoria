# 04 — Clientes e estrutura operacional

## Hierarquia

```text
Organization
└── Client
```

O cliente carrega `organization_id`, `public_id`, status e soft delete. As
relações de banco incluem o tenant para impedir que um filho seja associado a um
pai de outra empresa.

## Permissões

| Operação | Administrador da empresa | Membro | Superadministrador |
|---|---:|---:|---:|
| Listar e visualizar | Sim | Não | Não |
| Criar e editar | Sim | Não | Não |
| Ativar ou inativar | Sim | Não | Não |
| Excluir pela interface | Não | Não | Não |

Todas as operações são limitadas à organização autenticada. Recursos de outro
tenant são rejeitados antes da escrita e não aparecem em listas ou opções.

## Cliente

Cada organização possui zero ou um único cliente, inclusive considerando registros
excluídos logicamente. O cadastro fica em **Configurações → Cliente** e somente
administradores da empresa podem acessá-lo. Quando ainda não houver cliente, o
administrador poderá cadastrá-lo; depois disso, apenas poderá editá-lo ou alterar
seu status.

Campos funcionais principais:

- nome e razão social;
- documento normalizado, opcional e único dentro da organização;
- e-mail e telefone;
- logotipo;
- observações;
- status `active` ou `inactive`.

O logotipo é aceito apenas na edição, nos formatos JPEG, PNG ou WEBP, com até
2 MB. Ele é armazenado no disco público sob a organização e substitui o arquivo
anterior. O logotipo pode ser usado na capa do relatório.

## Estado operacional

O status não é propagado automaticamente aos descendentes. Inativar um cliente,
por exemplo, não altera fisicamente o status de equipamentos.

A validade operacional é calculada pela cadeia:

- equipamento só recebe nova inspeção se estiver ativo e o cliente estiver operacionalmente ativo.

Isso preserva o histórico enquanto impede novas operações sobre uma estrutura
inativa.

## Rotas e navegação

As URLs de cliente são preservadas, mas a navegação é:

```text
Configurações → Cliente
```

## Integridade e normalização

- espaços e caixa são normalizados antes da validação;
- documentos armazenam somente a forma normalizada usada para unicidade;
- Actions derivam `organization_id` e os pais do contexto autenticado;
- o cliente único ativo é associado automaticamente a novos equipamentos;
- não há seleção ou filtro de cliente no cadastro de equipamentos e inspeções;
- cliente inativo impede novos vínculos operacionais;
- não há cascade de inativação nem exclusão destrutiva na interface.

## Cobertura automatizada

Os testes verificam unicidade por organização, autorização exclusiva de
administradores, tenant, status e associação automática em operações operacionais.
