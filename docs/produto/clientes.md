# Cliente e estrutura operacional

## Modelo atual

Cada organização tem zero ou um `Client`, inclusive quando existem registros
excluídos logicamente. Não há unidades, áreas ou subáreas como entidades do
domínio. Dados de área e subárea usados na operação são atributos textuais do
equipamento.

```text
Organization
└── Client (0..1)
    └── Equipments (N)
```

O cliente contém nome, razão social, documento opcional normalizado, e-mail,
telefone, logotipo, observações e status `active` ou `inactive`.

## Acesso e operação

O cadastro fica em **Configurações → Cliente**. Somente administradores da empresa
podem criar, editar ou alterar seu status; membros não o acessam e
superadministradores não entram no tenant operacional. Não há exclusão pela
interface.

O servidor associa o cliente único ativo aos novos equipamentos. Formulários de
equipamento e inspeção não selecionam cliente. Sem um cliente ativo, não é possível
criar equipamento nem iniciar uma nova inspeção para seus equipamentos.

Na página do cliente, **Gerenciar usuários do cliente** abre a lista de usuários
da organização filtrada pelo tipo `client`. O administrador cria e mantém essas
contas pelo cadastro de usuários existente. Como há no máximo um cliente por
organização, a associação usa a organização da conta, sem um `client_id` em
`users`. Contas do tipo `client` não possuem papel operacional. Seu login abre
a lista de inspeções liberadas dos equipamentos do cliente da organização.
As telas técnicas existentes ficam disponíveis apenas para consulta, com PDF
e planilha de quantitativos; módulos internos e alterações são bloqueados.

## Arquivo de logotipo

O logotipo é alterado na edição do cliente, aceita JPEG, PNG ou WEBP de até 2 MB e
fica no disco privado `branding_images`, segregado pela organização e entregue
por rota autorizada. Sua substituição remove o arquivo anterior; ele pode ser
usado na capa do relatório.

## Integridade

- a organização vem do tenant autenticado, nunca do payload;
- relações e consultas mantêm `organization_id` para bloquear mistura de tenants;
- documento é normalizado antes da unicidade;
- inativar o cliente preserva os equipamentos e o histórico, mas impede novas
  inspeções enquanto a cadeia operacional estiver inativa.
