<?php

namespace MiMFa\Library;

final class TinyMCEMarkdownMigrator
{
    public const DEFAULT_BATCH_SIZE = 25;
    public const MAX_BATCH_SIZE = 100;

    private DataTable $contentTable;
    private DataTable $backupTable;
    private \PDO $connection;

    public function __construct()
    {
        $this->contentTable = table('Content');
        $this->backupTable = table('TinyMCE_ContentBackup');
        $this->assertTableName($this->contentTable->Name);
        $this->assertTableName($this->backupTable->Name);
        $this->connection = $this->contentTable->GetDatabase()->Connection();
    }

    public function Analyze(int $sampleLimit = 8): array
    {
        $records = $this->fetchContentRecords();
        $summary = [
            'total' => count($records),
            'empty' => 0,
            'plain' => 0,
            'markdown' => 0,
            'html' => 0,
            'mixed' => 0,
            'unsupported' => 0,
            'eligible' => 0,
            'activeBackups' => 0,
            'modifiedAfterMigration' => 0,
            'samples' => []
        ];

        foreach ($records as $record) {
            $analysis = $this->analyzeContent((string)($record['Content'] ?? ''));
            $format = $analysis['format'];
            $summary[$format]++;

            if ($analysis['eligible'])
                $summary['eligible']++;

            if (count($summary['samples']) < max(0, $sampleLimit)
                && ($analysis['eligible'] || in_array($format, ['mixed', 'unsupported'], true))) {
                $preview = $analysis['eligible'] ? $this->convertAnalyzedContent((string)$record['Content'], $analysis) : null;
                $summary['samples'][] = [
                    'Id' => (int)$record['Id'],
                    'Title' => (string)($record['Title'] ?? ''),
                    'Format' => $format,
                    'Issue' => $analysis['issue'],
                    'Original' => (string)$record['Content'],
                    'Converted' => $preview['html'] ?? null
                ];
            }
        }

        if ($this->backupTableExists()) {
            $query = "SELECT B.MigratedContentHash, C.Content
                FROM `{$this->backupTable->Name}` AS B
                INNER JOIN `{$this->contentTable->Name}` AS C ON C.Id=B.ContentId
                WHERE B.RestoredTime IS NULL";
            foreach ($this->connection->query($query)->fetchAll(\PDO::FETCH_ASSOC) as $backup) {
                $summary['activeBackups']++;
                if (!hash_equals((string)$backup['MigratedContentHash'], hash('sha256', (string)$backup['Content'])))
                    $summary['modifiedAfterMigration']++;
            }
        }

        return $summary;
    }

    public function Apply(int $batchSize = self::DEFAULT_BATCH_SIZE): array
    {
        $batchSize = $this->normalizeBatchSize($batchSize);
        $prepared = [];
        $skipped = ['mixed' => 0, 'html' => 0, 'unsupported' => 0, 'empty' => 0];

        foreach ($this->fetchContentRecords() as $record) {
            if (count($prepared) >= $batchSize)
                break;

            $content = (string)($record['Content'] ?? '');
            $analysis = $this->analyzeContent($content);
            if (!$analysis['eligible']) {
                if (isset($skipped[$analysis['format']]))
                    $skipped[$analysis['format']]++;
                continue;
            }

            $converted = $this->convertAnalyzedContent($content, $analysis);
            if ($converted['html'] === $content)
                continue;

            $prepared[] = [
                'Id' => (int)$record['Id'],
                'Title' => (string)($record['Title'] ?? ''),
                'OriginalContent' => $content,
                'OriginalUpdateTime' => $record['UpdateTime'] ?? null,
                'MigratedContent' => $converted['html'],
                'MigratedContentHash' => hash('sha256', $converted['html'])
            ];
        }

        if (!$prepared)
            return ['migrated' => 0, 'skipped' => $skipped];

        $this->ensureBackupTable();
        $backup = $this->connection->prepare(
            "INSERT INTO `{$this->backupTable->Name}`
                (ContentId, Title, OriginalContent, OriginalUpdateTime, MigratedContentHash, CreatedTime, RestoredTime)
             VALUES (:ContentId, :Title, :OriginalContent, :OriginalUpdateTime, :MigratedContentHash, CURRENT_TIMESTAMP, NULL)
             ON DUPLICATE KEY UPDATE
                Title=VALUES(Title), OriginalContent=VALUES(OriginalContent),
                OriginalUpdateTime=VALUES(OriginalUpdateTime), MigratedContentHash=VALUES(MigratedContentHash),
                CreatedTime=CURRENT_TIMESTAMP, RestoredTime=NULL"
        );
        $update = $this->connection->prepare(
            "UPDATE `{$this->contentTable->Name}`
             SET Content=:MigratedContent, UpdateTime=:OriginalUpdateTime
             WHERE Id=:Id AND Content=:OriginalContent"
        );

        try {
            $this->connection->beginTransaction();
            foreach ($prepared as $record) {
                $backup->execute([
                    ':ContentId' => $record['Id'],
                    ':Title' => $record['Title'],
                    ':OriginalContent' => $record['OriginalContent'],
                    ':OriginalUpdateTime' => $record['OriginalUpdateTime'],
                    ':MigratedContentHash' => $record['MigratedContentHash']
                ]);
                $update->execute([
                    ':MigratedContent' => $record['MigratedContent'],
                    ':OriginalUpdateTime' => $record['OriginalUpdateTime'],
                    ':Id' => $record['Id'],
                    ':OriginalContent' => $record['OriginalContent']
                ]);
                if ($update->rowCount() !== 1)
                    throw new \RuntimeException('Content changed while the migration was running. No records were committed.');
            }
            $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction())
                $this->connection->rollBack();
            throw $exception;
        }

