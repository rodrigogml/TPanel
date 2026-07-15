# Responsive Validation Matrix

Matriz minima para validar o TPanel em desktop, tablet e celular. A validacao deve ser feita em navegador real ou ferramenta headless com screenshot.

## Viewports

| Perfil | Largura x altura | Criterio principal |
|--------|------------------|--------------------|
| Desktop | 1440 x 900 | Sidebar visivel, topbar em uma linha, cards em grid amplo e tabelas sem sobreposicao. |
| Laptop compacto | 1280 x 720 | Conteudo principal permanece legivel, cards nao comprimem texto e tabelas usam overflow horizontal quando necessario. |
| Tablet | 820 x 1180 | Layout alterna para menos colunas, topbar preserva busca/acoes e painels empilham corretamente. |
| Celular | 390 x 844 | Menu hamburguer abre/fecha, cards ficam em coluna unica e botoes mantem area de toque adequada. |
| Celular estreito | 320 x 740 | Texto nao extrapola botoes/cards, alertas compactam e tabelas continuam navegaveis por rolagem horizontal. |

## Success Criteria

- Sidebar desktop nao cobre o conteudo principal.
- Menu mobile inicia recolhido e abre sem deslocar cards para fora da viewport.
- Topbar nao sobrepoe pesquisa, alertas, usuario ou botao de tema.
- Cards preservam altura minima estavel e texto visivel.
- Badges `NORMAL`, `WARNING`, `CRITICAL` e `UNAVAILABLE` cabem em tabela e card.
- Tabelas com conteudo longo usam rolagem horizontal em vez de quebrar o layout.
- Tema claro e escuro mantem contraste visual suficiente para leitura operacional.

## Evidence To Capture

Para considerar a validacao executada, anexar ou registrar:

- viewport validado;
- tema claro/escuro quando aplicavel;
- screenshot ou descricao objetiva do resultado;
- falhas encontradas com seletor/area afetada;
- commit ou tarefa que corrigiu eventual sobreposicao.
