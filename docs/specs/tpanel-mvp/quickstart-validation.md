# Quickstart Validation

Data: 2026-07-15

## Environment

- Runtime usado: PHP built-in server em `127.0.0.1:8087`
- Apache autenticado: executado localmente com `Host: tpanel.rodrigogml.eng.br`
- Navegador/headless para screenshots: Chromium 150.0.7871.114 em Debian 13
- MySQL schema validation: executado separadamente em `tpanel_validation`
- NotiCLI local: `config/noticli.php` ausente; scenario 9 registrado como SKIPPED justificado neste ambiente
- Producao local HTTP/HTTPS: `/srv/apache/vhosts/tpanel.rodrigogml.eng.br/www/public`
- TLS local: certificado self-signed valido ate 2036-07-12 em `/etc/ssl/central/tpanel.rodrigogml.eng.br/`

## Automated Evidence

Comando:

```sh
composer check
```

Resultado:

```text
OK (100 tests, 525 assertions)
```

Comando:

```sh
curl -fsS http://127.0.0.1:8087/
```

Evidencias observadas no HTML:

- dashboard com `Saude geral`, `Uptime`, CPU, memoria, disco, RAID, rede, Docker e alertas;
- secoes detalhadas de Sistema, CPU/Memoria, Armazenamento/Discos, RAID/Rede, Processos/Logs, Seguranca/Sensores e Agendamentos;
- acoes `Service status` e `Container status` renderizadas para Administrador;
- resultados `SUCCESS`, `DENIED`, `FAILED` e `TIMED_OUT` renderizados;
- Auditoria, reconhecimento de alertas e comentario operacional renderizados.

Comando:

```sh
curl -fsS http://127.0.0.1:8087/assets/css/tpanel.css
```

Evidencias observadas no CSS:

- tema claro via `[data-theme="light"]`;
- layout de sidebar;
- grids `metric-grid` e `detail-grid`;
- media query mobile em `@media (max-width: 820px)`.

Comando:

```sh
chromium --headless --disable-gpu --no-sandbox --window-size=<viewport> --screenshot=var/validation/tpanel-<viewport>.png http://127.0.0.1:8087/
```

Screenshots gerados:

- `var/validation/tpanel-1440x900.png`
- `var/validation/tpanel-1280x720.png`
- `var/validation/tpanel-820x1180.png`
- `var/validation/tpanel-390x844.png`
- `var/validation/tpanel-320x740.png`

Evidencias observadas nos screenshots:

- desktop e laptop compacto mantem topbar em uma linha, cards em grid e paineis sem sobreposicao;
- tablet empilha os blocos principais sem comprimir texto operacional;
- celular 390px preserva busca, tema, alerta e menu hamburguer sem quebra visual;
- celular estreito 320px usa busca compacta por icone, sem rolagem horizontal de pagina;
- tabelas continuam isoladas em `table-wrap` para rolagem horizontal local quando necessario.

Comando:

```sh
curl -fsS -X POST http://127.0.0.1:8087/ \
  -d 'requestId=http-action-1' \
  -d 'actionKey=service.status' \
  -d 'parameters[serviceName]=apache2.service' \
  -d 'confirmationAccepted=1'
```

Evidencias observadas no HTML:

- `data-result-status="FAILED"` renderizado como resultado real do POST;
- `auditoria #1` renderizada;
- `requestId http-action-1` preservado no feedback.

Observacao: o resultado foi `FAILED` porque o wrapper autorizado real em `/opt/tpanel/scripts/system/service-status` ainda nao esta instalado neste ambiente de desenvolvimento; isso valida o roundtrip HTTP, a captura do resultado e a auditoria sem executar comando arbitrario.

Comando:

```sh
curl -fsS -X POST http://127.0.0.1:8087/ \
  -d 'requestId=http-ack-1' \
  -d 'alertId=41' \
  -d 'acknowledgementNote=validado'
```

Evidencias observadas no HTML:

- `data-result-status="SUCCESS"` renderizado;
- `Alert acknowledged with status ACKNOWLEDGED.` renderizado;
- `auditoria #1` renderizada;
- `requestId http-ack-1` preservado no feedback.

Comando:

```sh
curl -u rodrigo:<redacted> -H 'Host: tpanel.rodrigogml.eng.br' \
  -X POST http://127.0.0.1/ \
  -d 'requestId=apache-prod-status-1' \
  -d 'actionKey=service.status' \
  -d 'parameters[serviceName]=apache2.service' \
  -d 'confirmationAccepted=1'
```

Evidencias observadas no HTML via Apache:

- `data-result-status="SUCCESS"` renderizado;
- `LoadState=loaded` e `ActiveState=active` retornados pelo wrapper `/opt/tpanel/scripts/system/service-status`;
- `auditoria #1` renderizada;
- `requestId apache-prod-status-1` preservado no feedback.

Comando:

```sh
curl -u rodrigo:<redacted> --resolve tpanel.rodrigogml.eng.br:443:127.0.0.1 \
  https://tpanel.rodrigogml.eng.br/
```

Evidencias observadas no HTML via Apache HTTPS:

- HTTP status `200`;
- `Turin Panel` renderizado;
- usuario `rodrigo` renderizado como `ADMINISTRATOR`.

## Scenario Status

| Scenario | Status | Evidence |
|----------|--------|----------|
| 1 Dashboard health overview | Executed locally | HTML renderiza os cards exigidos, testes de integracao preservam o contrato e screenshots validam layout. |
| 2 Optional capability unavailable | Executed locally | HTML renderiza `UNAVAILABLE` para RAID/Docker/sensores e screenshots validam layout. |
| 3 Administrator executes authorized action | Executed via Apache | `ApplicationPostRoundtripTest` cobre SUCCESS com catalogo local controlado; Apache real confirmou `service.status` com sudo/wrapper e auditoria. |
| 4 Monitor cannot execute administrative action | Covered by tests | `ApplicationPostRoundtripTest` valida ausencia de controles para Monitor e POST negado antes da execucao com auditoria; producao atual mantem apenas o usuario administrador `rodrigo`. |
| 5 Monitor acknowledges alert and comments event | Executed locally | `ApplicationPostRoundtripTest` cobre reconhecimento e comentario; sonda HTTP real confirmou reconhecimento com SUCCESS e auditoria. |
| 6 Invalid command parameter | Covered by tests | `CommandParameterValidatorTest` cobre rejeicao antes de execucao. |
| 7 Command timeout | Covered by tests | `AuthorizedCommandExecutorTest` cobre timeout. |
| 8 Configuration safety review | Executed locally | `.model`, `.gitignore` e testes/sondas validam que segredos ficam fora do versionamento e POST usa catalogo autorizado. |
| 9 Notification event when enabled | SKIPPED locally, covered by simulated tests | `config/noticli.php` ausente neste ambiente; `NotificationServiceTest` cobre NotiCLI simulado, SENT/FAILED/SKIPPED e sanitizacao. |
| 10 Roundtrip End-to-End | Executed locally | `ApplicationPostRoundtripTest` executa POST de acao, valida feedback, requestId, status e campos do dashboard contra o contrato UI. |
| 11 Responsive viewport validation | Executed | Chromium gerou screenshots para 1440x900, 1280x720, 820x1180, 390x844 e 320x740; ajuste mobile removeu rolagem horizontal de pagina. |

## Remaining Production-Like Validation

- DNS publico de `tpanel.rodrigogml.eng.br` via Cloudflare.
