<?php

declare(strict_types=1);

final class SemanticSchema
{
    public function __construct(private PDO $pdo) {}

    /**
     * Prepare the target database for a global harvest run.
     *
     * If sysHeuristSemantics is missing, there is no reliable concept-to-target
     * map for any existing harvested definitions. Treat this as a first global
     * harvest run and clear all target definition tables before creating the
     * semantic tracking tables.
     */
    public function prepareTargetForRun(): void
    {
        if (!$this->tableExists('sysHeuristSemantics')) {
            logWarning('sysHeuristSemantics is missing; cleaning all target tables with prefix def before first global harvest');
            $this->truncateDefinitionTables();
        }

        $this->ensureTables();
    }

    public function ensureTables(): void
    {
        $this->ensureDefTermsLinksTable();

        $this->pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `sysHeuristSemantics` (
    `sem_ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sem_EntityType` ENUM('rty','dty','trm') NOT NULL,
    `sem_TargetID` INT UNSIGNED NOT NULL,
    `sem_OriginatingDBID` INT UNSIGNED NOT NULL,
    `sem_IDInOriginatingDB` INT UNSIGNED NOT NULL,
    `sem_ImportedViaDBID` INT UNSIGNED NOT NULL,
    `sem_ImportedViaDatabase` VARCHAR(255) DEFAULT NULL,
    `sem_SourceLocalID` INT UNSIGNED DEFAULT NULL,
    `sem_IsOriginImport` TINYINT(1) NOT NULL DEFAULT 0,
    `sem_IsDerivedImport` TINYINT(1) NOT NULL DEFAULT 0,
    `sem_CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sem_UpdatedAt` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`sem_ID`),
    UNIQUE KEY `uq_sem_entity_origin` (`sem_EntityType`, `sem_OriginatingDBID`, `sem_IDInOriginatingDB`),
    KEY `idx_sem_target` (`sem_EntityType`, `sem_TargetID`),
    KEY `idx_sem_imported_via` (`sem_ImportedViaDBID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `sysHeuristSemanticStructures` (
    `sms_ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sms_TargetRstID` INT UNSIGNED NOT NULL,
    `sms_TargetRtyID` INT UNSIGNED NOT NULL,
    `sms_TargetDtyID` INT UNSIGNED NOT NULL,
    `sms_RtyOriginatingDBID` INT UNSIGNED NOT NULL,
    `sms_RtyIDInOriginatingDB` INT UNSIGNED NOT NULL,
    `sms_DtyOriginatingDBID` INT UNSIGNED NOT NULL,
    `sms_DtyIDInOriginatingDB` INT UNSIGNED NOT NULL,
    `sms_ImportedViaDBID` INT UNSIGNED NOT NULL,
    `sms_ImportedViaDatabase` VARCHAR(255) DEFAULT NULL,
    `sms_SourceRstID` INT UNSIGNED DEFAULT NULL,
    `sms_SourceRtyLocalID` INT UNSIGNED DEFAULT NULL,
    `sms_SourceDtyLocalID` INT UNSIGNED DEFAULT NULL,
    `sms_IsOriginStructure` TINYINT(1) NOT NULL DEFAULT 0,
    `sms_IsDerivedStructure` TINYINT(1) NOT NULL DEFAULT 0,
    `sms_CreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sms_UpdatedAt` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`sms_ID`),
    UNIQUE KEY `uq_sms_rty_dty_concept` (`sms_RtyOriginatingDBID`, `sms_RtyIDInOriginatingDB`, `sms_DtyOriginatingDBID`, `sms_DtyIDInOriginatingDB`),
    KEY `idx_sms_target_rst` (`sms_TargetRstID`),
    KEY `idx_sms_target_rty_dty` (`sms_TargetRtyID`, `sms_TargetDtyID`),
    KEY `idx_sms_imported_via` (`sms_ImportedViaDBID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }


    private function ensureDefTermsLinksTable(): void
    {
        // The first-run cleanup truncates all def* tables but does not create missing
        // Heurist support tables. Ensure this hierarchy table exists so the final
        // rebuild step can safely recreate parent/child links from defTerms.
        $this->pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `defTermsLinks` (
  `trl_ID` MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key for vocabulary-terms hierarchy',
  `trl_ParentID` SMALLINT UNSIGNED NOT NULL COMMENT 'The ID of the parent/owner term in the hierarchy',
  `trl_TermID` SMALLINT UNSIGNED NOT NULL COMMENT 'The ID of the child term',
  PRIMARY KEY (`trl_ID`),
  UNIQUE KEY `trl_CompositeKey` (`trl_ParentID`, `trl_TermID`)
) ENGINE=InnoDB COMMENT='Identifies hierarchy of vocabularies and terms'
SQL);
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName LIMIT 1'
        );
        $stmt->bindValue(':tableName', $tableName, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    private function truncateDefinitionTables(): void
    {
        $stmt = $this->pdo->query(
            "SELECT TABLE_NAME FROM information_schema.TABLES " .
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'def%' " .
            "ORDER BY TABLE_NAME"
        );
        $tables = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        if (!$tables) {
            logLine('No target definition tables with prefix def found to clean');
            return;
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($tables as $table) {
                if (!is_string($table) || $table === '') {
                    continue;
                }
                $this->pdo->exec('TRUNCATE TABLE `' . str_replace('`', '``', $table) . '`');
                logLine('Cleaned target definition table ' . $table);
            }
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}