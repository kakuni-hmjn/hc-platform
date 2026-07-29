<?php

declare(strict_types=1);

function staff_layout_start(array $options = []): void
{
    global $staffContext;
    global $staffDisplayName;
    global $staffRoleName;
    global $staffRoleSlug;
    global $staffNavigation;

    $title = trim(
        (string) (
            $options['title']
            ?? 'スタッフコンソール'
        )
    );

    $heading = trim(
        (string) (
            $options['heading']
            ?? $title
        )
    );

    $eyebrow = trim(
        (string) (
            $options['eyebrow']
            ?? 'HC STAFF WORKSPACE'
        )
    );

    $description = trim(
        (string) (
            $options['description']
            ?? ''
        )
    );

    $showHeading = (bool) (
        $options['show_heading']
        ?? true
    );

    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta name="robots" content="noindex,nofollow">

    <title>
        <?= htmlspecialchars(
            $title,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
        | HC Platform
    </title>

    <link
        href="https://fonts.googleapis.com/icon?family=Material+Icons"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="/staff/staff.css?v=1784437057"
    >

    <link
        rel="stylesheet"
        href="/staff/navigation.css?v=1"
    >
</head>
<body>
    <div class="staff-shell">
        <?php require __DIR__
            . '/../parts/sidebar.php'; ?>

        <div class="staff-workspace">
            <?php require __DIR__
                . '/../parts/topbar.php'; ?>

            <main
                class="staff-main"
                data-staff-main
            >
                <div
                    class="staff-page"
                    data-staff-page
                >
                <?php if ($showHeading): ?>
                    <section class="staff-page-heading">
                        <div>
                            <?php if ($eyebrow !== ''): ?>
                                <p class="staff-page-heading__eyebrow">
                                    <?= htmlspecialchars(
                                        $eyebrow,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </p>
                            <?php endif; ?>

                            <h2>
                                <?= htmlspecialchars(
                                    $heading,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </h2>

                            <?php if ($description !== ''): ?>
                                <p class="staff-page-heading__description">
                                    <?= htmlspecialchars(
                                        $description,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="staff-page-heading__status">
                            <span></span>

                            <?= htmlspecialchars(
                                (string) $staffRoleName,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>
                    </section>
                <?php endif; ?>
<?php
}

function staff_layout_end(): void
{
    ?>
                </div>
            </main>
        </div>
    </div>

    <div
        class="staff-navigation-progress"
        data-staff-navigation-progress
        aria-hidden="true"
    >
        <span></span>
    </div>

    <?php require __DIR__
        . '/search-palette.php'; ?>

    <script
        src="/staff/navigation.js?v=1784437057"
        defer
    ></script>

    <script src="/staff/staff.js?v=1785294209"></script>
    <script src="/staff/account-menu.js?v=1784391309" defer></script>
    <script src="/staff/search-palette.js?v=1784437057" defer></script>
</body>
</html>
<?php
}
