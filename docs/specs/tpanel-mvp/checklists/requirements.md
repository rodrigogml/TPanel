# Requirements Checklist: TPanel MVP

**Purpose**: Validar qualidade, clareza, completude, mensurabilidade e rastreabilidade dos requisitos antes de decompor o MVP em tarefas.
**Created**: 2026-07-14
**Feature**: [spec.md](../spec.md)

## Completude de Requisitos

- [x] CHK001 - Todas as jornadas principais do MVP estao representadas por user stories independentes? [Completude, Spec §User Stories 1-7] {auto}
- [x] CHK002 - Os requisitos funcionais cobrem dashboard, metricas detalhadas, acoes administrativas, auditoria, servicos/agendamentos, UI responsiva e configuracao segura? [Completude, Spec §FR-001..FR-034] {auto}
- [x] CHK003 - Os requisitos nao funcionais centrais de seguranca, performance, auditoria, responsividade e configuracao segura estao documentados? [Completude, Spec §FR-016..FR-032, Spec §SC-002..SC-008, Plan §Security Plan, Plan §UI Plan] {auto}
- [x] CHK004 - O fora-de-escopo implicito da fase atual esta refletido no plano, especialmente ausencia de API REST publica e escopo single-server? [Completude, Plan §Technical Context, Plan §Summary] {auto}
- [x] CHK005 - O conjunto inicial de acoes administrativas autorizadas esta enumerado com granularidade suficiente para virar backlog sem nova decisao de escopo? [Completude, Spec §User Story 3, Spec §FR-018..FR-020, Contracts §command-executor.md, Config §commands.php.model] {auto}
- [x] CHK006 - A politica de coleta historica define frequencia inicial, freshness e agregacoes, alem da retencao de 90 dias? [Completude, Spec §FR-029..FR-030, Plan §Data and Persistence Plan] {auto}
- [x] CHK007 - As expectativas de backup/restore de auditoria e metricas estao quantificadas com periodicidade, retencao e criterio de validacao? [Completude, Spec §FR-031, Plan §Data and Persistence Plan, Operations §backup-restore.md] {auto}

## Clareza e Testabilidade

- [x] CHK008 - Cada requisito funcional usa linguagem imperativa testavel com MUST ou SHOULD? [Clareza, Spec §Functional Requirements] {auto}
- [x] CHK009 - As permissoes do Monitor estao claras: pode reconhecer alertas e comentar eventos sem alterar estado do servidor? [Clareza, Spec §FR-017, Spec §SC-008] {auto}
- [x] CHK010 - A retencao de metricas esta clara como configuravel com padrao inicial de 90 dias? [Clareza, Spec §FR-029, Plan §Data and Persistence Plan] {auto}
- [x] CHK011 - O tratamento de capacidades opcionais ausentes esta especificado sem depender de implementacao concreta? [Clareza, Spec §Edge Cases, Spec §SC-009, Plan §UI Plan] {auto}
- [x] CHK012 - Os thresholds que diferenciam NORMAL, WARNING, CRITICAL e UNAVAILABLE estao definidos para as metricas criticas do MVP? [Clareza, Spec §FR-002, Contracts §ui-contracts.md, Plan §Severity Threshold Policy, Config §monitoring.php.model] {auto}
- [x] CHK013 - O requisito de "logs operacionais permitidos" define que Monitor consulta apenas resumos sanitizados de journal/syslog e seguranca, enquanto auth raw, auditoria e saidas de comando ficam restritos ao Administrador? [Clareza, Spec §User Story 4, Spec §FR-012, Spec §FR-017] {auto}

## Consistencia e Alinhamento

- [x] CHK014 - A spec e o plano estao alinhados com a constituicao sobre deny-by-default, catalogo autorizado e ausencia de comandos arbitrarios? [Consistencia, Spec §FR-016..FR-020, Plan §Constitution Check, Constitution §I] {auto}
- [x] CHK015 - A divisao de camadas no plano preserva UI, controllers, services, command executor e system scripts conforme a constituicao? [Consistencia, Plan §Project Structure, Constitution §II] {auto}
- [x] CHK016 - O modelo de dados e consistente com a decisao de MySQL e com auditoria persistente? [Consistencia, Plan §Data and Persistence Plan, Data Model §AuditRecord] {auto}
- [x] CHK017 - A integracao NotiCLI mantem segredos fora do TPanel e registra status/exit code como definido no briefing e no plano? [Consistencia, Spec §FR-033..FR-034, Plan §Phase 0 - Research Summary, Contracts §notification-events.md] {auto}
- [x] CHK018 - A decisao de manter "sem framework obrigatorio" e suficiente para orientar tarefas iniciais, ou o dono do projeto prefere definir um microframework/Composer antes da implementacao? [Assumption, Plan §Project Structure, ADR-001] {humano}

