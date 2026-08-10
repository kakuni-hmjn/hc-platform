<?php

declare(strict_types=1);

$query = trim((string) ($_GET['q'] ?? ''));
$legacyStatus = trim((string) ($_GET['status'] ?? ''));

$status = match ($legacyStatus) {
    'open', 'in_progress', 'waiting' => $legacyStatus,
    'closed', 'resolved' => 'resolved',
    default => 'all',
};

$parameters = ['status' => $status];

if ($query !== '') {
    $parameters['q'] = mb_substr($query, 0, 120);
}

header(
    'Location: /staff/support/?' . http_build_query($parameters),
    true,
    302
);
exit;
