-- TPanel mandatory seed data.
-- Run with the `tpanel` database selected by the client.
-- This file intentionally avoids credentials, database-selection statements and schema-prefixed table names.

INSERT INTO userRole (
    roleName,
    description,
    canRunAdministrativeAction,
    canAcknowledgeAlert,
    canCommentEvent
) VALUES
    (
        'ADMINISTRATOR',
        'Full TPanel operator role. Can run authorized administrative actions and manage operational events.',
        1,
        1,
        1
    ),
    (
        'MONITOR',
        'Read-only monitoring role. Can acknowledge alerts and comment events without changing server state.',
        0,
        1,
        1
    )
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    canRunAdministrativeAction = VALUES(canRunAdministrativeAction),
    canAcknowledgeAlert = VALUES(canAcknowledgeAlert),
    canCommentEvent = VALUES(canCommentEvent);

INSERT INTO configurationModel (
    configKey,
    modelPath,
    runtimePath,
    containsSecrets,
    isRequired
) VALUES
    (
        'app',
        'config/app.php.model',
        'config/app.php',
        0,
        1
    ),
    (
        'database',
        'config/database.php.model',
        'config/database.php',
        1,
        1
    ),
    (
        'commands',
        'config/commands.php.model',
        'config/commands.php',
        0,
        1
    ),
    (
        'noticli',
        'config/noticli.php.model',
        'config/noticli.php',
        0,
        0
    )
ON DUPLICATE KEY UPDATE
    modelPath = VALUES(modelPath),
    runtimePath = VALUES(runtimePath),
    containsSecrets = VALUES(containsSecrets),
    isRequired = VALUES(isRequired);
