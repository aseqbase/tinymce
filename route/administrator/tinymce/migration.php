<?php

use MiMFa\Library\TinyMCEMarkdownMigrator;

$renderMigration = function (array $input = []) {
    auth(\_::$User->AdminAccess);
    library('TinyMCEContentSanitizer');
    library('TinyMCEMarkdownMigrator');

    $escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $translate = static fn(string $value) => __($value);
    $formatLabels = [
        'empty' => 'Empty',
        'plain' => 'Plain text',
        'markdown' => 'Markdown',
        'html' => 'Existing HTML',
        'mixed' => 'Mixed content',
        'unsupported' => 'Unsupported content'
    ];
    $translateIssue = static function (?string $issue) use ($translate): string {
        if (!$issue)
            return '';
        if (str_starts_with($issue, 'Unsupported custom markup:'))
            return $translate('Unsupported custom markup:') . substr($issue, strlen('Unsupported custom markup:'));
        return $translate($issue);
    };
    $message = null;
    $messageClass = 'success';
    $migrator = new TinyMCEMarkdownMigrator();

    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
        $action = strtolower(trim((string)get($input, 'Action')));
        $confirmation = strtoupper(trim((string)get($input, 'Confirmation')));
        $batchSize = (int)get($input, 'BatchSize');

        try {
            if ($action === 'apply') {
                if ($confirmation !== 'MIGRATE')
                    throw new RuntimeException('Type MIGRATE in the confirmation field before applying the migration.');

                $result = $migrator->Apply($batchSize);
                $message = sprintf(
                    $translate('%d content records were migrated. Run another batch until Eligible becomes zero.'),
                    $result['migrated']
                );
            } elseif ($action === 'rollback') {
                if ($confirmation !== 'ROLLBACK')
                    throw new RuntimeException('Type ROLLBACK in the confirmation field before restoring content.');

                $result = $migrator->Rollback($batchSize);
                $message = sprintf(
                    $translate('%d content records were restored. %d edited records were protected and skipped.'),
                    $result['restored'],
                    $result['modified']
                );
            }
        } catch (Throwable $exception) {
            $message = $translate('The migration operation failed:') . ' ' . $exception->getMessage();
            $messageClass = 'error';
        }
    }

    $summary = $migrator->Analyze();
    $stats = [
        'Total content' => $summary['total'],
        'Eligible' => $summary['eligible'],
        'Markdown' => $summary['markdown'],
        'Plain text' => $summary['plain'],
        'Existing HTML' => $summary['html'],
        'Mixed content' => $summary['mixed'],
        'Unsupported content' => $summary['unsupported'],
        'Active backups' => $summary['activeBackups'],
        'Edited after migration' => $summary['modifiedAfterMigration']
    ];

    ob_start();
    ?>
    <style>
        .tinymce-migration{max-width:1200px;margin:1rem auto;padding:1rem;color:#1f2937}
        .tinymce-migration h1,.tinymce-migration h2{margin:.5rem 0 1rem}
        .tinymce-migration .notice{padding:.85rem 1rem;border-radius:8px;margin:1rem 0;background:#ecfdf5;border:1px solid #6ee7b7}
        .tinymce-migration .notice.error{background:#fef2f2;border-color:#fca5a5}
        .tinymce-migration .warning{padding:.85rem 1rem;border-radius:8px;background:#fffbeb;border:1px solid #fcd34d}
        .tinymce-migration .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;margin:1rem 0}
        .tinymce-migration .stat{padding:1rem;border:1px solid #d1d5db;border-radius:8px;background:#fff}
        .tinymce-migration .stat strong{display:block;font-size:1.5rem;margin-top:.35rem}
        .tinymce-migration .actions{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;margin:1rem 0}
        .tinymce-migration form{padding:1rem;border:1px solid #d1d5db;border-radius:8px;background:#fff}
        .tinymce-migration label{display:block;margin:.65rem 0 .25rem}
        .tinymce-migration input{width:100%;padding:.55rem;border:1px solid #9ca3af;border-radius:5px}
        .tinymce-migration button{margin-top:.8rem;padding:.65rem 1rem;border:0;border-radius:5px;background:#047857;color:#fff;cursor:pointer}
        .tinymce-migration button.rollback{background:#b45309}
        .tinymce-migration table{width:100%;border-collapse:collapse;margin-top:1rem;background:#fff}
        .tinymce-migration th,.tinymce-migration td{padding:.65rem;border:1px solid #d1d5db;vertical-align:top;text-align:start}
        .tinymce-migration pre{max-width:500px;max-height:220px;overflow:auto;white-space:pre-wrap;direction:auto;background:#f3f4f6;padding:.65rem;border-radius:5px}
        .tinymce-migration .preview{max-width:500px;max-height:260px;overflow:auto;direction:auto}
    </style>
    <section class="tinymce-migration">
        <h1><?= $escape($translate('Markdown Content Migration')) ?></h1>
        <p><?= $escape($translate('Preview and convert legacy AseqBase Markdown content to sanitized HTML for TinyMCE. PHP code is never stored in content.')) ?></p>

        <?php if ($message): ?>
            <div class="notice <?= $escape($messageClass) ?>"><?= $escape($message) ?></div>
        <?php endif; ?>

        <div class="warning">
            <strong><?= $escape($translate('Safety notice')) ?></strong><br>
            <?= $escape($translate('Test this migration on a local database backup first. Mixed HTML and Markdown, unsupported directives, and content edited after migration are never overwritten automatically.')) ?>
        </div>

        <div class="stats">
            <?php foreach ($stats as $label => $value): ?>
                <div class="stat"><span><?= $escape($translate($label)) ?></span><strong><?= (int)$value ?></strong></div>
            <?php endforeach; ?>
        </div>

        <div class="actions">
            <form method="post">
                <h2><?= $escape($translate('Apply next migration batch')) ?></h2>
                <p><?= $escape($translate('Original content and update time are saved in a dedicated backup table before each change.')) ?></p>
                <input type="hidden" name="Action" value="apply">
                <label for="migration-batch-size"><?= $escape($translate('Batch size')) ?></label>
                <input id="migration-batch-size" type="number" name="BatchSize" min="1" max="100" value="25">
                <label for="migration-confirmation"><?= $escape($translate('Type MIGRATE to confirm')) ?></label>
                <input id="migration-confirmation" type="text" name="Confirmation" autocomplete="off">
                <button type="submit"><?= $escape($translate('Apply migration batch')) ?></button>
            </form>

            <form method="post">
                <h2><?= $escape($translate('Rollback last migration batch')) ?></h2>
                <p><?= $escape($translate('Records edited after migration are protected and will not be overwritten.')) ?></p>
                <input type="hidden" name="Action" value="rollback">
                <label for="rollback-batch-size"><?= $escape($translate('Batch size')) ?></label>
                <input id="rollback-batch-size" type="number" name="BatchSize" min="1" max="100" value="25">
                <label for="rollback-confirmation"><?= $escape($translate('Type ROLLBACK to confirm')) ?></label>
                <input id="rollback-confirmation" type="text" name="Confirmation" autocomplete="off">
                <button class="rollback" type="submit"><?= $escape($translate('Restore from migration backup')) ?></button>
            </form>
        </div>

        <h2><?= $escape($translate('Dry-run samples')) ?></h2>
        <?php if (!$summary['samples']): ?>
            <p><?= $escape($translate('No eligible or exceptional content records were found.')) ?></p>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th><?= $escape($translate('ID')) ?></th>
                    <th><?= $escape($translate('Title')) ?></th>
                    <th><?= $escape($translate('Detected format')) ?></th>
                    <th><?= $escape($translate('Original content')) ?></th>
                    <th><?= $escape($translate('Converted preview')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($summary['samples'] as $sample): ?>
                    <tr>
                        <td><?= (int)$sample['Id'] ?></td>
                        <td><?= $escape($sample['Title']) ?></td>
                        <td><?= $escape($translate($formatLabels[$sample['Format']] ?? ucfirst($sample['Format']))) ?><br><small><?= $escape($translateIssue($sample['Issue'])) ?></small></td>
                        <td><pre><?= $escape($sample['Original']) ?></pre></td>
                        <td><div class="preview"><?= $sample['Converted'] ?? $escape($translate('Skipped for manual review')) ?></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
};

$showMigration = function (array $input = []) use ($renderMigration) {
    (\_::$Front->AdminView)(
        fn() => $renderMigration($input),
        ['Image' => 'exchange', 'Title' => 'Markdown Content Migration']
    );
};

(new Router())->if(\_::$User->HasAccess(\_::$User->AdminAccess))
    ->Get(fn() => $showMigration())
    ->Post(fn() => $showMigration(receivePost() ?: []))
    ->Default(fn() => response($renderMigration(receive() ?: [])))
    ->Handle();
