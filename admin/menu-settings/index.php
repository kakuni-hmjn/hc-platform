<?php
session_start();

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/permissions.php';
require_once __DIR__ . '/../lib/menu.php';

$currentUser = require_role('admin');

header('Location: /staff/admin/site/menu/', true, 302);
exit;

$pageTitle = '管理メニュー設定 | HC Platform';
$pageDescription = '管理ページのカテゴリと表示設定を管理します。';
$pageCss = '/admin/admin.css?v=20260718-8';

if (!isset($_SESSION['admin_menu_csrf'])) {
    $_SESSION['admin_menu_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = (string) $_SESSION['admin_menu_csrf'];
$config = admin_menu_load();

$flashMessage = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrfToken, $postedToken)) {
        $flashMessage = 'セキュリティトークンが一致しません。';
        $flashType = 'danger';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'bulk_assign_categories') {
            $routes = $_POST['routes'] ?? [];
            $categoryKeys = $_POST['category_keys'] ?? [];
            $descriptions = $_POST['descriptions'] ?? [];

            if (
                !is_array($routes)
                || !is_array($categoryKeys)
                || !is_array($descriptions)
            ) {
                $flashMessage = '送信された管理ページ設定が正しくありません。';
                $flashType = 'danger';
            } else {
                $updatedCount = 0;

                foreach ($routes as $index => $routeValue) {
                    $route = (string) $routeValue;
                    $categoryKey = (string) ($categoryKeys[$index] ?? '');
                    $description = trim(
                        (string) ($descriptions[$index] ?? '')
                    );

                    if (
                        !str_starts_with($route, '/admin/')
                        || !isset($config['categories'][$categoryKey])
                    ) {
                        continue;
                    }

                    $config['assignments'][$route] = $categoryKey;
                    $config['descriptions'][$route] = mb_substr(
                        $description,
                        0,
                        300
                    );

                    $updatedCount++;
                }

                if (admin_menu_save($config)) {
                    $flashMessage = $updatedCount
                        . '件の管理ページ設定を保存しました。';
                } else {
                    $flashMessage = '管理ページ設定を保存できませんでした。';
                    $flashType = 'danger';
                }
            }
        }

        if ($action === 'assign_category') {
            $route = (string) ($_POST['route'] ?? '');
            $categoryKey = (string) ($_POST['category_key'] ?? '');

            if (
                !str_starts_with($route, '/admin/')
                || !isset($config['categories'][$categoryKey])
            ) {
                $flashMessage = 'カテゴリ設定が正しくありません。';
                $flashType = 'danger';
            } else {
                $config['assignments'][$route] = $categoryKey;

                if (admin_menu_save($config)) {
                    $flashMessage = 'カテゴリを変更しました。';
                } else {
                    $flashMessage = '設定を保存できませんでした。';
                    $flashType = 'danger';
                }
            }
        }

        if ($action === 'add_category') {
            $name = trim((string) ($_POST['category_name'] ?? ''));
            $description = trim(
                (string) ($_POST['category_description'] ?? '')
            );

            if ($name === '') {
                $flashMessage = 'カテゴリ名を入力してください。';
                $flashType = 'danger';
            } else {
                $baseKey = admin_menu_slug($name);
                $categoryKey = $baseKey;
                $number = 2;

                while (isset($config['categories'][$categoryKey])) {
                    $categoryKey = $baseKey . '-' . $number;
                    $number++;
                }

                $highestOrder = 0;

                foreach ($config['categories'] as $category) {
                    $highestOrder = max(
                        $highestOrder,
                        (int) ($category['order'] ?? 0)
                    );
                }

                $config['categories'][$categoryKey] = [
                    'name' => $name,
                    'description' => $description,
                    'order' => $highestOrder + 10,
                ];

                if (admin_menu_save($config)) {
                    $flashMessage = 'カテゴリを追加しました。';
                } else {
                    $flashMessage = 'カテゴリを保存できませんでした。';
                    $flashType = 'danger';
                }
            }
        }

        if ($action === 'update_category') {
            $categoryKey = (string) ($_POST['category_key'] ?? '');
            $name = trim((string) ($_POST['category_name'] ?? ''));
            $description = trim(
                (string) ($_POST['category_description'] ?? '')
            );

            if (
                !isset($config['categories'][$categoryKey])
                || $name === ''
            ) {
                $flashMessage = 'カテゴリ情報が正しくありません。';
                $flashType = 'danger';
            } else {
                $config['categories'][$categoryKey]['name'] = $name;
                $config['categories'][$categoryKey]['description']
                    = $description;

                if (admin_menu_save($config)) {
                    $flashMessage = 'カテゴリを更新しました。';
                } else {
                    $flashMessage = 'カテゴリを保存できませんでした。';
                    $flashType = 'danger';
                }
            }
        }


        if ($action === 'move_category') {
            $categoryKey = (string) ($_POST['category_key'] ?? '');
            $direction = (string) ($_POST['direction'] ?? '');

            if (
                !isset($config['categories'][$categoryKey])
                || !in_array($direction, ['up', 'down'], true)
            ) {
                $flashMessage = 'カテゴリの移動情報が正しくありません。';
                $flashType = 'danger';
            } else {
                $config['categories'] = admin_menu_move_category(
                    $config['categories'],
                    $categoryKey,
                    $direction
                );

                if (admin_menu_save($config)) {
                    $flashMessage = 'カテゴリの順番を変更しました。';
                } else {
                    $flashMessage = 'カテゴリ順を保存できませんでした。';
                    $flashType = 'danger';
                }
            }
        }

        if ($action === 'delete_category') {
            $categoryKey = (string) ($_POST['category_key'] ?? '');

            if ($categoryKey === 'uncategorized') {
                $flashMessage = '未分類カテゴリは削除できません。';
                $flashType = 'danger';
            } elseif (!isset($config['categories'][$categoryKey])) {
                $flashMessage = 'カテゴリが見つかりません。';
                $flashType = 'danger';
            } else {
                unset($config['categories'][$categoryKey]);

                foreach ($config['assignments'] as $route => $assigned) {
                    if ($assigned === $categoryKey) {
                        $config['assignments'][$route] = 'uncategorized';
                    }
                }

                if (admin_menu_save($config)) {
                    $flashMessage = 'カテゴリを削除しました。';
                } else {
                    $flashMessage = 'カテゴリを削除できませんでした。';
                    $flashType = 'danger';
                }
            }
        }
    }

    $config = admin_menu_load();
}

