<?php

declare(strict_types=1);

function staff_ui_escape(
    string|int|float|null $value
): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function staff_ui_summary_card(
    string $label,
    string|int $value,
    string $description,
    string $href = '#',
    string $variant = ''
): void {
    $classes = 'staff-summary-card';

    if ($variant !== '') {
        $classes .= ' staff-summary-card--'
            . preg_replace(
                '/[^a-z0-9_-]/i',
                '',
                $variant
            );
    }

    ?>
<a
    href="<?= staff_ui_escape($href) ?>"
    class="<?= staff_ui_escape($classes) ?>"
>
    <span>
        <?= staff_ui_escape($label) ?>
    </span>

    <strong>
        <?= staff_ui_escape($value) ?>
    </strong>

    <small>
        <?= staff_ui_escape($description) ?>
    </small>
</a>
<?php
}

function staff_ui_panel_start(
    string $title,
    string $description = '',
    string $actionLabel = '',
    string $actionHref = ''
): void {
    ?>
<section class="staff-panel">
    <header class="staff-panel__header">
        <div>
            <h3>
                <?= staff_ui_escape($title) ?>
            </h3>

            <?php if ($description !== ''): ?>
                <p>
                    <?= staff_ui_escape($description) ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if (
            $actionLabel !== ''
            && $actionHref !== ''
        ): ?>
            <a
                href="<?= staff_ui_escape(
                    $actionHref
                ) ?>"
            >
                <?= staff_ui_escape(
                    $actionLabel
                ) ?>
            </a>
        <?php endif; ?>
    </header>
<?php
}

function staff_ui_panel_end(): void
{
    ?>
</section>
<?php
}

function staff_ui_empty(
    string $title,
    string $description = ''
): void {
    ?>
<div class="staff-empty-state">
    <strong>
        <?= staff_ui_escape($title) ?>
    </strong>

    <?php if ($description !== ''): ?>
        <p>
            <?= staff_ui_escape($description) ?>
        </p>
    <?php endif; ?>
</div>
<?php
}

function staff_ui_status(
    string $label,
    string $status
): void {
    $safeStatus = preg_replace(
        '/[^a-z0-9_-]/i',
        '',
        $status
    );

    ?>
<span
    class="staff-status staff-status--<?= staff_ui_escape(
        $safeStatus
    ) ?>"
>
    <?= staff_ui_escape($label) ?>
</span>
<?php
}
