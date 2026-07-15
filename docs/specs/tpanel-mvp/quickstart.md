# Quickstart: TPanel MVP

Cenarios de teste que validam a implementacao end-to-end. Estes cenarios assumem um ambiente local de teste com Apache autenticando usuarios, uma instancia MySQL configurada, usuario Administrador e usuario Monitor.

## Scenario 1: Dashboard health overview

1. Autenticar como Administrador.
2. Abrir a tela inicial do TPanel.
3. Verificar status geral, uptime, CPU, memoria, disco, RAID, rede, Docker e alertas.
4. **Expected**: a saude geral do servidor fica clara em ate 30 segundos, com estados NORMAL/WARNING/CRITICAL/UNAVAILABLE visiveis.

## Scenario 2: Optional capability unavailable

1. Usar um servidor sem Docker, RAID ou sensores disponiveis.
2. Autenticar como Monitor.
3. Abrir dashboard e secoes detalhadas.
4. **Expected**: areas opcionais aparecem como indisponiveis ou nao instaladas sem quebrar as demais telas.

## Scenario 3: Administrator executes authorized action

1. Autenticar como Administrador.
2. Abrir Servicos.
3. Escolher uma acao autorizada de reinicio controlado para servico permitido.
4. Confirmar a acao uma unica vez.
5. Abrir Auditoria.
6. **Expected**: acao executada somente pelo catalogo autorizado; resultado visivel; auditoria contem ator, acao, parametros validados, horario e resultado.

## Scenario 4: Monitor cannot execute administrative action

1. Autenticar como Monitor.
2. Abrir Servicos.
3. Tentar acessar ou enviar uma acao administrativa critica.
4. Abrir Auditoria como Administrador.
5. **Expected**: a acao e negada antes da execucao; tentativa e registrada; nenhum estado do servidor e alterado.

## Scenario 5: Monitor acknowledges alert and comments event

1. Autenticar como Monitor.
2. Abrir Alertas.
3. Reconhecer um alerta aberto e adicionar comentario operacional.
4. Autenticar como Administrador.
5. Abrir o mesmo alerta e a auditoria.
6. **Expected**: alerta mostra reconhecimento e comentario; auditoria registra acoes do Monitor; nenhum comando administrativo foi executado.

## Scenario 6: Invalid command parameter

1. Autenticar como Administrador.
2. Submeter uma acao autorizada com parametro invalido ou fora do esquema permitido.
3. **Expected**: parametro rejeitado antes da execucao; usuario recebe mensagem clara; auditoria registra falha de validacao.

## Scenario 7: Command timeout

1. Autenticar como Administrador.
2. Executar acao de teste configurada para exceder timeout.
3. Abrir resultado e auditoria.
4. **Expected**: sistema reporta timeout com estado final seguro; auditoria registra TIMED_OUT e motivo sanitizado.

## Scenario 8: Configuration safety review

1. Listar arquivos versionados do repositorio.
2. Procurar credenciais reais, tokens, senhas e webhooks.
3. Conferir modelos `.model` para configuracoes sensiveis.
4. **Expected**: nenhum segredo real esta versionado; cada configuracao sensivel possui modelo documentado.

## Scenario 9: Notification event when enabled

1. Configurar NotiCLI em ambiente local sem duplicar segredos no TPanel.
2. Gerar alerta de teste com prioridade HIGH.
3. Disparar envio de notificacao.
4. Verificar evento de notificacao e auditoria.
5. **Expected**: NotiCLI recebe sender, category, priority, title e message; TPanel registra status e exit code sem expor segredo.

## Scenario 10: Roundtrip End-to-End

1. Subir o TPanel em ambiente local de teste com Apache e MySQL.
2. Autenticar como Administrador.
3. Abrir o dashboard real pelo navegador.
4. Capturar o payload renderizado ou usado pela UI para o resumo do dashboard.
5. Comparar nomes, tipos, enums e estados contra `contracts/ui-contracts.md`.
6. Executar uma acao administrativa autorizada e comparar request/result contra `contracts/command-executor.md`.
7. **Expected**: zero divergencia entre payload real, contrato declarado, UI renderizada e auditoria gravada.

## Scenario 11: Responsive viewport validation

1. Abrir o dashboard em navegador real ou ferramenta headless.
2. Validar a matriz em [responsive-validation.md](responsive-validation.md).
3. Alternar tema claro/escuro pelo controle da topbar.
4. No celular, abrir e fechar o menu hamburguer.
5. **Expected**: sidebar, topbar, cards, tabelas, badges e botoes permanecem legiveis, sem sobreposicao, em todos os viewports definidos.