        return ['migrated' => count($prepared), 'skipped' => $skipped];
    }

    public function Rollback(int $batchSize = self::DEFAULT_BATCH_SIZE): array
    {
        $batchSize = $this->normalizeBatchSize($batchSize);
        if (!$this->backupTableExists())
            return ['restored' => 0, 'modified' => 0];

        $query = "SELECT B.ContentId, B.OriginalContent, B.OriginalUpdateTime, B.MigratedContentHash, C.Content AS CurrentContent
            FROM `{$this->backupTable->Name}` AS B
            INNER JOIN `{$this->contentTable->Name}` AS C ON C.Id=B.ContentId
            WHERE B.RestoredTime IS NULL
            ORDER BY B.Id DESC";
        $records = $this->connection->query($query)->fetchAll(\PDO::FETCH_ASSOC);
        $restorable = [];
        $modified = 0;

        foreach ($records as $record) {
            if (!hash_equals((string)$record['MigratedContentHash'], hash('sha256', (string)$record['CurrentContent']))) {
                $modified++;
                continue;
            }
            $restorable[] = $record;
            if (count($restorable) >= $batchSize)
                break;
        }

        if (!$restorable)
            return ['restored' => 0, 'modified' => $modified];

        $restore = $this->connection->prepare(
            "UPDATE `{$this->contentTable->Name}`
             SET Content=:OriginalContent, UpdateTime=:OriginalUpdateTime
             WHERE Id=:ContentId AND Content=:CurrentContent"
        );
        $mark = $this->connection->prepare(
            "UPDATE `{$this->backupTable->Name}` SET RestoredTime=CURRENT_TIMESTAMP
             WHERE ContentId=:ContentId AND RestoredTime IS NULL"
        );

        try {
            $this->connection->beginTransaction();
            foreach ($restorable as $record) {
                $restore->execute([
                    ':OriginalContent' => $record['OriginalContent'],
                    ':OriginalUpdateTime' => $record['OriginalUpdateTime'],
                    ':ContentId' => $record['ContentId'],
                    ':CurrentContent' => $record['CurrentContent']
                ]);
                if ($restore->rowCount() !== 1)
                    throw new \RuntimeException('Content changed while the rollback was running. No records were committed.');
                $mark->execute([':ContentId' => $record['ContentId']]);
            }
            $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction())
                $this->connection->rollBack();
            throw $exception;
        }

        return ['restored' => count($restorable), 'modified' => $modified];
    }

    public function Convert(string $content): array
    {
        $analysis = $this->analyzeContent($content);
        if (!$analysis['eligible'])
            return ['html' => $content, ...$analysis];

        return [...$this->convertAnalyzedContent($content, $analysis), ...$analysis];
    }

    private function analyzeContent(string $content): array
    {
        if (trim($content) === '')
            return ['format' => 'empty', 'eligible' => false, 'issue' => null];

        $hasHtml = preg_match('~</?[a-z][a-z0-9:-]*(?:\s[^>]*)?>~iu', $content) === 1;
        $hasMarkdown = preg_match(
            '~(^|\R)\s{0,3}(?:#{1,6}\s+|(?:[-+*]|\d+[.)])\s+|>{1,3}\s+|\|.*\|\s*$|-{3,}\s*$)|!Button\[[^\]]*\]\([^\r\n]*\)|!\[[^\]]*\]\([^\r\n]*\)|\[[^\]]+\]\([^\r\n]*\)|(?<!\\\\)[*_+\~`]\S~imu',
            $content
        ) === 1;
        $unsupported = preg_match_all('~!(?!Button\b)([a-z][a-z0-9_]*)\s*[\[{]~iu', $content, $matches);

        if ($unsupported) {
            $names = array_values(array_unique($matches[1] ?? []));
            return [
                'format' => 'unsupported',
                'eligible' => false,
                'issue' => 'Unsupported custom markup: ' . implode(', ', $names)
            ];
        }

        if ($hasHtml && $hasMarkdown)
            return ['format' => 'mixed', 'eligible' => false, 'issue' => 'HTML and Markdown are mixed in this record.'];
        if ($hasHtml)
            return ['format' => 'html', 'eligible' => false, 'issue' => null];

        if ($this->containsUnsafeButtonUrl($content))
            return ['format' => 'unsupported', 'eligible' => false, 'issue' => 'A legacy button contains an unsupported URL.'];

        return ['format' => $hasMarkdown ? 'markdown' : 'plain', 'eligible' => true, 'issue' => null];
    }

    private function convertAnalyzedContent(string $content, array $analysis): array
    {
        if (!$analysis['eligible'])
            return ['html' => $content];

        $safeMarkup = preg_replace_callback(
            '~!Button\[([^\]]*)\]\(\s*([^\s)]+)(?:\s+"[^"]*")?\s*\)~iu',
            static fn(array $match) => '[' . $match[1] . '](' . $match[2] . ') @{output button}',
            $content
        ) ?? $content;

        $translationWasAllowed = \_::$Front->AllowTranslate;
        try {
            \_::$Front->AllowTranslate = false;
            $html = Struct::Convert($safeMarkup);
        } finally {
            \_::$Front->AllowTranslate = $translationWasAllowed;
        }
        return ['html' => (string)TinyMCEContentSanitizer::Sanitize($html)];
    }

    private function containsUnsafeButtonUrl(string $content): bool
    {
        if (!preg_match_all('~!Button\[[^\]]*\]\(\s*([^\s)]+)~iu', $content, $matches))
            return false;

        foreach ($matches[1] as $url)
            if (!preg_match('~^(?:https?://|/|#|mailto:|tel:)~iu', html_entity_decode((string)$url, ENT_QUOTES | ENT_HTML5, 'UTF-8')))
                return true;

        return false;
    }

    private function fetchContentRecords(): array
    {
        $query = "SELECT Id, Title, Content, UpdateTime FROM `{$this->contentTable->Name}` ORDER BY Id ASC";
        return $this->connection->query($query)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function ensureBackupTable(): void
    {
        $this->connection->exec(
            "CREATE TABLE IF NOT EXISTS `{$this->backupTable->Name}` (
                Id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                ContentId BIGINT UNSIGNED NOT NULL,
                Title VARCHAR(500) NULL,
                OriginalContent LONGTEXT NULL,
                OriginalUpdateTime DATETIME NULL,
                MigratedContentHash CHAR(64) NOT NULL,
                CreatedTime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                RestoredTime DATETIME NULL,
                PRIMARY KEY (Id),
                UNIQUE KEY UX_TinyMCE_ContentBackup_ContentId (ContentId),
                KEY IX_TinyMCE_ContentBackup_RestoredTime (RestoredTime)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function backupTableExists(): bool
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:TableName'
        );
        $statement->execute([':TableName' => $this->backupTable->Name]);
        return (int)$statement->fetchColumn() > 0;
    }

    private function normalizeBatchSize(int $batchSize): int
    {
        return max(1, min(self::MAX_BATCH_SIZE, $batchSize));
    }

    private function assertTableName(string $tableName): void
    {
        if (!preg_match('/^[a-z0-9_]+$/i', $tableName))
            throw new \RuntimeException('The resolved database table name is invalid.');
    }
}
