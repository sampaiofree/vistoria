# 04 — Cliente e estrutura operacional

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

## Arquivo de logotipo

O logotipo é alterado na edição do cliente, aceita JPEG, PNG ou WEBP de até 2 MB e
fica no disco público segregado pela organização. Sua substituição remove o arquivo
anterior; ele pode ser usado na capa do relatório.

## Integridade

- a organização vem do tenant autenticado, nunca do payload;
- relações e consultas mantêm `organization_id` para bloquear mistura de tenants;
- documento é normalizado antes da unicidade;
- inativar o cliente preserva os equipamentos e o histórico, mas impede novas
  inspeções enquanto a cadeia operacional estiver inativa.
