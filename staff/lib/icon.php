<?php

declare(strict_types=1);

/**
 * Material Iconsを出力する。
 *
 * 使用例:
 * <?= staff_icon('notifications') ?>
 * <?= staff_icon('home', 'staff-nav-icon', 20) ?>
 */
function staff_icon(
    string $name,
    string $class = '',
    int $size = 20,
    array $attributes = []
): string {
    $name = trim($name);

    if ($name === '') {
        $name = 'circle';
    }

    $classes = trim(
        'material-icons ' . $class
    );

    $attributeParts = [
        'class="' . htmlspecialchars(
            $classes,
            ENT_QUOTES,
            'UTF-8'
        ) . '"',
        'style="font-size:' . max(1, $size) . 'px"',
        'aria-hidden="true"',
    ];

    foreach ($attributes as $key => $value) {
        $key = trim((string) $key);

        if (
            $key === ''
            || !preg_match(
                '/^[a-zA-Z_:][a-zA-Z0-9_:.-]*$/',
                $key
            )
        ) {
            continue;
        }

        if (is_bool($value)) {
            if ($value) {
                $attributeParts[] = htmlspecialchars(
                    $key,
                    ENT_QUOTES,
                    'UTF-8'
                );
            }

            continue;
        }

        if ($value === null) {
            continue;
        }

        $attributeParts[] =
            htmlspecialchars(
                $key,
                ENT_QUOTES,
                'UTF-8'
            )
            . '="'
            . htmlspecialchars(
                (string) $value,
                ENT_QUOTES,
                'UTF-8'
            )
            . '"';
    }

    return sprintf(
        '<span %s>%s</span>',
        implode(' ', $attributeParts),
        htmlspecialchars(
            $name,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}
