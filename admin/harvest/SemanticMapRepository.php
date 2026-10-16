<?php

declare(strict_types=1);

final class SemanticMapRepository
{
    public function __construct(private PDO $pdo) {}

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function findSemanticTargetId(string $entityType, int $originDb, int $originId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT `sem_TargetID` FROM `sysHeuristSemantics`
             WHERE `sem_EntityType` = :type
               AND `sem_OriginatingDBID` = :originDb
               AND `sem_IDInOriginatingDB` = :originId
             LIMIT 1'
        );
        $stmt->bindValue(':type', $entityType, PDO::PARAM_STR);
        $stmt->bindValue(':originDb', $originDb, PDO::PARAM_INT);
        $stmt->bindValue(':originId', $originId, PDO::PARAM_INT);
        $stmt->execute();
        $value = $stmt->fetchColumn();
        return $value === false ? null : (int)$value;
    }



    /**
     * Return target-local IDs keyed by "OriginatingDBID-IDInOriginatingDB" for one entity type.
     *
     * @return array<string,int>
     */
    public function loadTargetIdMap(string $entityType): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT `sem_OriginatingDBID`, `sem_IDInOriginatingDB`, `sem_TargetID`
             FROM `sysHeuristSemantics`
             WHERE `sem_EntityType` = :type'
        );
        $stmt->bindValue(':type', $entityType, PDO::PARAM_STR);
        $stmt->execute();

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = (int)$row['sem_OriginatingDBID'] . '-' . (int)$row['sem_IDInOriginatingDB'];
            $map[$key] = (int)$row['sem_TargetID'];
        }
        return $map;
    }

    public function getSemanticRecord(string $entityType, int $originDb, int $originId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `sysHeuristSemantics`
             WHERE `sem_EntityType` = :type
               AND `sem_OriginatingDBID` = :originDb
               AND `sem_IDInOriginatingDB` = :originId
             LIMIT 1'
        );
        $stmt->bindValue(':type', $entityType, PDO::PARAM_STR);
        $stmt->bindValue(':originDb', $originDb, PDO::PARAM_INT);
        $stmt->bindValue(':originId', $originId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function semanticExists(string $entityType, int $originDb, int $originId): bool
    {
        return $this->findSemanticTargetId($entityType, $originDb, $originId) !== null;
    }

    public function recordSemantic(
        string $entityType,
        int $targetId,
        int $originDb,
        int $originId,
        int $importedViaDb,
        ?string $importedViaDatabase,
        ?int $sourceLocalId,
        bool $isOriginImport,
        bool $isDerivedImport
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `sysHeuristSemantics`
                (`sem_EntityType`, `sem_TargetID`, `sem_OriginatingDBID`, `sem_IDInOriginatingDB`,
                 `sem_ImportedViaDBID`, `sem_ImportedViaDatabase`, `sem_SourceLocalID`,
                 `sem_IsOriginImport`, `sem_IsDerivedImport`)
             VALUES
                (:type, :targetId, :originDb, :originId,
                 :viaDb, :viaDatabase, :sourceLocalId,
                 :isOrigin, :isDerived)
             ON DUPLICATE KEY UPDATE
                `sem_TargetID` = VALUES(`sem_TargetID`),
                `sem_UpdatedAt` = CURRENT_TIMESTAMP'
        );
        $stmt->bindValue(':type', $entityType, PDO::PARAM_STR);
        $stmt->bindValue(':targetId', $targetId, PDO::PARAM_INT);
        $stmt->bindValue(':originDb', $originDb, PDO::PARAM_INT);
        $stmt->bindValue(':originId', $originId, PDO::PARAM_INT);
        $stmt->bindValue(':viaDb', $importedViaDb, PDO::PARAM_INT);
        $stmt->bindValue(':viaDatabase', $importedViaDatabase, $importedViaDatabase === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':sourceLocalId', $sourceLocalId, $sourceLocalId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':isOrigin', $isOriginImport ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':isDerived', $isDerivedImport ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function findSemanticStructureId(int $rtyOriginDb, int $rtyOriginId, int $dtyOriginDb, int $dtyOriginId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT `sms_TargetRstID` FROM `sysHeuristSemanticStructures`
             WHERE `sms_RtyOriginatingDBID` = :rtyDb
               AND `sms_RtyIDInOriginatingDB` = :rtyId
               AND `sms_DtyOriginatingDBID` = :dtyDb
               AND `sms_DtyIDInOriginatingDB` = :dtyId
             LIMIT 1'
        );
        $stmt->bindValue(':rtyDb', $rtyOriginDb, PDO::PARAM_INT);
        $stmt->bindValue(':rtyId', $rtyOriginId, PDO::PARAM_INT);
        $stmt->bindValue(':dtyDb', $dtyOriginDb, PDO::PARAM_INT);
        $stmt->bindValue(':dtyId', $dtyOriginId, PDO::PARAM_INT);
        $stmt->execute();
        $value = $stmt->fetchColumn();
        return $value === false ? null : (int)$value;
    }

    public function recordSemanticStructure(
        int $targetRstId,
        int $targetRtyId,
        int $targetDtyId,
        array $rtyOrigin,
        array $dtyOrigin,
        int $importedViaDb,
        ?string $importedViaDatabase,
        ?int $sourceRstId,
        ?int $sourceRtyLocalId,
        ?int $sourceDtyLocalId,
        bool $isOriginStructure,
        bool $isDerivedStructure
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `sysHeuristSemanticStructures`
                (`sms_TargetRstID`, `sms_TargetRtyID`, `sms_TargetDtyID`,
                 `sms_RtyOriginatingDBID`, `sms_RtyIDInOriginatingDB`,
                 `sms_DtyOriginatingDBID`, `sms_DtyIDInOriginatingDB`,
                 `sms_ImportedViaDBID`, `sms_ImportedViaDatabase`,
                 `sms_SourceRstID`, `sms_SourceRtyLocalID`, `sms_SourceDtyLocalID`,
                 `sms_IsOriginStructure`, `sms_IsDerivedStructure`)
             VALUES
                (:targetRst, :targetRty, :targetDty,
                 :rtyDb, :rtyId,
                 :dtyDb, :dtyId,
                 :viaDb, :viaDatabase,
                 :sourceRst, :sourceRty, :sourceDty,
                 :isOrigin, :isDerived)
             ON DUPLICATE KEY UPDATE
                `sms_TargetRstID` = VALUES(`sms_TargetRstID`),
                `sms_TargetRtyID` = VALUES(`sms_TargetRtyID`),
                `sms_TargetDtyID` = VALUES(`sms_TargetDtyID`),
                `sms_UpdatedAt` = CURRENT_TIMESTAMP'
        );
        $stmt->bindValue(':targetRst', $targetRstId, PDO::PARAM_INT);
        $stmt->bindValue(':targetRty', $targetRtyId, PDO::PARAM_INT);
        $stmt->bindValue(':targetDty', $targetDtyId, PDO::PARAM_INT);
        $stmt->bindValue(':rtyDb', (int)$rtyOrigin['db'], PDO::PARAM_INT);
        $stmt->bindValue(':rtyId', (int)$rtyOrigin['id'], PDO::PARAM_INT);
        $stmt->bindValue(':dtyDb', (int)$dtyOrigin['db'], PDO::PARAM_INT);
        $stmt->bindValue(':dtyId', (int)$dtyOrigin['id'], PDO::PARAM_INT);
        $stmt->bindValue(':viaDb', $importedViaDb, PDO::PARAM_INT);
        $stmt->bindValue(':viaDatabase', $importedViaDatabase, $importedViaDatabase === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':sourceRst', $sourceRstId, $sourceRstId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':sourceRty', $sourceRtyLocalId, $sourceRtyLocalId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':sourceDty', $sourceDtyLocalId, $sourceDtyLocalId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':isOrigin', $isOriginStructure ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':isDerived', $isDerivedStructure ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getMaxRecStructureOrder(int $targetRtyId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(`rst_DisplayOrder`), 0) FROM `defRecStructure` WHERE `rst_RecTypeID` = :rty');
        $stmt->bindValue(':rty', $targetRtyId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}
