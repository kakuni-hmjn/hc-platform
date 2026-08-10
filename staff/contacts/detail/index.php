<?php

declare(strict_types=1);

$contactId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$parameters = [];

if (is_int($contactId) && $contactId > 0) {
    $parameters['id'] = $contactId;
}

$location = '/staff/support/';

if ($parameters !== []) {
    $location .= '?' . http_build_query($parameters);
}

header('Location: ' . $location, true, 302);
exit;
