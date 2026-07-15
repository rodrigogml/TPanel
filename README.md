# TPanel

TPanel, ou Turin Panel, e um portal PHP monolitico para monitoramento e administracao controlada de servidores Linux. O alvo inicial e Debian 13 com Apache, MySQL, sudo restrito e coletores locais para sistema, CPU, memoria, armazenamento, rede, servicos, Docker, logs, seguranca, sensores e agendamentos.

## Stack

- PHP 8.4+ com Composer, sem framework obrigatório.
- Apache como fronteira de autenticacao.
- MySQL para usuarios, auditoria, metricas, alertas, comentarios e notificacoes.
- Shell wrappers revisados em `scripts/system/` para acoes administrativas autorizadas.
- NotiCLI opcional para notificacoes.

## Estrutura

```text
config/        modelos de configuracao e arquivos locais ignorados
database/      DDL, seeds e updates MySQL
docs/          especificacao, plano, operacao e validacoes
public/        entrypoint web e assets
scripts/       wrappers de sistema e sudoers model
src/           camadas PHP de dominio, servicos, seguranca e repositorios
templates/     estrutura reservada para templates
tests/         testes unitarios e integracao
```

## Configuracao Segura

Arquivos reais de configuracao ficam fora do Git pelo `.gitignore`.

Modelos versionados:

- `config/app.php.model`
- `config/database.php.model`
- `config/commands.php.model`
- `config/monitoring.php.model`
- `config/noticli.php.model`

Arquivos locais esperados:

- `config/app.php`
- `config/database.php`
- `config/commands.php`
- `config/monitoring.php`
- `config/noticli.php`

Nao versionar senhas, tokens, webhooks, certificados privados ou configuracoes reais de provedores externos. Rotas e segredos de notificacao ficam na configuracao propria do NotiCLI.

## Instalacao Local

Instale dependencias PHP:

```bash
composer install
```

Prepare os arquivos locais copiando os modelos necessarios e preenchendo valores do ambiente:

```bash
cp config/app.php.model config/app.php
cp config/database.php.model config/database.php
cp config/commands.php.model config/commands.php
cp config/monitoring.php.model config/monitoring.php
cp config/noticli.php.model config/noticli.php
```

Os arquivos acima sao ignorados pelo Git.

## MySQL

O schema alvo chama `tpanel`. Em uma instalacao nova, aplique:

```bash
sudo mysql < database/init/01-ddl.sql
sudo mysql tpanel < database/init/02-seed.sql
```

Updates incrementais ficam em `database/update/` e devem ser aplicados em bancos existentes conforme ordem de arquivo. Veja tambem [backup-restore.md](docs/operations/backup-restore.md).

## Apache, Usuario Linux e Sudoers

Apache autentica o usuario e o PHP usa essa identidade. O TPanel nao implementa autenticacao paralela no MVP.

Consulte [debian13-prerequisites.md](docs/operations/debian13-prerequisites.md) para:

- pacotes minimos Debian 13;
- criacao do usuario/grupo `tpanel`;
- layout `/opt/tpanel`;
- ownership e permissoes;
- instalacao dos wrappers;
- modelo sudoers restrito.

O modelo sudoers versionado fica em:

```text
scripts/sudoers/tpanel.model
```

Valide qualquer arquivo instalado com:

```bash
sudo visudo -cf /etc/sudoers.d/tpanel
```

## Instalacao em Producao Local

Instalacao aplicada neste host:

- aplicacao: `/srv/apache/vhosts/tpanel.rodrigogml.eng.br/www`
- document root: `/srv/apache/vhosts/tpanel.rodrigogml.eng.br/www/public`
- wrappers autorizados: `/opt/tpanel/scripts/system`
- sudoers instalado: `/etc/sudoers.d/tpanel`
- vhost HTTP: `/etc/apache2/sites-available/tpanel.rodrigogml.eng.br.conf`
- Basic Auth: `/etc/apache2/tpanel.htpasswd`
- credenciais iniciais root-only: `/root/tpanel-credentials.txt`

O vhost HTTP exige autenticacao Apache. Usuarios iniciais:

- `tpanel-admin`, mapeado como `ADMINISTRATOR`
- `tpanel-monitor`, mapeado como `MONITOR`

Como o host fica atras de proxy Cloudflare, o HTTPS local usa certificado self-signed de 10 anos em `/etc/ssl/central/tpanel.rodrigogml.eng.br/`.

## Desenvolvimento

Servidor local de teste, sem substituir Apache em producao:

```bash
php -S 127.0.0.1:8087 -t public
```

Validacao completa:

```bash
composer check
```

Esse comando executa:

- `composer validate --strict`
- lint PHP em `public`, `src` e `tests`
- PHPUnit

## Quickstart e Validacao

Cenarios principais: [quickstart.md](docs/specs/tpanel-mvp/quickstart.md)

Status de validacao local atual: [quickstart-validation.md](docs/specs/tpanel-mvp/quickstart-validation.md)

Matriz responsiva: [responsive-validation.md](docs/specs/tpanel-mvp/responsive-validation.md)

## Estado Atual

O MVP ja possui fundacao PHP, schema MySQL, seguranca por papel, executor autorizado, idempotencia, coletores, dashboard inicial, telas operacionais, eventos de notificacao, suite automatizada e instalacao Apache HTTP/HTTPS local para `tpanel.rodrigogml.eng.br`.
