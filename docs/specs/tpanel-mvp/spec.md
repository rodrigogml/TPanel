# Feature Specification: TPanel MVP

**Feature**: `tpanel-mvp`
**Created**: 2026-07-14
**Status**: Draft

## User Scenarios & Testing

### User Story 1 - View server health at a glance (Priority: P1)

Administrador ou Monitor acessa o painel e entende rapidamente a saude geral do servidor, incluindo status geral, uptime, CPU, memoria, disco, rede, RAID, Docker, alertas e eventos recentes.

**Why this priority**: Sem uma visao consolidada, o TPanel nao cumpre seu papel central de painel de monitoramento.

**Independent Test**: Acessar o painel com usuario autenticado e verificar que a tela inicial apresenta os principais indicadores de saude do servidor, estados visuais claros e alertas relevantes sem exigir navegacao adicional.

**Acceptance Scenarios**:

1. **Given** servidor com metricas disponiveis, **When** usuario abre o dashboard, **Then** o painel mostra status geral, uptime, CPU, memoria, disco, rede, RAID, Docker e alertas.
2. **Given** um indicador esta em estado critico, **When** usuario abre o dashboard, **Then** o painel destaca esse estado de forma visualmente clara.
3. **Given** uma metrica nao esta disponivel, **When** usuario abre o dashboard, **Then** o painel informa indisponibilidade sem quebrar o restante da tela.

---

### User Story 2 - Inspect detailed system metrics (Priority: P1)

Administrador ou Monitor navega por secoes detalhadas para investigar sistema, CPU, memoria, armazenamento, saude dos discos, RAID, rede, processos, logs, seguranca, sensores e agendamentos.

**Why this priority**: O dashboard aponta problemas, mas a operacao precisa de detalhamento para diagnostico.

**Independent Test**: Abrir cada secao de monitoramento e verificar que os dados correspondentes sao apresentados com contexto suficiente para identificar normalidade, alerta ou falha.

**Acceptance Scenarios**:

1. **Given** usuario autenticado, **When** acessa a secao Sistema, **Then** visualiza hostname, distribuicao, kernel, uptime, data/hora e load average.
2. **Given** usuario autenticado, **When** acessa CPU e Memoria, **Then** visualiza uso total, uso por nucleo quando disponivel, frequencia, temperatura quando disponivel, RAM, swap e cache/buffers.
3. **Given** usuario autenticado, **When** acessa Armazenamento e Saude dos Discos, **Then** visualiza sistemas de arquivos, espaco livre, inodes, I/O, SMART, temperatura, horas ligadas, setores realocados e erros criticos quando disponiveis.
4. **Given** usuario autenticado, **When** acessa RAID, Rede, Processos, Logs, Seguranca, Sensores ou Agendamentos, **Then** visualiza dados relevantes de cada area com estados identificaveis.

---

### User Story 3 - Execute approved administrative actions safely (Priority: P1)

Administrador executa acoes administrativas previamente autorizadas, como reinicio controlado de servicos, sem acesso a comandos arbitrarios. Monitor consegue visualizar dados operacionais, mas nao executa acoes criticas.

**Why this priority**: Administracao segura e controlada e diferencial central do TPanel e requisito de seguranca do projeto.

**Independent Test**: Com usuario Administrador, executar uma acao autorizada e verificar resultado e auditoria; com usuario Monitor, tentar acessar a mesma acao e verificar bloqueio claro.

**Acceptance Scenarios**:

1. **Given** Administrador autenticado e acao autorizada disponivel, **When** confirma a execucao, **Then** o sistema executa somente a acao mapeada e apresenta resultado compreensivel.
2. **Given** Administrador informa parametro invalido, **When** tenta executar a acao, **Then** o sistema rejeita antes da execucao e explica o motivo.
3. **Given** Monitor autenticado, **When** tenta acessar ou executar acao administrativa critica, **Then** o sistema nega a operacao e registra a tentativa conforme politica de auditoria.
4. **Given** uma acao excede o tempo limite, **When** a execucao nao conclui a tempo, **Then** o sistema interrompe ou trata a falha e mostra estado final seguro.

---

### User Story 4 - Review audit trail and operational logs (Priority: P1)

Administrador consulta auditoria de acoes administrativas e logs operacionais recentes para entender quem fez o que, quando, com quais parametros validados e qual foi o resultado.

**Why this priority**: Auditoria completa e requisito central de seguranca e suporte operacional.

**Independent Test**: Executar uma acao administrativa, gerar uma falha controlada e verificar que ambas aparecem na auditoria com ator, acao, horario, resultado e detalhes suficientes sem expor segredos.

**Acceptance Scenarios**:

1. **Given** uma acao administrativa foi executada, **When** Administrador abre auditoria, **Then** visualiza ator, acao, horario, parametros validados, resultado e status final.
2. **Given** uma execucao falhou, **When** Administrador consulta auditoria, **Then** visualiza motivo da falha e codigo/status quando disponivel.
3. **Given** logs contem informacoes sensiveis potenciais, **When** sao exibidos no painel, **Then** segredos nao sao mostrados em claro.
4. **Given** Monitor acessa logs operacionais permitidos, **When** navega pela tela, **Then** visualiza informacoes de acompanhamento sem acesso a acoes criticas.

