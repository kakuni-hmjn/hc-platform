<?php

declare(strict_types=1);

$currentPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/staff/',
    PHP_URL_PATH
);

$currentPath = is_string($currentPath)
    ? staff_sidebar_normalize_path($currentPath)
    : '/staff/';

$staffAvatarUrl = staff_workspace_asset_url(
    $staffWorkspacePreferences['avatar_image_path'] ?? null
);

function staff_sidebar_initial(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        return 'S';
    }

    return mb_strtoupper(
        mb_substr($name, 0, 1, 'UTF-8'),
        'UTF-8'
    );
}

function staff_sidebar_normalize_path(string $path): string
{
    $path = parse_url($path, PHP_URL_PATH);

    if (!is_string($path) || $path === '') {
        return '/';
    }

    return rtrim($path, '/') . '/';
}

function staff_sidebar_item_is_exact_active(
    array $item,
    string $currentPath
): bool {
    return $currentPath === staff_sidebar_normalize_path(
        (string) ($item['href'] ?? '/staff/')
    );
}

function staff_sidebar_item_contains_active(
    array $item,
    string $currentPath
): bool {
    if (
        staff_sidebar_item_is_exact_active(
            $item,
            $currentPath
        )
    ) {
        return true;
    }

    foreach ((array) ($item['children'] ?? []) as $child) {
        if (
            staff_sidebar_item_contains_active(
                $child,
                $currentPath
            )
        ) {
            return true;
        }
    }

    return false;
}

function staff_sidebar_render_items(
    array $items,
    string $currentPath,
    int $depth = 0,
    string $parentKey = 'root'
): void {
    foreach ($items as $index => $item) {
        $href = (string) ($item['href'] ?? '/staff/');
        $children = (array) ($item['children'] ?? []);

        $hasChildren = $children !== [];

        $isExactActive = staff_sidebar_item_is_exact_active(
            $item,
            $currentPath
        );

        $containsActive = staff_sidebar_item_contains_active(
            $item,
            $currentPath
        );

        $isExpanded = $hasChildren && $containsActive;

        $itemKey = $parentKey . '-' . $index;
        $panelId = 'staff-nav-panel-' . preg_replace(
            '/[^a-zA-Z0-9_-]/',
            '-',
            $itemKey
        );

        ?>
        <div
            class="staff-nav-entry
                   staff-nav-entry--depth-<?= $depth ?>
                   <?= $isExpanded ? 'is-expanded' : '' ?>"
            data-staff-nav-entry
            data-staff-nav-key="<?= htmlspecialchars(
                $itemKey,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >
            <div class="staff-nav-entry__row">
                <a
                    href="<?= htmlspecialchars(
                        $href,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    class="staff-nav-item
                           <?= $isExactActive
                               ? 'staff-nav-item--active'
                               : '' ?>
                           <?= $containsActive && !$isExactActive
                               ? 'staff-nav-item--ancestor'
                               : '' ?>
                           <?= $hasChildren
                               ? 'staff-nav-item--parent'
                               : '' ?>"
                    <?= $isExactActive
                        ? 'aria-current="page"'
                        : '' ?>
                >
                    <?php if ($depth <= 1): ?>
                        <span class="staff-nav-item__icon">
                            <?= staff_icon(
                                (string) (
                                    $item['icon'] ?? 'circle'
                                ),
                                'staff-nav-icon',
                                $depth === 0 ? 19 : 17
                            ) ?>
                        </span>
                    <?php else: ?>
                        <span
                            class="staff-nav-item__bullet"
                            aria-hidden="true"
                        ></span>
                    <?php endif; ?>

                    <span class="staff-nav-item__label">
                        <?= htmlspecialchars(
                            (string) ($item['label'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>
                </a>

                <?php if ($hasChildren): ?>
                    <button
                        type="button"
                        class="staff-nav-toggle"
                        data-staff-nav-toggle
                        aria-expanded="<?= $isExpanded
                            ? 'true'
                            : 'false' ?>"
                        aria-controls="<?= htmlspecialchars(
                            $panelId,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        aria-label="<?= htmlspecialchars(
                            (string) ($item['label'] ?? '')
                            . 'を開閉',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >
                        <?= staff_icon(
                            'chevron_right',
                            'staff-nav-toggle__icon',
                            18
                        ) ?>
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($hasChildren): ?>
                <div
                    id="<?= htmlspecialchars(
                        $panelId,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    class="staff-nav-children
                           staff-nav-children--depth-<?= $depth + 1 ?>"
                    data-staff-nav-children
                    <?= $isExpanded ? '' : 'hidden' ?>
                >
                    <div class="staff-nav-children__inner">
                        <?php staff_sidebar_render_items(
                            $children,
                            $currentPath,
                            $depth + 1,
                            $itemKey
                        ); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
?>

<aside
    id="staff-sidebar"
    class="staff-sidebar"
    data-staff-sidebar
>
    <div class="staff-sidebar__brand">
        <a
            href="/staff/"
            class="staff-sidebar__brand-link"
            aria-label="スタッフコンソール ホーム"
        >
            <span class="staff-brand-mark" aria-hidden="true">
                <img
                    src="/assets/logo.png"
                    alt=""
                    width="38"
                    height="38"
                    draggable="false"
                >
            </span>

            <div class="staff-brand-copy">
                <strong>Staff Console</strong>
                <span>HC Platform</span>
            </div>
        </a>

        <button
            type="button"
            class="staff-sidebar__close"
            data-staff-sidebar-close
            aria-label="メニューを閉じる"
        >
            <?= staff_icon(
                'close',
                'staff-sidebar__close-icon',
                22
            ) ?>
        </button>
    </div>

    <nav
        class="staff-sidebar__nav"
        aria-label="スタッフメニュー"
        data-staff-navigation
    >
        <?php foreach ($staffNavigation as $groupIndex => $group): ?>
            <section class="staff-nav-group">
                <p class="staff-nav-group__label">
                    <?= htmlspecialchars(
                        (string) $group['label'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

                <div class="staff-nav-group__items">
                    <?php staff_sidebar_render_items(
                        (array) $group['items'],
                        $currentPath,
                        0,
                        'group-' . $groupIndex
                    ); ?>
                </div>
            </section>
        <?php endforeach; ?>



        <?php require __DIR__
    . '/hpmc-sidebar-menu.php'; ?>

    </nav>

    <div class="staff-sidebar__account">
        <div class="staff-account-avatar">
            <?php if ($staffAvatarUrl !== null): ?>
                <img src="<?= htmlspecialchars($staffAvatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
            <?php else: ?>
                <?= htmlspecialchars(
                    staff_sidebar_initial($staffDisplayName),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            <?php endif; ?>
        </div>

        <div class="staff-sidebar__account-copy">
            <strong>
                <?= htmlspecialchars(
                    $staffDisplayName,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>

            <span>
                <?= htmlspecialchars(
                    $staffRoleName,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>
        </div>

        <a
            href="/staff/account/"
            class="staff-sidebar__account-action"
            aria-label="スタッフアカウント設定"
            title="アカウント設定"
        >
            <?= staff_icon(
                'manage_accounts',
                '',
                19
            ) ?>
        </a>
    </div>
</aside>

<div
    class="staff-sidebar-backdrop"
    data-staff-sidebar-backdrop
></div>
