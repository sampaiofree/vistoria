# 01 — Visão geral e escopo atual

## Objetivo do produto

O Vistoria centraliza a execução e a rastreabilidade de inspeções técnicas em uma
aplicação web responsiva. Cada empresa opera em um tenant próprio e mantém sua
estrutura operacional, equipamentos, equipe, taxonomia, inspeções e arquivos.

## Perfis de acesso

### Superadministrador global

- não pertence a uma organização;
- administra empresas em `/admin/organizations`;
- cria cada empresa junto com seu primeiro administrador;
- pode editar dados cadastrais e suspender ou reativar empresas;
- acessa o Horizon quando está ativo e já trocou a senha temporária;
- não acessa módulos operacionais e não impersona organizações.

### Administrador da empresa

- pertence a uma única organização;
- administra usuários, identidade visual e cadastros do tenant;
- cria e mantém clientes, estrutura, equipamentos, documentos e inspeções;
- configura categorias, classificações, faixas e opções GUT;
- gerencia metadados do relatório, aspectos gerais, referências e responsáveis;
- também pode assumir funções técnicas quando for atribuído à inspeção.

### Membro

- pertence a uma única organização;
- consulta dados operacionais do tenant;
- atua em uma inspeção conforme as responsabilidades recebidas;
- não administra empresas, usuários, estrutura, equipamentos ou taxonomia.

Contas inativas ou suspensas têm a sessão encerrada. Empresas que não estejam
ativas também bloqueiam os usuários do tenant. Contas criadas ou redefinidas com
senha temporária são direcionadas para a troca de senha antes das demais rotas.

## Estrutura do domínio

```text
Organização
├── usuários
├── clientes
│   └── unidades
│       └── áreas
│           └── subáreas (opcionais para o equipamento)
├── categorias de avaria
│   ├── classificações
│   └── opções GUT
└── equipamentos
    ├── documentos
    ├── revisões
    ├── avarias permanentes
    └── inspeções
        ├── responsáveis e histórico de status
        ├── avaliações das avarias
        ├── fotografias
        ├── vista geral do relatório
        └── mapas e marcações
```

## Fluxo principal

1. O superadministrador cria uma empresa e seu administrador inicial.
2. O administrador da empresa configura usuários, identidade visual e estrutura
   operacional.
3. Um equipamento ativo é vinculado a cliente, unidade, área e, opcionalmente,
   subárea ativas.
4. O administrador cria uma inspeção planejada e define responsáveis técnicos.
5. Um preparador inicia a inspeção, registra ou reavalia avarias, classifica por
   GUT, informa o quantitativo, envia fotos e, quando exigido pela categoria,
   cria marcações nos mapas. Condições observáveis só podem ser publicadas com um
   quantitativo e duas fotografias prontas.
6. A inspeção passa por verificação e aprovação conforme as responsabilidades.
7. A prévia A4 pode ser impressa ou exportada no navegador quando seus requisitos
   próprios estão completos.
8. O administrador registra a geração do relatório no workflow; o liberador então
   pode liberar a inspeção.

O fluxo de estados e suas pré-condições estão detalhados em
[06 — Inspeções e fluxo](06-INSPECOES-E-FLUXO.md).

## Escopo implementado

- autenticação por sessão e troca obrigatória de senha temporária;
- administração global e isolamento multiempresa;
- configurações de empresa e usuários;
- estrutura operacional e equipamentos;
- inspeções iniciais e reinspeções;
- avarias permanentes, relações e avaliações históricas;
- classificação GUT configurável por categoria;
- fotos privadas, mapas de localização e notificações de falha;
- dashboard operacional e navegação contextual;
- prévia de relatório, impressão e exportação PDF/DOCX no cliente;
- filas Redis monitoradas pelo Horizon.

## Limites de escopo

Não existem atualmente:

- seleção ou impersonação de tenant pelo superadministrador;
- exclusão definitiva pela interface dos principais cadastros operacionais;
- API pública ou integração automática com sistemas externos;
- armazenamento de um PDF/DOCX oficial no backend;
- retry manual de imagens que atingiram falha definitiva;
- dados fictícios criados por seed;
- aplicativo nativo, modo offline, IA ou faturamento SaaS.

As categorias TAC e REC são provisionadas junto com CIVIL e podem ser configuradas,
mas isso não significa que todos os layouts e processos especializados dessas
disciplinas estejam implementados.

## Requisitos operacionais

- PHP 8.3 ou superior;
- Laravel 13, Inertia 3 e Vue 3;
- Node compatível com `.nvmrc` e `package.json`;
- MySQL no ambiente alvo;
- Redis e Horizon para processamento assíncrono;
- Imagick; ImageMagick e Ghostscript são necessários quando origens históricas em
  PDF precisarem ser processadas;
- storage privado persistente para documentos, fotografias e mapas.

Consulte [02 — Arquitetura e padrões](02-ARQUITETURA-E-PADROES.md) e
[13 — Deploy](13-DEPLOY-HETZNER.md) para detalhes técnicos.
