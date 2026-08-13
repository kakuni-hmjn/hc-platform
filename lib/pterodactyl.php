<?php

function hc_ptero_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);

    if ($value !== false && $value !== "") {
        return $value;
    }

    $paths = [
        __DIR__ . "/../.env",
        __DIR__ . "/../docker/.env",
    ];

    foreach ($paths as $path) {
        if (!is_file($path)) {
            continue;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === "" || str_starts_with($line, "#") || !str_contains($line, "=")) {
                continue;
            }

            [$envKey, $envValue] = explode("=", $line, 2);

            if (trim($envKey) === $key) {
                return trim($envValue, " \t\n\r\0\x0B\"'");
            }
        }
    }

    return $default;
}

function hc_ptero_bool(string $key, bool $default = false): bool
{
    $value = hc_ptero_env($key);

    if ($value === null) {
        return $default;
    }

    return in_array(strtolower($value), ["1", "true", "yes", "on"], true);
}

function hc_ptero_enabled(): bool
{
    return hc_ptero_bool("PTERO_ENABLED", false);
}

function hc_ptero_mock(): bool
{
    return hc_ptero_bool("PTERO_MOCK", true);
}

function hc_ptero_panel_url(): string
{
    $url = hc_ptero_env("PTERO_PANEL_URL");

    if (!$url) {
        throw new RuntimeException("PTERO_PANEL_URL が設定されていません。");
    }

    return rtrim($url, "/");
}

function hc_ptero_api_key(): string
{
    $key = hc_ptero_env("PTERO_API_KEY");

    if (!$key) {
        throw new RuntimeException("PTERO_API_KEY が設定されていません。");
    }

    if (!str_starts_with($key, "ptla_")) {
        throw new RuntimeException("PTERO_API_KEY は Application API キー ptla_... を指定してください。");
    }

    return $key;
}

function hc_ptero_request(string $method, string $path, array $payload = []): array
{
    if (!hc_ptero_enabled()) {
        throw new RuntimeException("Pterodactyl連携が無効です。PTERO_ENABLED=true にしてください。");
    }

    if (hc_ptero_mock()) {
        if (str_contains($path, "/allocations")) {
            return [
                "object" => "list",
                "data" => [
                    [
                        "object" => "allocation",
                        "attributes" => [
                            "id" => random_int(1000, 9999),
                            "ip" => "127.0.0.1",
                            "alias" => null,
                            "port" => random_int(20000, 30000),
                            "assigned" => false,
                        ],
                    ],
                ],
                "meta" => [
                    "pagination" => [
                        "total_pages" => 1,
                        "current_page" => 1,
                    ],
                ],
            ];
        }

        if ($method === "POST" && $path === "/api/application/users") {
            return [
                "object" => "user",
                "attributes" => [
                    "id" => random_int(1000, 9999),
                    "uuid" => bin2hex(random_bytes(16)),
                    "username" => $payload["username"] ?? "mock_user",
                    "email" => $payload["email"] ?? "mock@example.com",
                ],
            ];
        }

        if (str_contains($path, "/api/application/users/external/")) {
            throw new RuntimeException("Mock external user not found.");
        }

        return [
            "object" => "server",
            "attributes" => [
                "id" => random_int(10000, 99999),
                "uuid" => bin2hex(random_bytes(16)),
                "identifier" => substr(bin2hex(random_bytes(8)), 0, 8),
                "name" => $payload["name"] ?? "Mock Server",
            ],
        ];
    }

    $method = strtoupper($method);
    $url = hc_ptero_panel_url() . $path;
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

    if ($body === false) {
        throw new RuntimeException("Pterodactyl API送信用JSONを作成できませんでした。");
    }

    $headers = [
        "Authorization: Bearer " . hc_ptero_api_key(),
        "Accept: Application/vnd.pterodactyl.v1+json",
        "Content-Type: application/json",
    ];

    if (function_exists("curl_init")) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 45,
        ]);

        if ($method !== "GET") {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($responseBody === false) {
            throw new RuntimeException("Pterodactyl API通信に失敗しました: " . $curlError);
        }
    } else {
        $context = stream_context_create([
            "http" => [
                "method" => $method,
                "header" => implode("\r\n", $headers),
                "content" => $method === "GET" ? "" : $body,
                "timeout" => 45,
                "ignore_errors" => true,
            ],
        ]);

        $responseBody = file_get_contents($url, false, $context);
        $statusCode = 0;

        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $statusCode = (int)$matches[1];
        }

        if ($responseBody === false) {
            throw new RuntimeException("Pterodactyl API通信に失敗しました。");
        }
    }

    /*
     * Pterodactyl Application API は suspend / unsuspend / delete 等で
     * HTTP 204 No Content を正常レスポンスとして返す。
     * 2xx + 空bodyは成功として扱う。
     */
    if ($statusCode >= 200 && $statusCode < 300) {
        if ($statusCode === 204 || trim((string)$responseBody) === "") {
            return [];
        }

        $decoded = json_decode($responseBody, true);

        if (!is_array($decoded)) {
            throw new RuntimeException(
                "Pterodactyl APIレスポンスを解析できませんでした。HTTP {$statusCode}"
            );
        }

        return $decoded;
    }

    $decoded = json_decode($responseBody, true);

    $message = is_array($decoded)
        ? (
            $decoded["errors"][0]["detail"]
            ?? $decoded["errors"][0]["title"]
            ?? $decoded["message"]
            ?? "Pterodactyl APIエラーが発生しました。HTTP {$statusCode}"
        )
        : "Pterodactyl APIエラーが発生しました。HTTP {$statusCode}";

    throw new RuntimeException($message);
}

