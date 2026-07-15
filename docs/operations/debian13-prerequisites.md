# Debian 13 Prerequisites and Linux Permissions

This document defines the initial operating-system prerequisites for running TPanel on Debian 13 with Apache, PHP, MySQL and restricted administrative command execution.

It is documentation only. Do not run commands from this file blindly on production hosts; review paths, package names and local policy first.

## Minimum Packages

### Required runtime packages

```bash
sudo apt update
sudo apt install \
  apache2 \
  libapache2-mod-php \
  php \
  php-cli \
  php-mysql \
  php-json \
  php-mbstring \
  php-xml \
  php-curl \
  default-mysql-client \
  sudo \
  procps \
  iproute2 \
  iputils-ping \
  dnsutils \
  util-linux \
  acl
```

### Required development/deployment packages

```bash
sudo apt install \
  composer \
  git
```

### Optional monitoring packages

Install only when the server uses the related capability.

```bash
sudo apt install \
  smartmontools \
  lm-sensors \
  mdadm \
  cron
```

Docker monitoring requires a local Docker client and access policy defined by the administrator. Do not add `www-data` to the `docker` group for TPanel, because that would grant broad container control outside the approved command catalog.

## Linux Accounts and Groups

TPanel uses a dedicated Linux user and group for owned scripts and approved execution boundaries.

```bash
sudo groupadd --system tpanel
sudo useradd \
  --system \
  --gid tpanel \
  --home-dir /opt/tpanel \
  --shell /usr/sbin/nologin \
  tpanel
```

Expected account model:

| Account | Purpose | Interactive shell | Notes |
|---------|---------|-------------------|-------|
| `www-data` | Apache runtime user | No direct TPanel shell needed | May invoke only approved sudo rules |
| `tpanel` | Owner/effective user for TPanel scripts | `/usr/sbin/nologin` | Owns scripts and runtime directories |
| Human admin | Host administration | Local policy | Installs packages and reviews sudoers |

## Directory Layout and Ownership

Recommended production layout:

```text
/opt/tpanel/
|-- current/                 # deployed application release
|-- shared/
|   |-- config/              # real local config files, not in Git
|   |-- logs/                # runtime logs
|   `-- tmp/                 # runtime temp/cache
`-- scripts/
    `-- system/              # approved command wrappers