## Mensurabilidade e Success Criteria

- [x] CHK019 - Os success criteria incluem metas objetivas para entendimento do dashboard, tempo de refresh, auditoria, rejeicao de acoes indevidas, segredos e responsividade? [Mensurabilidade, Spec §SC-001..SC-010] {auto}
- [x] CHK020 - Os criterios de auditoria sao verificaveis por contagem ou presenca obrigatoria de campos? [Mensurabilidade, Spec §SC-003, Spec §FR-021..FR-022] {auto}
- [x] CHK021 - A performance inicial do dashboard tem threshold mensuravel do ponto de vista do usuario? [Mensurabilidade, Spec §SC-002, Plan §Technical Context] {auto}
- [x] CHK022 - O criterio de "sem sobreposicao" em responsividade define viewports minimos ou matriz de dispositivos para validacao? [Mensurabilidade, Spec §SC-007, Quickstart §Scenario 11, Responsive Matrix §responsive-validation.md] {auto}

## Cobertura de Cenarios e Edge Cases

- [x] CHK023 - Happy paths essenciais estao descritos para dashboard, metricas, acoes administrativas, auditoria, servicos/agendamentos, UI e configuracao segura? [Cobertura, Spec §User Stories 1-7] {auto}
- [x] CHK024 - Error paths de parametro invalido, timeout, permissao negada e dependencia opcional ausente estao cobertos? [Cobertura, Spec §User Story 3, Spec §Edge Cases, Quickstart §Scenarios 2, 4, 6, 7] {auto}
- [x] CHK025 - Edge cases de dados indisponiveis, fontes ausentes, excesso de logs/metricas e dados antigos estao documentados? [Cobertura, Spec §Edge Cases] {auto}
- [x] CHK026 - O comportamento para concorrencia ou dupla submissao de acoes administrativas esta detalhado com janela inicial de 15 minutos, fingerprint do request, retorno de resultado anterior e negacao segura para conflito/em andamento? [Cobertura, Spec §FR-032, Contracts §command-executor.md] {auto}

## Dependencias e Premissas

- [x] CHK027 - Dependencias tecnicas primarias estao documentadas no plano, incluindo Apache, PHP, MySQL, sudo, systemd, Docker opcional, SMART/sensores opcionais e NotiCLI opcional? [Dependencias, Plan §Technical Context] {auto}
- [x] CHK028 - A ausencia de Docker, RAID, SMART ou sensores tem fallback de requisito documentado? [Dependencias, Spec §SC-009, Plan §UI Plan, Quickstart §Scenario 2] {auto}
- [x] CHK029 - A configuracao segura para Git esta coberta por requisitos e plano de estrutura `.model`? [Dependencias, Spec §FR-027..FR-028, Plan §Project Structure] {auto}
- [x] CHK030 - O plano define pre-requisitos minimos de pacotes do Debian 13 e permissoes Linux para instalacao inicial? [Dependencias, Plan §Technical Context, Plan §Security Plan, Operations §debian13-prerequisites.md] {auto}

## Rastreabilidade

- [x] CHK031 - As user stories possuem ligacao clara com grupos de requisitos funcionais correspondentes? [Traceability, Spec §User Stories 1-7, Spec §FR-001..FR-034] {auto}
- [x] CHK032 - O plano referencia os artefatos derivados da spec: research, data model, contracts e quickstart? [Traceability, Plan §Phase 1 - Design Summary] {auto}
- [x] CHK033 - Os contratos cobrem as principais fronteiras planejadas: UI, executor de comandos e notificacoes? [Traceability, Plan §Convenções de Borda, Contracts §ui-contracts.md, Contracts §command-executor.md, Contracts §notification-events.md] {auto}
- [x] CHK034 - Existem tasks rastreaveis para mapear requisitos a tarefas executaveis? [Traceability, docs/specs/tpanel-mvp/tasks.md] {auto}

## Notes

- Itens `{auto}` foram resolvidos contra `spec.md`, `plan.md`, contratos, quickstart e modelo de dados.
- Itens `{humano}` aguardam decisao do dono do produto.
- Itens `[Gap]` devem virar clarificacao ou tarefa explicita antes da implementacao.
- Este checklist valida qualidade de requisitos; nao valida codigo nem comportamento executado.
