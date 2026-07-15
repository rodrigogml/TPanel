-- Arquivo de atualização do banco de dados, não pode ser alterado por agentes de IA.

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
