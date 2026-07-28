<?php

declare(strict_types=1);

require_once __DIR__ . '/menu.php';
require_once __DIR__ . '/helpers.php';

$boConsole = $boConsole ?? 'admin';
$boBadges = is_array($boBadges ?? null) ? $boBadges : [];
$boMenu = hc_backoffice_menu($boConsole);
$boIsDeveloper = hc_bo_role_is_developer($user);
?>

<aside class="hc-bo-sidebar" id="hcBoSidebar">
    <div class="hc-bo-brand">
        <a href="<?php echo $boConsole === 'staff' ? '/staff/' : '/admin/'; ?>">
            <span class="hc-bo-brand-mark">HC</span>

            <span>
                <strong>
                    <?php echo $boConsole === 'staff'
                        ? 'Staff'
                        : 'Admin'; ?>
                </strong>
                <small>HC Platform</small>
            </span>
        </a>

        <button
            type="button"
            class="hc-bo-sidebar-close"
            data-bo-sidebar-close
            aria-label="メニューを閉じる"
        >
            ×
        </button>
    </div>

    <nav class="hc-bo-nav" aria-label="管理メニュー">
        <?php foreach ($boMenu as $section): ?>
            <?php
            if (
                !empty($section['developer_only'])
                && !$boIsDeveloper
            ) {
                continue;
            }
            ?>

            <section class="hc-bo-nav-section">
                <p class="hc-bo-nav-label">
                    <?php echo h((string)$section['label']); ?>
                </p>

                <div class="hc-bo-nav-items">
                    <?php foreach ($section['items'] as $item): ?>
                        <?php
                        $href = (string)$item['href'];
                        $badgeKey = (string)($item['badge'] ?? '');
                        $badgeValue = $badgeKey !== ''
                            ? (int)($boBadges[$badgeKey] ?? 0)
                            : 0;
                        $external = !empty($item['external']);
                        ?>

                        <a
                            href="<?php echo h($href); ?>"
                            class="hc-bo-nav-item <?php echo hc_bo_is_active($href) ? 'is-active' : ''; ?>"
                            <?php echo $external ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
                        >
                            <span
                                class="hc-bo-nav-icon"
                                data-bo-icon="<?php echo h((string)$item['icon']); ?>"
                            ></span>

                            <span class="hc-bo-nav-text">
                                <?php echo h((string)$item['label']); ?>
                            </span>

                            <?php if ($badgeValue > 0): ?>
                                <strong class="hc-bo-nav-badge">
                                    <?php echo h((string)$badgeValue); ?>
                                </strong>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </nav>

    <div class="hc-bo-user">
        <div class="hc-bo-user-avatar">
            <?php
            echo h(
                mb_strtoupper(
                    mb_substr(
                        (string)($user['username'] ?? 'U'),
                        0,
                        1
                    )
                )
            );
            ?>
        </div>

        <div>
            <strong>
                <?php echo h((string)($user['username'] ?? 'User')); ?>
            </strong>

            <small>
                <?php echo h(role_label((string)($user['role'] ?? 'user'))); ?>
            </small>
        </div>
    </div>
</aside>

<div class="hc-bo-sidebar-overlay" data-bo-sidebar-close></div>
