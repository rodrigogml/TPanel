# Research: TPanel MVP

Documento produzido no Phase 0 do plan. Resolve as decisoes tecnicas necessarias antes do design.

## Decision 1: Monolito PHP em camadas

**Decision**: O MVP sera uma aplicacao PHP monolitica sobre Apache, organizada em UI, controllers, services, command executor e system scripts.

**Rationale**: Esta decisao segue o briefing e a constituicao. Mantem instalacao simples no Apache, evita API REST nesta fase e ainda preserva limites internos testaveis.

**Alternatives considered**: Framework full-stack maior, SPA com backend separado, API REST desde o inicio. Rejeitados por aumentarem escopo, dependencias e superficie operacional sem necessidade no MVP.

## Decision 2: Apache como fonte de autenticacao

**Decision**: Apache autentica o usuario e o PHP consome a identidade autenticada. O TPanel mapeia a identidade para os papeis Administrador ou Monitor.

**Rationale**: Requisito fornecido e principio constitucional. Evita criar uma segunda fonte de autenticacao no MVP.

**Alternatives considered**: Login proprio em PHP, OAuth externo, sessao independente. Rejeitados para esta fase por ampliar escopo de seguranca.

## Decision 3: Autorizacao por papel e capacidade

**Decision**: Administrador pode executar acoes administrativas autorizadas. Monitor pode visualizar dados, reconhecer alertas e comentar eventos, mas nao pode alterar estado do servidor.

**Rationale**: Resolve a decisao da spec e preserva seguranca. Acoes do Monitor ainda precisam de auditoria por alterarem estado de acompanhamento operacional.

**Alternatives considered**: Monitor estritamente somente leitura; Monitor com acoes administrativas limitadas. A primeira reduziria utilidade operacional; a segunda viola o limite de seguranca do MVP.

## Decision 4: Executor de comandos por catalogo autorizado

**Decision**: Toda acao administrativa passa por um catalogo de comandos autorizados, validacao de parametros, timeout e auditoria.

**Rationale**: Atende o principio "Security Before Capability". O catalogo e a fonte da verdade para o que pode ser executado.

**Alternatives considered**: Permitir comandos digitados pelo usuario; wrappers ad hoc em cada tela. Rejeitados por risco de execucao arbitraria e baixa auditabilidade.

## Decision 5: Coleta de metricas hibrida

**Decision**: O MVP tera leituras sob demanda para telas atuais e coleta periodica planejada para historico, com politica documentada antes da implementacao.

**Rationale**: Leituras sob demanda entregam valor imediato ao dashboard. Historico e retencao exigem controle de frequencia, volume e armazenamento.

**Alternatives considered**: Coleta apenas sob demanda; coleta continua desde o primeiro corte. A primeira nao atende historico; a segunda pode criar custo e risco antes dos limites estarem claros.

## Decision 6: Retencao de metricas configuravel

**Decision**: Retencao de metricas sera configuravel, com padrao inicial de 90 dias.

**Rationale**: Decisao confirmada na spec. 90 dias da base para tendencias sem tornar a politica inflexivel.

**Alternatives considered**: 7 dias, 30 dias, sem padrao. 7/30 dias reduzem utilidade historica; sem padrao cria ambiguidade operacional.

## Decision 7: MySQL para auditoria, metricas e configuracoes aplicaveis

**Decision**: MySQL sera usado para auditoria, alertas, comentarios, reconhecimentos, metricas historicas, configuracoes aplicaveis e resultados de notificacao.

**Rationale**: Banco definido no briefing. Persistir auditoria e historico fora de arquivos facilita consulta, filtros e retencao.

**Alternatives considered**: Arquivos JSONL, SQLite, somente logs do sistema. Rejeitados para o MVP por menor capacidade de consulta e gestao de retencao centralizada.

## Decision 8: Contratos internos de payload para UI sem API REST publica

**Decision**: Mesmo sem API REST publica, a borda controller -> UI tera contratos documentados para payloads, formularios, erros e estados.

**Rationale**: O projeto cruza fronteiras entre backend, UI e comandos do sistema. Contratos evitam drift de nomes, tipos e estados.

**Alternatives considered**: Deixar contratos implicitos em templates. Rejeitado porque dificulta validacao e aumenta risco de inconsistencia visual/funcional.

## Decision 9: Integracao NotiCLI como comando externo opcional

**Decision**: Notificacoes serao planejadas por eventos que podem invocar `noticli send` com sender, category, priority, title e message. Segredos e rotas permanecem na configuracao do NotiCLI.

**Rationale**: Alinha com o README do NotiCLI e evita duplicar credenciais no TPanel.

**Alternatives considered**: Implementar notificacoes diretamente no TPanel; armazenar credenciais de provedores no TPanel. Rejeitados por duplicar responsabilidade e ampliar superficie de segredo.

## Decision 10: Nomenclatura de banco orientada ao projeto

**Decision**: Tabelas e colunas planejadas para MySQL usam nomes em ingles e camelCase; constraints e indices usam nomes curtos orientados a consulta.

**Rationale**: Segue a instrucao local de banco de dados. Mantem scripts futuros consistentes e revisaveis.

**Alternatives considered**: snake_case para colunas ou nomes em portugues. Rejeitados por conflito com as regras locais do projeto.