function hc_ptero_create_server(array $payload): array
{
    return hc_ptero_request("POST", "/api/application/servers", $payload);
}

function hc_ptero_get_user_by_external_id(string $externalId): array
{
    return hc_ptero_request("GET", "/api/application/users/external/" . rawurlencode($externalId));
}

function hc_ptero_create_user(array $payload): array
{
    return hc_ptero_request("POST", "/api/application/users", $payload);
}

function hc_ptero_list_node_allocations(int $pteroNodeId, int $page = 1): array
{
    if ($pteroNodeId <= 0) {
        throw new RuntimeException("Pterodactyl Node ID が不正です。");
    }

    return hc_ptero_request("GET", "/api/application/nodes/" . rawurlencode((string)$pteroNodeId) . "/allocations?page=" . rawurlencode((string)$page));
}

function hc_ptero_allocation_is_free(array $allocation): bool
{
    $attributes = $allocation["attributes"] ?? $allocation;

    if (!array_key_exists("assigned", $attributes)) {
        return false;
    }

    $assigned = $attributes["assigned"];

    return $assigned === false
        || $assigned === null
        || $assigned === 0
        || $assigned === "0"
        || $assigned === "";
}

function hc_ptero_find_free_allocation(int $pteroNodeId): array
{
    if (hc_ptero_mock()) {
        $response = hc_ptero_list_node_allocations($pteroNodeId, 1);
        $allocation = $response["data"][0] ?? null;

        if (!$allocation) {
            throw new RuntimeException("Mock Allocationを取得できませんでした。");
        }

        $attributes = $allocation["attributes"] ?? $allocation;

        return [
            "id" => (int)$attributes["id"],
            "ip" => (string)($attributes["ip"] ?? ""),
            "alias" => $attributes["alias"] ?? null,
            "port" => (int)($attributes["port"] ?? 0),
        ];
    }

    $page = 1;
    $maxPages = 20;

    while ($page <= $maxPages) {
        $response = hc_ptero_list_node_allocations($pteroNodeId, $page);
        $allocations = $response["data"] ?? [];

        foreach ($allocations as $allocation) {
            $attributes = $allocation["attributes"] ?? $allocation;

            if (!hc_ptero_allocation_is_free($allocation)) {
                continue;
            }

            $allocationId = (int)($attributes["id"] ?? 0);

            if ($allocationId <= 0) {
                continue;
            }

            return [
                "id" => $allocationId,
                "ip" => (string)($attributes["ip"] ?? ""),
                "alias" => $attributes["alias"] ?? null,
                "port" => (int)($attributes["port"] ?? 0),
            ];
        }

        $pagination = $response["meta"]["pagination"] ?? [];
        $totalPages = (int)($pagination["total_pages"] ?? $page);

        if ($page >= $totalPages) {
            break;
        }

        $page++;
    }

    throw new RuntimeException("Node #" . $pteroNodeId . " に空きAllocationがありません。Pterodactyl側でポート枠を追加してください。");
}

