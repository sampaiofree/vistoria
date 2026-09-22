# 01 — Visão geral e escopo atual

## Objetivo do produto

O Vistoria gerencia inspeções técnicas por organização: cadastro do ativo,
planejamento, trabalho de campo, revisão, liberação e prévia de relatório. O
sistema preserva o contexto da inspeção e das avaliações em snapshots para
rastreabilidade dentro do tenant.

## Perfis de acesso

| Perfil | Alcance |
|---|---|
| Superadministrador global | Cria e administra organizações; não acessa módulos operacionais. |
| Administrador da empresa | Mantém configurações, usuários, cliente, equipamentos e conteúdo permitido pelo estado. |
| Membro | Consulta recursos autorizados do tenant. |
| Papel operacional | Planejador, Inspetor, Revisor ou Liberador; combinado à responsabilidade da inspeção para executar transições. |

## Estrutura do domínio

```text
Organization
├── Users
├── Client (zero ou um)
└── Equipments
    ├── Inspections
    │   ├── responsáveis, aspectos gerais e revisão do relatório
    │   └── vínculos de classificação para Nota M2
    └── Defects
        └── DefectAssessments
            ├── itens de quantitativo e fotografias
            └── localização em versão de mapa
```

Área, subárea e seus códigos pertencem ao equipamento como campos textuais. Não
existem cadastros de unidades, áreas ou subáreas, nem relações para essas entidades.

## Fluxo principal implementado

1. Um administrador configura organização, usuários e o cliente único.
2. O administrador cadastra ou importa equipamento ativo com item de manutenção,
   TAG e prefixo de avaria.
3. O Planejador cria uma ou mais inspeções e atribui um Inspetor.
4. O Inspetor executa a inspeção, registra avaliações, fotos, quantitativos, mapa,
   classificação técnica e conteúdo do relatório.
5. Revisor e Liberador conduzem as transições até a liberação.
6. A prévia reúne a documentação e permite exportação local de PDF ou DOCX.

## Capacidades entregues

- multiempresa, autenticação, senha temporária, notificações e Horizon;
- cliente único e equipamentos com atributos textuais;
- inspeções iniciais/reinspeções, responsáveis, estados, referências e aspectos
  gerais reutilizáveis por template;
- avarias por categoria CIVIL, TAC, REC e TEL, avaliações e relações históricas;
- GUT, classificação TEL, quantitativos por itens, fotos e localização;
- resumo por classificação e vínculo manual de Nota M2 para grupos publicados;
- prévia A4 renderizada no navegador.

## Fora do escopo ou incompleto

- API pública, integração SAP, arquivo de relatório persistido, aplicativo nativo
  e uso offline não existem.
- Exportar não altera o fluxo nem substitui a liberação da inspeção.
- Imutabilidade append-only de avaliações publicadas, resumo independente do
  catálogo, precisão decimal em string no contrato e cabeçalho completo do resumo
  ainda não estão entregues; consulte o documento 17.
