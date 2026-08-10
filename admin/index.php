<?php
session_start();

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/permissions.php';
require_once __DIR__ . '/lib/menu.php';

$currentUser = require_role('admin');

if (!isset($_GET['legacy'])) {
    header('Location: /staff/admin/', true, 302);
    exit;
}

$pageTitle = '管理ダッシュボード | HC Platform';
$pageDescription = 'HC Platformの管理機能一覧です。';
$pageCss = '/admin/admin.css?v=20260718-8';

$config = admin_menu_load();
$adminPages = admin_menu_detect_pages($config);
$pagesByCategory = admin_menu_group_pages(
    $adminPages,
    $config['categories']
);

$totalPageCount = count($adminPages);
$totalCategoryCount = count(
    array_filter(
        $pagesByCategory,
        static fn(array $pages): bool => $pages !== []
    )
);
$uncategorizedCount = count(
    $pagesByCategory['uncategorized'] ?? []
);

$adminName = trim((string) (
    $currentUser['display_name']
    ?? $currentUser['username']
    ?? $currentUser['name']
    ?? 'Administrator'
));

require_once __DIR__ . '/../parts/head.php';
?>
<body>
<?php include __DIR__ . '/../parts/header/header.php'; ?>

<main class="admin-page">
    <div class="admin-container">
        <section class="admin-hero">
            <div class="admin-hero__content">
                <p class="admin-eyebrow">HC PLATFORM ADMINISTRATION</p>
                <h1>管理ダッシュボード</h1>

                <p class="admin-hero__description">
                    HC Platformの注文、ユーザー、事業、サービス、
                    決済、システム機能を管理します。
                </p>
            </div>

            <div class="admin-hero__account">
                <span class="admin-hero__account-label">ログイン中</span>
                <strong>
                    <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?>
                </strong>
                <span class="admin-role-badge">管理者</span>
            </div>
        </section>

        <section class="admin-control-panel">
            <div class="admin-control-panel__main">
                <label for="admin-category-filter">表示カテゴリ</label>

                <select
                    id="admin-category-filter"
                    class="admin-select"
                    data-admin-category-filter
                >
                    <option value="all">
                        すべての管理機能（<?= count($adminPages) ?>件）
                    </option>

                    <?php foreach ($config['categories'] as $key => $category): ?>
                        <option
                            value="<?= htmlspecialchars(
                                $key,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >
                            <?= htmlspecialchars(
                                (string) $category['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                            （<?= count($pagesByCategory[$key] ?? []) ?>件）
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <a
                class="admin-button admin-button--primary"
                href="/admin/menu-settings/"
            >
                カテゴリ・表示設定
            </a>
        </section>

        <?php
        $visibleCategoryNumber = 0;
        ?>

        <?php foreach ($config['categories'] as $categoryKey => $category): ?>
            <?php $categoryPages = $pagesByCategory[$categoryKey] ?? []; ?>

            <?php if ($categoryPages === []): ?>
                <?php continue; ?>
            <?php endif; ?>

            <?php $visibleCategoryNumber++; ?>

            <section
                class="admin-section admin-category-section"
                data-admin-category="<?= htmlspecialchars(
                    $categoryKey,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >
                <div class="admin-section__header">
                    <div>
                        <p class="admin-section__eyebrow">
                            CATEGORY
                            <?= str_pad(
                                (string) $visibleCategoryNumber,
                                2,
                                '0',
                                STR_PAD_LEFT
                            ) ?>
                            ·
                            <?= count($categoryPages) ?> ITEMS
                        </p>

                        <h2>
                            <?= htmlspecialchars(
                                (string) $category['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </h2>

                        <p class="admin-category-description">
                            <?= htmlspecialchars(
                                (string) ($category['description'] ?? ''),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>
                    </div>
                </div>

                <div class="admin-menu-grid">
                    <?php foreach ($categoryPages as $page): ?>
                        <a
                            class="admin-menu-card"
                            href="<?= htmlspecialchars(
                                $page['route'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >
                            <span class="admin-menu-card__content">
                                <strong>
                                    <?= htmlspecialchars(
                                        $page['title'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>

                                <small>
                                    <?= htmlspecialchars(
                                        $page['description'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </small>
                            </span>

                            <span class="admin-menu-card__arrow">→</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</main>

<?php include __DIR__ . '/../parts/footer/footer.php'; ?>

<script src="/common/base.js"></script>
<script src="/admin/admin.js?v=20260718-8"></script>
</body>
</html>