```

Recommended ownership:

```bash
sudo mkdir -p /opt/tpanel/current /opt/tpanel/shared/config /opt/tpanel/shared/logs /opt/tpanel/shared/tmp /opt/tpanel/scripts/system
sudo chown -R root:tpanel /opt/tpanel
sudo chown -R tpanel:tpanel /opt/tpanel/scripts
sudo chmod 0750 /opt/tpanel
sudo chmod 0750 /opt/tpanel/shared
sudo chmod 0750 /opt/tpanel/shared/config
sudo chmod 0750 /opt/tpanel/scripts
sudo chmod 0750 /opt/tpanel/scripts/system
sudo chmod 0640 /opt/tpanel/shared/config/*.php
```

Repository scripts under `scripts/system/` are source artifacts. Production scripts under `/opt/tpanel/scripts/system/` must be reviewed, deployed, owned by `tpanel:tpanel`, and executable only when they are intended command wrappers.

Initial source wrappers:

| Wrapper | Purpose |
|---------|---------|
| `service-status` | Read selected systemd service state |
| `service-restart` | Restart selected systemd service |
| `service-reload` | Reload selected systemd service |
| `docker-container-status` | Read selected Docker container state |
| `docker-container-restart` | Restart selected Docker container |
| `timer-status` | Read selected systemd timer state |
| `timer-restart` | Restart selected systemd timer |
| `collector-once` | Validate collector category and reserve collector entrypoint |
| `noticli-test` | Send a configured NotiCLI test notification |

## Apache Relationship

Apache authenticates users. PHP reads the authenticated identity from the configured server variable, initially `REMOTE_USER`.

Rules:

- TPanel must not implement a parallel authentication source in the MVP.
- Apache `www-data` must not own TPanel scripts.
- Apache `www-data` must not be granted shell access.
- Apache `www-data` must not be added to broad privileged groups such as `docker`, `adm`, `sudo` or `root`.
- Apache may execute only specifically allowed command wrappers through sudo.

## Sudoers Model

Create a dedicated sudoers drop-in from the reviewed model after checking the target host:

```text
/etc/sudoers.d/tpanel
```

Source model in the repository:

```text
scripts/sudoers/tpanel.model
```

The installed drop-in must be validated with `visudo -c`.

Approved wrappers in the initial catalog:

```sudoers
Cmnd_Alias TPANEL_SERVICE_STATUS = /opt/tpanel/scripts/system/service-status *
Cmnd_Alias TPANEL_SERVICE_RESTART = /opt/tpanel/scripts/system/service-restart *
Cmnd_Alias TPANEL_SERVICE_RELOAD = /opt/tpanel/scripts/system/service-reload *
Cmnd_Alias TPANEL_DOCKER_CONTAINER_STATUS = /opt/tpanel/scripts/system/docker-container-status *
Cmnd_Alias TPANEL_DOCKER_CONTAINER_RESTART = /opt/tpanel/scripts/system/docker-container-restart *
Cmnd_Alias TPANEL_TIMER_STATUS = /opt/tpanel/scripts/system/timer-status *
Cmnd_Alias TPANEL_TIMER_RESTART = /opt/tpanel/scripts/system/timer-restart *
Cmnd_Alias TPANEL_COLLECTOR_ONCE = /opt/tpanel/scripts/system/collector-once *
Cmnd_Alias TPANEL_NOTICLI_TEST = /opt/tpanel/scripts/system/noticli-test *

www-data ALL=(tpanel) NOPASSWD: TPANEL_SERVICE_STATUS, TPANEL_SERVICE_RESTART, TPANEL_SERVICE_RELOAD, TPANEL_DOCKER_CONTAINER_STATUS, TPANEL_DOCKER_CONTAINER_RESTART, TPANEL_TIMER_STATUS, TPANEL_TIMER_RESTART, TPANEL_COLLECTOR_ONCE, TPANEL_NOTICLI_TEST
```

The `ALL` host field is standard sudoers syntax. It must not be used as a command wildcard.

Forbidden patterns:

```sudoers
www-data ALL=(ALL) NOPASSWD: ALL
www-data ALL=(root) NOPASSWD: ALL
www-data ALL=(tpanel) NOPASSWD: /bin/bash
www-data ALL=(tpanel) NOPASSWD: /bin/sh
www-data ALL=(tpanel) NOPASSWD: /usr/bin/env *
```

Rules:

- Every sudo command must point to a reviewed wrapper.
- Every wrapper must parse only validated parameters.
- No sudo rule may grant arbitrary shell, package manager, editor or interpreter execution.
- No sudo rule may allow writable paths controlled by `www-data`.
- Each added command must map to a TPanel command catalog entry.

## Wrapper Script Requirements

Every approved wrapper must satisfy:

- owned by `tpanel:tpanel`;
- not writable by `www-data`;
- executable only when intended;
- rejects unknown or extra parameters;
- has deterministic exit codes;
- avoids printing secrets;
- has a corresponding command catalog entry;
- has a smoke test or manual validation note before production use.

Recommended file mode for executable wrappers:

```bash
sudo chown tpanel:tpanel /opt/tpanel/scripts/system/<wrapper>
sudo chmod 0750 /opt/tpanel/scripts/system/<wrapper>
```

## Local Configuration Files

Real configuration files must be stored outside Git or ignored by Git.

Expected source models:

```text
config/app.php.model
config/database.php.model
config/commands.php.model
config/noticli.php.model
```

Expected local runtime files:

```text
config/app.php
config/database.php
config/commands.php
config/noticli.php
```

Runtime config files may contain secrets or host-specific values and must remain ignored by Git.

## Security Validation Checklist

Before enabling administrative actions on a host:

- [ ] `id tpanel` shows a system user with no interactive shell.
- [ ] `id www-data` does not list `sudo`, `root`, `docker` or other broad privileged groups.
- [ ] `/opt/tpanel/scripts/system` is not writable by `www-data`.
- [ ] `/etc/sudoers.d/tpanel` passes `visudo -c`.
- [ ] No sudoers entry contains `NOPASSWD: ALL`.
- [ ] No sudoers entry grants `/bin/bash`, `/bin/sh`, package managers, editors or generic interpreters.
- [ ] Each sudoers command maps to one TPanel command catalog entry.
- [ ] Each command catalog entry has parameter validation and timeout.
- [ ] Logs and diagnostics do not expose secrets.

## Non-Goals

- Do not configure broad Docker access for `www-data`.
- Do not grant arbitrary command execution.
- Do not store provider credentials in TPanel when NotiCLI owns the notification provider configuration.
- Do not use this document as an unattended installation script.
