-- TPanel initial database definition.
-- Run the CREATE DATABASE statement with an administrative connection.
-- Run the table/index section with the `tpanel` database selected by the client.
-- This file intentionally avoids database-selection statements and schema-prefixed table names.

CREATE DATABASE tpanel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE userRole (
    id BIGINT AUTO_INCREMENT NOT NULL,
    roleName ENUM('ADMINISTRATOR', 'MONITOR') NOT NULL,
    description VARCHAR(255) NOT NULL,
    canRunAdministrativeAction TINYINT(1) NOT NULL DEFAULT 0,
    canAcknowledgeAlert TINYINT(1) NOT NULL DEFAULT 0,
    canCommentEvent TINYINT(1) NOT NULL DEFAULT 0,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_user_role PRIMARY KEY (id),
    CONSTRAINT uk_user_role_role_name UNIQUE (roleName)
) ENGINE=InnoDB;

CREATE TABLE authenticatedUser (
    id BIGINT AUTO_INCREMENT NOT NULL,
    idUserRole BIGINT NOT NULL,
    externalUsername VARCHAR(190) NOT NULL,
    displayName VARCHAR(190) NULL,
    isActive TINYINT(1) NOT NULL DEFAULT 1,
    lastSeenAt DATETIME NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_authenticated_user PRIMARY KEY (id),
    CONSTRAINT uk_authenticated_user_external_username UNIQUE (externalUsername),
    CONSTRAINT fk_authenticated_user_user_role FOREIGN KEY (idUserRole) REFERENCES userRole (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE serverHealthSummary (
    id BIGINT AUTO_INCREMENT NOT NULL,
    healthStatus ENUM('NORMAL', 'WARNING', 'CRITICAL', 'UNAVAILABLE') NOT NULL,
    hostname VARCHAR(255) NOT NULL,
    uptimeSeconds BIGINT NULL,
    loadAverage VARCHAR(100) NULL,
    collectedAt DATETIME NOT NULL,
    freshnessStatus ENUM('FRESH', 'STALE', 'UNKNOWN') NOT NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_server_health_summary PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE metricReading (
    id BIGINT AUTO_INCREMENT NOT NULL,
    idServerHealthSummary BIGINT NULL,
    metricCategory ENUM('SYSTEM', 'CPU', 'MEMORY', 'STORAGE', 'DISK_HEALTH', 'RAID', 'NETWORK', 'SERVICE', 'PROCESS', 'LOG', 'SECURITY', 'SENSOR', 'SCHEDULE') NOT NULL,
    metricName VARCHAR(190) NOT NULL,
    metricValue JSON NOT NULL,
    unit VARCHAR(50) NULL,
    severity ENUM('NORMAL', 'WARNING', 'CRITICAL', 'UNAVAILABLE') NOT NULL,
    source VARCHAR(190) NOT NULL,
    collectedAt DATETIME NOT NULL,
    expiresAt DATETIME NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_metric_reading PRIMARY KEY (id),
    CONSTRAINT fk_metric_reading_server_health_summary FOREIGN KEY (idServerHealthSummary) REFERENCES serverHealthSummary (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_metric_reading_category_collected ON metricReading (metricCategory, collectedAt);
CREATE INDEX idx_metric_reading_expires ON metricReading (expiresAt);

CREATE TABLE alert (
    id BIGINT AUTO_INCREMENT NOT NULL,
    idMetricReading BIGINT NULL,
    alertSource VARCHAR(190) NOT NULL,
    severity ENUM('INFO', 'WARNING', 'CRITICAL') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('OPEN', 'ACKNOWLEDGED', 'RESOLVED', 'DISMISSED') NOT NULL DEFAULT 'OPEN',
    openedAt DATETIME NOT NULL,
    resolvedAt DATETIME NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_alert PRIMARY KEY (id),
    CONSTRAINT fk_alert_metric_reading FOREIGN KEY (idMetricReading) REFERENCES metricReading (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_alert_status_severity ON alert (status, severity);
CREATE INDEX idx_alert_opened_at ON alert (openedAt);

CREATE TABLE administrativeAction (
    id BIGINT AUTO_INCREMENT NOT NULL,
    actionKey VARCHAR(190) NOT NULL,
    displayName VARCHAR(190) NOT NULL,
    description TEXT NOT NULL,
    targetType ENUM('SERVICE', 'CONTAINER', 'SCHEDULE', 'SYSTEM', 'NOTIFICATION', 'OTHER') NOT NULL,
    isEnabled TINYINT(1) NOT NULL DEFAULT 0,
    timeoutSeconds INT NOT NULL,
    requiresConfirmation TINYINT(1) NOT NULL DEFAULT 1,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_administrative_action PRIMARY KEY (id),
    CONSTRAINT uk_administrative_action_action_key UNIQUE (actionKey)
) ENGINE=InnoDB;

CREATE TABLE commandMapping (
    id BIGINT AUTO_INCREMENT NOT NULL,
    idAdministrativeAction BIGINT NOT NULL,
    commandKey VARCHAR(190) NOT NULL,
    executablePath VARCHAR(500) NOT NULL,
    allowedParametersSchema JSON NOT NULL,
    runAsUser VARCHAR(100) NOT NULL,
    isEnabled TINYINT(1) NOT NULL DEFAULT 0,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_command_mapping PRIMARY KEY (id),
    CONSTRAINT uk_command_mapping_command_key UNIQUE (commandKey),
    CONSTRAINT fk_command_mapping_administrative_action FOREIGN KEY (idAdministrativeAction) REFERENCES administrativeAction (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE auditRecord (
    id BIGINT AUTO_INCREMENT NOT NULL,
    idActorUser BIGINT NOT NULL,
    idAdministrativeAction BIGINT NULL,
    auditType ENUM('ADMIN_ACTION', 'DENIED_ACTION', 'ALERT_ACK', 'EVENT_COMMENT', 'NOTIFICATION', 'AUTHORIZATION') NOT NULL,
    actionKey VARCHAR(190) NULL,
    validatedParameters JSON NULL,
    resultStatus ENUM('SUCCESS', 'DENIED', 'FAILED', 'TIMED_OUT', 'SKIPPED') NOT NULL,
    exitCode INT NULL,
    failureReason TEXT NULL,
    requestId VARCHAR(190) NOT NULL,
    occurredAt DATETIME NOT NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_audit_record PRIMARY KEY (id),
    CONSTRAINT fk_audit_record_actor_user FOREIGN KEY (idActorUser) REFERENCES authenticatedUser (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_audit_record_administrative_action FOREIGN KEY (idAdministrativeAction) REFERENCES administrativeAction (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE commandExecutionRequest (
    id BIGINT AUTO_INCREMENT NOT NULL,
    requestId VARCHAR(190) NOT NULL,
    actionKey VARCHAR(190) NOT NULL,
    requestFingerprint CHAR(64) NOT NULL,
    resultStatus ENUM('IN_PROGRESS', 'SUCCESS', 'DENIED', 'FAILED', 'TIMED_OUT') NOT NULL,
    exitCode INT NULL,
    stdoutSummary TEXT NULL,
    stderrSummary TEXT NULL,
    failureReason TEXT NULL,
    expiresAt DATETIME NOT NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_command_execution_request PRIMARY KEY (id),
    CONSTRAINT uk_command_execution_request_request_id UNIQUE (requestId)
) ENGINE=InnoDB;

CREATE INDEX idx_command_execution_request_expires ON commandExecutionRequest (expiresAt);

CREATE INDEX idx_audit_record_actor_occurred ON auditRecord (idActorUser, occurredAt);
CREATE INDEX idx_audit_record_request_id ON auditRecord (requestId);
CREATE INDEX idx_audit_record_result_occurred ON auditRecord (resultStatus, occurredAt);

CREATE TABLE alertAcknowledgement (
    id BIGINT AUTO_INCREMENT NOT NULL,
    idAlert BIGINT NOT NULL,
    idActorUser BIGINT NOT NULL,
    acknowledgementNote TEXT NULL,
    acknowledgedAt DATETIME NOT NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_alert_acknowledgement PRIMARY KEY (id),
    CONSTRAINT fk_alert_acknowledgement_alert FOREIGN KEY (idAlert) REFERENCES alert (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_alert_acknowledgement_actor_user FOREIGN KEY (idActorUser) REFERENCES authenticatedUser (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_alert_acknowledgement_alert ON alertAcknowledgement (idAlert, acknowledgedAt);

CREATE TABLE eventComment (
    id BIGINT AUTO_INCREMENT NOT NULL,
    idAlert BIGINT NULL,
    idAuditRecord BIGINT NULL,
    idActorUser BIGINT NOT NULL,
    commentText TEXT NOT NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_event_comment PRIMARY KEY (id),
    CONSTRAINT fk_event_comment_alert FOREIGN KEY (idAlert) REFERENCES alert (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_event_comment_audit_record FOREIGN KEY (idAuditRecord) REFERENCES auditRecord (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_event_comment_actor_user FOREIGN KEY (idActorUser) REFERENCES authenticatedUser (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_event_comment_alert ON eventComment (idAlert, createdAt);
CREATE INDEX idx_event_comment_audit_record ON eventComment (idAuditRecord, createdAt);

CREATE TABLE configurationModel (
    id BIGINT AUTO_INCREMENT NOT NULL,
    configKey VARCHAR(190) NOT NULL,
    modelPath VARCHAR(500) NOT NULL,
    runtimePath VARCHAR(500) NOT NULL,
    containsSecrets TINYINT(1) NOT NULL DEFAULT 0,
    isRequired TINYINT(1) NOT NULL DEFAULT 1,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_configuration_model PRIMARY KEY (id),
    CONSTRAINT uk_configuration_model_config_key UNIQUE (configKey)
) ENGINE=InnoDB;

CREATE TABLE notificationEvent (
    id BIGINT AUTO_INCREMENT NOT NULL,
    idAlert BIGINT NULL,
    sender VARCHAR(20) NOT NULL,
    category VARCHAR(100) NOT NULL,
    priority ENUM('HIGH', 'NORMAL', 'LOW') NOT NULL DEFAULT 'NORMAL',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    deliveryStatus ENUM('PENDING', 'SENT', 'FAILED', 'SKIPPED') NOT NULL DEFAULT 'PENDING',
    exitCode INT NULL,
    failureReason TEXT NULL,
    createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sentAt DATETIME NULL,
    updatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_notification_event PRIMARY KEY (id),
    CONSTRAINT fk_notification_event_alert FOREIGN KEY (idAlert) REFERENCES alert (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_notification_event_status_created ON notificationEvent (deliveryStatus, createdAt);
CREATE INDEX idx_notification_event_alert ON notificationEvent (idAlert, createdAt);
