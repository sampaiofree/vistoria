# 04 — Clientes e estrutura operacional

## Hierarquia

```text
Organization
└── Client
    └── ClientUnit
        └── Area
            └── Subarea
```

Todos os níveis carregam `organization_id`, `public_id`, status e soft delete. As
relações de banco incluem o tenant para impedir que um filho seja associado a um
pai de outra empresa.

## Permissões

| Operação | Administrador da empresa | Membro | Superadministrador |
|---|---:|---:|---:|
| Listar e visualizar | Sim | Sim | Não |
| Criar e editar | Sim | Não | Não |
| Ativar ou inativar | Sim | Não | Não |
| Excluir pela interface | Não | Não | Não |

Todas as operações são limitadas à organização autenticada. Recursos de outro
tenant são rejeitados antes da escrita e não aparecem em listas ou opções.

## Cliente

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

## Unidade

Uma unidade pertence a um cliente e mantém nome, código opcional, fuso horário,
endereço, país, observações e status.

O código é normalizado e, quando informado, deve ser único dentro do cliente. O
fuso horário deve ser um identificador válido. O país usa código de duas letras e
o valor inicial da interface é `BR`.

## Área e subárea

Uma área pertence a uma unidade. Uma subárea pertence a uma área. Nome é
obrigatório; código e descrição são opcionais.

- código de área é único dentro da unidade;
- código de subárea é único dentro da área;
- códigos vazios permanecem nulos e não conflitam entre si.

## Estado operacional

O status não é propagado automaticamente aos descendentes. Inativar um cliente,
por exemplo, não altera fisicamente o status de unidades, áreas, subáreas ou
equipamentos.

A validade operacional é calculada pela cadeia:

- unidade ativa exige cliente ativo;
- área ativa exige unidade operacionalmente ativa;
- subárea ativa exige área operacionalmente ativa;
- equipamento só recebe nova inspeção se estiver ativo e toda a cadeia obrigatória
  estiver operacionalmente ativa.

Isso preserva o histórico enquanto impede novas operações sobre uma estrutura
inativa.

## Rotas e navegação

Os recursos usam rotas web Inertia. Clientes têm listagem própria; unidades,
áreas e subáreas usam recursos aninhados na criação e rotas rasas para exibição e
edição. `scopeBindings` e a resolução tenant-scoped reforçam a hierarquia.

A navegação segue o drill-down:

```text
Clientes → Cliente → Unidade → Área → Subárea
```

## Integridade e normalização

- espaços e caixa são normalizados antes da validação;
- documentos armazenam somente a forma normalizada usada para unicidade;
- códigos técnicos são normalizados antes de preencher `normalized_code`;
- Actions derivam `organization_id` e os pais do contexto autenticado;
- pais inativos não são oferecidos para novos vínculos operacionais;
- não há cascade de inativação nem exclusão destrutiva na interface.

## Cobertura automatizada

Os testes de estrutura operacional verificam CRUD, hierarquia, autorização,
tenant, pais inativos, constraints compostas, status e visibilidade das ações para
membros.
