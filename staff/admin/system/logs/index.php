<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 3) . '/lib/administration.php';
require_once dirname(__DIR__, 3) . '/components/layout.php';
require_once dirname(__DIR__, 3) . '/components/ui.php';

staff_administration_require_admin($staffContext);

$allowedLogs = [
    'mail-error' => [
        'label' => 'メールエラーログ',
        'file' => dirname(__DIR__, 4) . '/storage/logs/mail-error.log',
        'description' => 'SMTP送信失敗など、メール送信系のエラーを表示します。',
    ],
    'mail' => [
        'label' => '認証メールログ',
        'file' => dirname(__DIR__, 4) . '/storage/logs/mail.log',
        'description' => 'ローカル開発環境で記録されたメール送信内容を表示します。',
    ],
];
$selected = (string) ($_GET['log'] ?? 'mail-error');
if (!isset($allowedLogs[$selected])) {
    $selected = 'mail-error';
}
$current = $allowedLogs[$selected];
$exists = is_file($current['file']) && is_readable($current['file']);
$content = '';
if ($exists) {
    $lines = file($current['file'], FILE_IGNORE_NEW_LINES);
    if (is_array($lines)) {
        $content = implode(PHP_EOL, array_slice($lines, -200));
    }
}

staff_layout_start([
    'title' => '開発ログ', 'heading' => '開発ログ', 'eyebrow' => 'SYSTEM / LOGS',
    'description' => '開発・保守向けログの直近200行を、スタッフコンソール内で確認します。',
]);
?>
<div class="ops-page admin-native-page">
    <nav class="ops-tabs" aria-label="ログ種別">
        <?php foreach ($allowedLogs as $key => $definition): ?>
            <a class="<?= $selected === $key ? 'is-active' : '' ?>" href="?log=<?= staff_ui_escape($key) ?>"><?= staff_ui_escape($definition['label']) ?></a>
        <?php endforeach; ?>
    </nav>
    <section class="ops-panel">
        <header class="ops-panel__header"><div><h3><?= staff_ui_escape($current['label']) ?></h3><p><?= staff_ui_escape($current['description']) ?></p></div><a class="ops-button ops-button--secondary" href="?log=<?= staff_ui_escape($selected) ?>">再読み込み</a></header>
        <div class="ops-panel__body">
            <dl class="ops-kv"><div><dt>ファイル</dt><dd>storage/logs/<?= staff_ui_escape(basename($current['file'])) ?></dd></div><div><dt>状態</dt><dd><?= $exists ? '読み取り可能' : 'まだ作成されていません' ?></dd></div></dl>
            <div class="ops-section">
                <?php if (!$exists): ?><div class="ops-empty">ログファイルはまだありません。</div>
                <?php elseif ($content === ''): ?><div class="ops-empty">ログ内容は空です。</div>
                <?php else: ?><pre class="ops-code admin-native-log"><?= staff_ui_escape($content) ?></pre><?php endif; ?>
            </div>
        </div>
    </section>
</div>
<?php staff_layout_end();
