# 03 — Organizações, usuários e isolamento multiempresa

## Modelo atual

Cada usuário operacional pertence a exatamente uma organização. O único usuário
sem organização é o superadministrador global.

```text
Organization 1 ── N User
Organization 1 ── N registros operacionais
User(super_admin) ── sem Organization
```

`Organization` e `User` usam `public_id` ULID. Organizações possuem soft delete;
usuários preservam estado por status.

## Estados e tipos de conta

Organizações:

- `active`;
- `suspended`;
- `inactive`.

Usuários:

- `active`;
- `inactive`;
- `suspended`.

Tipos de conta:

- `super_admin`;
- `company_admin`;
- `member`.

O model impede salvar um superadministrador com organização e impede salvar uma
conta comum sem organização.

## Resolução do tenant

As rotas operacionais aplicam, nesta ordem lógica:

1. autenticação;
2. verificação de usuário ativo;
3. verificação de organização ativa;
4. troca obrigatória de senha temporária;
5. resolução do tenant.

`TenantContext` é registrado como serviço scoped. `ResolveTenant` usa a organização
do usuário autenticado, rejeita superadministradores, define o contexto antes do
Controller e sempre o limpa ao terminar a requisição.

Rotas globais usam `global.super-admin` e não passam pelo tenant. Não há seletor de
empresa nem impersonação.

## Administração global

O superadministrador acessa `/admin/organizations` para:

- listar e pesquisar empresas por nome, razão social ou documento;
- filtrar por status;
- criar empresa e primeiro administrador em uma transação;
- editar nome, razão social e documento;
- suspender e reativar empresas.

A criação gera uma senha temporária aleatória para o administrador, exibida apenas
na resposta da operação. A nova organização recebe automaticamente as categorias,
classificações e opções GUT padrão descritas em
[09 — Classificação e GUT](09-CLASSIFICACAO-CIVIL-GUT.md).

Uma empresa suspensa ou inativa não permite operação dos seus usuários. A
interface global atual reativa empresas suspensas; o status `inactive` existe no
domínio e nos filtros, mas não é uma transição oferecida pelo formulário global.

## Configurações da empresa

O administrador da empresa acessa `/settings/company` para manter:

- nome, razão social e documento;
- logotipo público;
- cor primária;
- ícone público da navegação.

Logotipo e ícone podem ser substituídos ou removidos. A organização do usuário é
sempre obtida do `TenantContext`; o formulário não escolhe outro tenant.

## Gestão de usuários

Em `/settings/users`, administradores da empresa podem:

- pesquisar e filtrar usuários do próprio tenant;
- criar membros ou outros administradores da empresa;
- editar nome, e-mail e tipo de conta;
- alterar status;
- redefinir a senha de outro usuário ativo.

Criação e redefinição geram senha temporária e ativam `must_change_password`. O
próprio administrador não pode usar a ação de redefinição sobre sua conta. O
middleware libera apenas as rotas de senha e logout até a troca ser concluída.

## Autenticação e sessão

- login e logout usam a sessão web do Laravel;
- login atualiza o último acesso;
- usuário inativo ou suspenso é desconectado;
- organização não ativa desconecta usuários comuns;
- superadministrador ativo pode abrir a dashboard global e o Horizon;
- o Horizon também exige senha definitiva e ausência de organização.

## Notificações

O centro de notificações usa a tabela padrão de notificações do Laravel. Usuários
podem listar suas notificações, abrir uma notificação ou marcar todas como lidas.

Falhas definitivas no processamento de fotografia de avaliação, fotografia da
vista geral ou mapa geram uma notificação de banco para:

- o usuário que enviou o arquivo, quando conhecido;
- os responsáveis da inspeção;
- somente destinatários ativos da mesma organização.

A notificação aponta para a tela em que o arquivo pode ser substituído. Ela não
executa retry automático além das três tentativas do próprio Job.

## Regras de isolamento

- e-mail de usuário é globalmente único;
- cadastros operacionais carregam `organization_id`;
- consultas administrativas e operacionais são filtradas pelo tenant;
- route bindings públicos são confirmados novamente dentro da organização;
- Policies impedem acesso cruzado;
- relações compostas impedem combinar pais e filhos de organizações diferentes;
- IDs de organização enviados pelo navegador não são usados para definir escopo.

## Bootstrap de produção

O primeiro superadministrador pode ser criado com:

```bash
php artisan app:bootstrap-super-admin
```

O comando aceita `--email` e `--name`, é idempotente para um superadministrador já
compatível e falha se o e-mail pertencer a outro tipo de conta. A senha temporária
é mostrada somente na criação e deve ser trocada no primeiro acesso.

Não há seeder de usuários ou dados operacionais. O `DatabaseSeeder` intencionalmente
não cria registros.