---

### User Story 5 - Manage service and scheduling visibility (Priority: P2)

Administrador visualiza servicos, containers, cron e timers, identifica estados problemáticos e executa reinicio controlado quando autorizado. Monitor acompanha estados sem alterar execucao.

**Why this priority**: Servicos e agendamentos sao pontos frequentes de falha operacional; o MVP precisa torna-los visiveis e operaveis com controle.

**Independent Test**: Abrir telas de servicos e agendamentos, validar estados exibidos, executar um reinicio autorizado como Administrador e confirmar que Monitor nao altera estados.

**Acceptance Scenarios**:

1. **Given** existem servicos e containers, **When** usuario abre a tela de Servicos, **Then** visualiza nome, estado, disponibilidade e acoes permitidas por papel.
2. **Given** existem cron jobs ou timers, **When** usuario abre Agendamentos, **Then** visualiza itens, estado, ultima execucao quando disponivel e proxima execucao quando disponivel.
3. **Given** Administrador reinicia servico autorizado, **When** a acao conclui, **Then** o estado atualizado fica visivel e a acao aparece na auditoria.
4. **Given** Monitor acessa a mesma tela, **When** visualiza servicos e agendamentos, **Then** controles de alteracao ficam indisponiveis ou ausentes.

---

### User Story 6 - Use a premium responsive monitoring interface (Priority: P2)

Administrador e Monitor usam o TPanel em desktop, tablet ou celular, com menu lateral recolhivel, barra superior, pesquisa, alertas, tema claro/escuro e layouts adequados ao tamanho da tela.

**Why this priority**: O portal deve ser util em operacao real e manter aspecto premium desde o MVP.

**Independent Test**: Acessar as principais telas em larguras de desktop, tablet e celular, alternar tema e verificar que a informacao permanece legivel, navegavel e sem sobreposicoes.

**Acceptance Scenarios**:

1. **Given** usuario acessa em desktop, **When** navega pelo painel, **Then** menu lateral, barra superior, cards e tabelas permanecem organizados.
2. **Given** usuario acessa em celular, **When** abre o painel, **Then** menu hamburguer funciona, cards aparecem em coluna unica e acoes sao adequadas ao toque.
3. **Given** usuario alterna tema claro/escuro, **When** navega entre telas, **Then** contraste, estados e legibilidade permanecem adequados.
4. **Given** ha muitos alertas ou dados, **When** a tela renderiza, **Then** conteudo nao se sobrepoe e componentes mantem dimensoes estaveis.

---

### User Story 7 - Preserve safe configuration for public repository use (Priority: P2)

Administrador instala ou prepara o TPanel sem colocar segredos no repositorio. Arquivos reais de configuracao sensivel ficam fora do Git e modelos versionados documentam as chaves necessarias.

**Why this priority**: O projeto sera publicado no GitHub e precisa ser seguro desde a estrutura inicial.

**Independent Test**: Revisar os arquivos versionados e verificar que nao ha segredos reais, que arquivos sensiveis reais sao ignorados e que seus modelos indicam como configurar o ambiente.

**Acceptance Scenarios**:

1. **Given** projeto contem configuracoes sensiveis, **When** usuario revisa o repositorio, **Then** encontra modelos versionados e nao encontra segredos reais.
2. **Given** novo ambiente precisa ser configurado, **When** Administrador consulta os modelos, **Then** entende quais chaves preencher e onde colocar os arquivos reais.
3. **Given** uma integracao externa usa credenciais, **When** documentada no projeto, **Then** o TPanel referencia a configuracao necessaria sem duplicar segredos.

---

### Edge Cases

- O que acontece quando uma metrica do sistema esta indisponivel, ausente no host ou sem permissao de leitura?
- Como o painel se comporta quando uma acao autorizada falha, demora demais ou retorna saida inesperada?
- O que acontece quando RAID, SMART, sensores ou Docker nao estao presentes no servidor?
- Como o sistema diferencia erro critico, alerta e informacao normal sem gerar excesso de alarmes?
- Como a auditoria trata tentativas negadas por papel insuficiente?
- Como o painel evita expor segredos em logs, mensagens, notificacoes ou diagnosticos?
- O que acontece quando o volume de logs ou metricas e grande demais para uma tela?
- Como o sistema apresenta dados antigos, incompletos ou impossiveis de coletar no momento?

## Requirements

### Functional Requirements