$adminPages = admin_menu_detect_pages($config);

require_once __DIR__ . '/../../parts/head.php';
?>
<body>
<?php include __DIR__ . '/../../parts/header/header.php'; ?>

<main class="admin-page">
    <div class="admin-container">
        <div class="admin-page-heading">
            <div>
                <p class="admin-eyebrow">ADMIN MENU SETTINGS</p>
                <h1>カテゴリ・表示設定</h1>
                <p>
                    管理ページのカテゴリ分けとカテゴリ自体の管理を行います。
                </p>
            </div>

            <a class="admin-button" href="/admin/">
                管理トップへ戻る
            </a>
        </div>

        <?php if ($flashMessage !== ''): ?>
            <div class="admin-alert admin-alert--<?= htmlspecialchars(
                $flashType,
                ENT_QUOTES,
                'UTF-8'
            ) ?>">
                <?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="admin-settings-section">
            <div class="admin-settings-heading">
                <div>
                    <h2>管理ページ一覧</h2>
                    <p>
                        <?= count($adminPages) ?>件の管理ページを検出しました。
                    </p>
                </div>

                <input
                    type="search"
                    class="admin-settings-search"
                    placeholder="ページ名・URLを検索"
                    data-admin-page-search
                >
            </div>

            <form method="post" class="admin-bulk-category-form">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $csrfToken,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
                <input
                    type="hidden"
                    name="action"
                    value="bulk_assign_categories"
                >

                <div class="admin-horizontal-list">
                    <div class="admin-horizontal-list__header">
                        <span>管理ページ</span>
                        <span>説明文</span>
                        <span>カテゴリ</span>
                        <span>ページ</span>
                    </div>

                    <?php foreach ($adminPages as $page): ?>
                        <div
                            class="admin-horizontal-row"
                            data-admin-page-row
                            data-search-text="<?= htmlspecialchars(
                                mb_strtolower(
                                    $page['title']
                                    . ' '
                                    . $page['route']
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >
                            <input
                                type="hidden"
                                name="routes[]"
                                value="<?= htmlspecialchars(
                                    $page['route'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                            <div class="admin-horizontal-row__page">
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
                            </div>

                            <div class="admin-page-description-field">
                                <textarea
                                    name="descriptions[]"
                                    rows="2"
                                    maxlength="300"
                                    data-admin-description-input
                                ><?= htmlspecialchars(
                                    $page['description'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?></textarea>

                                <code>
                                    <?= htmlspecialchars(
                                        $page['route'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </code>
                            </div>

                            <select
                                name="category_keys[]"
                                class="admin-select admin-select--small"
                                data-admin-category-select
                            >
                                <?php foreach (
                                    $config['categories']
                                    as $key => $category
                                ): ?>
                                    <option
                                        value="<?= htmlspecialchars(
                                            $key,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        <?= $key === $page['category']
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= htmlspecialchars(
                                            (string) $category['name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div class="admin-horizontal-row__actions">
                                <a
                                    class="admin-button"
                                    href="<?= htmlspecialchars(
                                        $page['route'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >
                                    開く
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="admin-bulk-save-bar">
                    <div class="admin-bulk-save-bar__text">
                        <strong>カテゴリ変更をまとめて保存</strong>
                        <span>
                            上の一覧で変更した内容を一度に反映します。
                        </span>
                    </div>

                    <button
                        type="submit"
                        class="admin-button admin-button--primary
                               admin-button--save-all"
                    >
                        変更をすべて保存
                    </button>
                </div>
            </form>
        </section>

        <section class="admin-settings-section">
            <div class="admin-settings-heading">
                <div>
                    <h2>カテゴリ一覧</h2>
                    <p>カテゴリの追加、名称変更、削除ができます。</p>
                </div>
            </div>

            <form method="post" class="admin-category-add-form">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $csrfToken,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
                <input type="hidden" name="action" value="add_category">

                <input
                    type="text"
                    name="category_name"
                    placeholder="新しいカテゴリ名"
                    required
                >

                <input
                    type="text"
                    name="category_description"
                    placeholder="カテゴリの説明"
                >

                <button
                    type="submit"
                    class="admin-button admin-button--primary"
                >
                    カテゴリ追加
                </button>
            </form>

            <div class="admin-category-manage-list">
                <?php
                $categoryKeys = array_keys($config['categories']);
                $lastCategoryIndex = count($categoryKeys) - 1;
                ?>

                <?php foreach (
                    $config['categories']
                    as $categoryIndex => $category
                ): ?>
                    <?php
                    $key = (string) $categoryIndex;
                    $numericIndex = array_search(
                        $key,
                        $categoryKeys,
                        true
                    );
                    ?>

                    <div class="admin-category-manage-row">
                        <div class="admin-category-order">
                            <span class="admin-category-order__number">
                                <?= str_pad(
                                    (string) (($numericIndex ?? 0) + 1),
                                    2,
                                    '0',
                                    STR_PAD_LEFT
                                ) ?>
                            </span>

                            <div class="admin-category-order__buttons">
                                <form method="post">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars(
                                            $csrfToken,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="move_category"
                                    >
                                    <input
                                        type="hidden"
                                        name="category_key"
                                        value="<?= htmlspecialchars(
                                            $key,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="direction"
                                        value="up"
                                    >

                                    <button
                                        type="submit"
                                        class="admin-order-button"
                                        aria-label="上へ移動"
                                        <?= $numericIndex === 0
                                            ? 'disabled'
                                            : '' ?>
                                    >
                                        ↑
                                    </button>
                                </form>

                                <form method="post">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars(
                                            $csrfToken,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="move_category"
                                    >
                                    <input
                                        type="hidden"
                                        name="category_key"
                                        value="<?= htmlspecialchars(
                                            $key,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="direction"
                                        value="down"
                                    >

                                    <button
                                        type="submit"
                                        class="admin-order-button"
                                        aria-label="下へ移動"
                                        <?= $numericIndex === $lastCategoryIndex
                                            ? 'disabled'
                                            : '' ?>
                                    >
                                        ↓
                                    </button>
                                </form>
                            </div>
                        </div>

                        <form
                            method="post"
                            class="admin-category-manage-row__content"
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(
                                    $csrfToken,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >
                            <input
                                type="hidden"
                                name="category_key"
                                value="<?= htmlspecialchars(
                                    $key,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                            <input
                                type="text"
                                name="category_name"
                                value="<?= htmlspecialchars(
                                    (string) $category['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                required
                            >

                            <input
                                type="text"
                                name="category_description"
                                value="<?= htmlspecialchars(
                                    (string) (
                                        $category['description']
                                        ?? ''
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                            <div class="admin-horizontal-row__actions">
                                <button
                                    type="submit"
                                    name="action"
                                    value="update_category"
                                    class="admin-button"
                                >
                                    更新
                                </button>

                                <?php if ($key !== 'uncategorized'): ?>
                                    <button
                                        type="submit"
                                        name="action"
                                        value="delete_category"
                                        class="admin-button
                                               admin-button--danger"
                                        onclick="
                                            return confirm(
                                                'このカテゴリを削除しますか？'
                                            );
                                        "
                                    >
                                        削除
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</main>

<?php include __DIR__ . '/../../parts/footer/footer.php'; ?>

<script src="/common/base.js"></script>
<script src="/admin/admin.js?v=20260718-8"></script>
</body>
</html>