/* =========================================================
   Compatibility wrappers for /admin/ptero/
========================================================= */

if (!function_exists("ptero_config")) {
    function ptero_config(): array
    {
        return [
            "enabled" => hc_ptero_enabled(),
            "mock" => hc_ptero_mock(),
            "panel_url" => rtrim((string)hc_ptero_env("PTERO_PANEL_URL", ""), "/"),
            "api_key" => (string)hc_ptero_env("PTERO_API_KEY", ""),
            "default_node_id" => (int)hc_ptero_env("PTERO_DEFAULT_NODE_ID", "1"),
            "default_nest_id" => (int)hc_ptero_env("PTERO_DEFAULT_NEST_ID", "1"),
            "default_egg_id" => (int)hc_ptero_env("PTERO_DEFAULT_EGG_ID", "1"),
            "default_location_id" => (int)hc_ptero_env("PTERO_DEFAULT_LOCATION_ID", "1"),
        ];
    }
}

if (!function_exists("ptero_mask_api_key")) {
    function ptero_mask_api_key(string $key): string
    {
        if ($key === "") {
            return "未設定";
        }

        if (strlen($key) <= 12) {
            return str_repeat("*", strlen($key));
        }

        return substr($key, 0, 6) . "..." . substr($key, -4);
    }
}

if (!function_exists("ptero_result")) {
    function ptero_result(callable $callback): array
    {
        try {
            return [
                "ok" => true,
                "status" => 200,
                "mock" => hc_ptero_mock(),
                "error" => "",
                "data" => $callback(),
            ];
        } catch (Throwable $e) {
            return [
                "ok" => false,
                "status" => 500,
                "mock" => hc_ptero_mock(),
                "error" => $e->getMessage(),
                "data" => null,
            ];
        }
    }
}

if (!function_exists("ptero_get_nodes")) {
    function ptero_get_nodes(): array
    {
        return ptero_result(function () {
            if (hc_ptero_mock()) {
                return [
                    "object" => "list",
                    "data" => [
                        [
                            "object" => "node",
                            "attributes" => [
                                "id" => 1,
                                "name" => "HC Mock Node",
                                "fqdn" => "node.local",
                                "memory" => 32768,
                                "disk" => 200000,
                            ],
                        ],
                    ],
                ];
            }

            return hc_ptero_request("GET", "/api/application/nodes");
        });
    }
}

if (!function_exists("ptero_get_nests")) {
    function ptero_get_nests(): array
    {
        return ptero_result(function () {
            if (hc_ptero_mock()) {
                return [
                    "object" => "list",
                    "data" => [
                        [
                            "object" => "nest",
                            "attributes" => [
                                "id" => 1,
                                "name" => "Minecraft",
                                "description" => "Minecraft server eggs",
                            ],
                        ],
                    ],
                ];
            }

            return hc_ptero_request("GET", "/api/application/nests");
        });
    }
}

if (!function_exists("ptero_get_eggs")) {
    function ptero_get_eggs(int $nestId = 1): array
    {
        return ptero_result(function () use ($nestId) {
            if (hc_ptero_mock()) {
                return [
                    "object" => "list",
                    "data" => [
                        [
                            "object" => "egg",
                            "attributes" => [
                                "id" => 1,
                                "name" => "Paper",
                                "nest" => $nestId,
                                "description" => "Paper Minecraft Server",
                            ],
                        ],
                        [
                            "object" => "egg",
                            "attributes" => [
                                "id" => 2,
                                "name" => "Vanilla",
                                "nest" => $nestId,
                                "description" => "Vanilla Minecraft Server",
                            ],
                        ],
                    ],
                ];
            }

            return hc_ptero_request("GET", "/api/application/nests/" . rawurlencode((string)$nestId) . "/eggs");
        });
    }
}