- **FR-001**: System MUST provide a dashboard with overall health, uptime, CPU, memory, disk, RAID, network, Docker, alert and recent event status.
- **FR-002**: System MUST show clear visual states for normal, warning, critical and unavailable data.
- **FR-003**: System MUST provide detailed monitoring views for system identity, operating system, kernel, uptime, date/time and load average.
- **FR-004**: System MUST provide CPU monitoring for total usage, per-core usage when available, frequency, temperature when available and top consuming processes.
- **FR-005**: System MUST provide memory monitoring for RAM, swap, cache and buffers.
- **FR-006**: System MUST provide storage monitoring for filesystems, free space, I/O, inodes and capacity states.
- **FR-007**: System MUST provide disk health monitoring for SMART data, temperature, powered-on hours, reallocated sectors and critical errors when available.
- **FR-008**: System MUST provide RAID monitoring for state, synchronization and degraded disks when available.
- **FR-009**: System MUST provide network monitoring for interfaces, IPs, traffic, errors, latency, gateway and DNS status when available.
- **FR-010**: System MUST provide service monitoring for system services, Docker and containers, including state and allowed actions per user role.
- **FR-011**: System MUST provide process monitoring for critical processes, CPU usage and memory usage.
- **FR-012**: System MUST provide log views for journal, syslog and recent errors with filtering or navigation sufficient for operational review.
- **FR-013**: System MUST provide security monitoring for SSH login activity, failed attempts, firewall state and available update indicators.
- **FR-014**: System MUST provide sensor monitoring for temperatures, fans and power data when available.
- **FR-015**: System MUST provide scheduling visibility for cron and system timers, including status and execution timing when available.
- **FR-016**: System MUST restrict administrative actions to authenticated Administrators.
- **FR-017**: System MUST prevent Monitor users from executing critical administrative actions while allowing Monitor users to acknowledge alerts and comment on events without changing server state.
- **FR-018**: System MUST reject administrative action requests that are not explicitly authorized.
- **FR-019**: System MUST validate administrative action parameters before execution.
- **FR-020**: System MUST enforce timeouts for administrative action execution and report timeout outcomes clearly.
- **FR-021**: System MUST create an audit record for every administrative action attempt, including denied, failed, timed out and successful attempts.
- **FR-022**: Audit records MUST include actor, action, timestamp, validated parameters, result and failure reason when applicable.
- **FR-023**: System MUST avoid exposing secrets in dashboards, logs, audit records, diagnostics and notification content.
- **FR-024**: System MUST support light and dark themes.
- **FR-025**: System MUST provide responsive navigation with desktop sidebar behavior and mobile hamburger behavior.
- **FR-026**: System MUST keep dashboard cards, tables, alerts and action controls usable on desktop, tablet and mobile.
- **FR-027**: System MUST keep sensitive runtime configuration out of version control.
- **FR-028**: System MUST provide versioned model files for required sensitive or environment-specific configuration.
- **FR-029**: System MUST support configurable metric retention with an initial default of 90 days for collected metrics.
- **FR-030-INFRA-SCHED**: System MUST declare whether any periodic collection or refresh behavior is manual, automatic, or scheduled before implementation of historical metrics or auto-refresh.
- **FR-031-INFRA-BACKUP**: System MUST document backup and restore expectations for audit and metric data before storing operational history considered important for troubleshooting.
- **FR-032-INFRA-IDEMP**: Administrative action flows MUST prevent duplicate execution when the same confirmed action is submitted repeatedly by retry or accidental double click.
- **FR-033**: System SHOULD prepare notification events with sender, category, priority, title and message fields for future NotiCLI integration.
- **FR-034**: System SHOULD record notification invocation results and exit status when notification integration is enabled.

### Key Entities

- **User Role**: Represents whether an authenticated user operates as Administrador or Monitor, determining visible actions, administrative permissions and non-server-changing event actions.
- **Server Health Summary**: Consolidated current state of the server, including indicators, severity and freshness.
- **Metric Reading**: A point-in-time observation of CPU, memory, disk, RAID, network, service, process, sensor or scheduling state.
- **Alert**: A condition requiring attention, with severity, source, message, timestamp and resolution state when applicable.
- **Administrative Action**: A controlled operation that may change server or service state, available only when authorized.
- **Audit Record**: Immutable record of an administrative attempt or security-relevant event.
- **Configuration Model**: Versioned example of required configuration fields without real secrets.
- **Notification Event**: A future outbound alert payload containing sender, category, priority, title, message and delivery result metadata when applicable.

## Success Criteria

### Measurable Outcomes

- **SC-001**: A user can identify the overall server health state from the dashboard within 30 seconds in a usability review.
- **SC-002**: 95% of dashboard refreshes show visible current status or explicit unavailable-state feedback within 2 seconds on the target local server.
- **SC-003**: 100% of administrative action attempts produce an audit record with actor, action, timestamp and result.
- **SC-004**: 100% of unauthorized or unmapped administrative action attempts are rejected before execution.
- **SC-005**: 100% of administrative actions with invalid parameters are rejected before execution.
- **SC-006**: No real secrets are present in versioned project files during repository review.
- **SC-007**: The main dashboard and service/log views remain usable without content overlap at desktop, tablet and mobile viewport sizes.
- **SC-008**: A Monitor user can inspect dashboard, metrics and permitted logs, acknowledge alerts and comment on events without being able to execute critical administrative actions.
- **SC-009**: Missing optional capabilities such as Docker, SMART, RAID or sensors are shown as unavailable or not installed without breaking other monitoring areas.
- **SC-010**: Administrators can find the latest successful, failed or denied administrative action in the audit view in under 60 seconds.
