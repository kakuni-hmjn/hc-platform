
<?php

require_once __DIR__ . "/../config/pterodactyl.php";

function ptero_config(): array
{
    return require __DIR__ . "/../config/pterodactyl.php";
}

function ptero_is_mock(): bool
{
    $config = ptero_config();
    return !empty($config["mock"]);
}

function ptero_is_enabled(): bool
{
    $config = ptero_config();
    return !empty($config["enabled"]);
}

function ptero_mask_api_key(string $key): string
{
    if ($key === "") {
        return "未設定";
    }

    if (strlen($key) <= 12) {
        return "設定済み";
    }

    return substr($key, 0, 8) . "..." . substr($key, -4);
}

function ptero_mock_nodes(): array
{
    return [
        "ok" => true,
        "status" => 200,
        "mock" => true,
        "data" => [
            "object" => "list",
            "data" => [
                [
                    "object" => "node",
                    "attributes" => [
                        "id" => 1,
                        "name" => "mock-node-01",
                        "description" => "開発環境用のダミーNodeです。",
                        "fqdn" => "mock-node-01.local",
                        "memory" => 32768,
                        "disk" => 200000,
                        "maintenance_mode" => false,
                    ],
                ],
                [
                    "object" => "node",
                    "attributes" => [
                        "id" => 2,
                        "name" => "mock-node-02",
                        "description" => "将来拡張用のダミーNodeです。",
                        "fqdn" => "mock-node-02.local",
                        "memory" => 65536,
                        "disk" => 500000,
                        "maintenance_mode" => false,
                    ],
                ],
            ],
        ],
        "error" => null,
    ];
}

function ptero_mock_nests(): array
{
    return [
        "ok" => true,
        "status" => 200,
        "mock" => true,
        "data" => [
            "object" => "list",
            "data" => [
                [
                    "object" => "nest",
                    "attributes" => [
                        "id" => 1,
                        "name" => "Minecraft",
                        "description" => "Minecraft Java / Bedrock / Forge / Fabric などのテスト用Nestです。",
                    ],
                ],
                [
                    "object" => "nest",
                    "attributes" => [
                        "id" => 2,
                        "name" => "Source Engine",
                        "description" => "将来対応確認用のダミーNestです。",
                    ],
                ],
            ],
        ],
        "error" => null,
    ];
}

function ptero_mock_eggs(int $nestId): array
{
    return [
        "ok" => true,
        "status" => 200,
        "mock" => true,
        "data" => [
            "object" => "list",
            "data" => [
                [
                    "object" => "egg",
                    "attributes" => [
                        "id" => 1,
                        "nest" => $nestId,
                        "name" => "Paper",
                        "description" => "Paper Minecraft server mock egg.",
                    ],
                ],
                [
                    "object" => "egg",
                    "attributes" => [
                        "id" => 2,
                        "nest" => $nestId,
                        "name" => "Fabric",
                        "description" => "Fabric Minecraft server mock egg.",
                    ],
                ],
                [
                    "object" => "egg",
                    "attributes" => [
                        "id" => 3,
                        "nest" => $nestId,
                        "name" => "Forge",
                        "description" => "Forge Minecraft server mock egg.",
                    ],
                ],
            ],
        ],
        "error" => null,
    ];
}

function ptero_request(string $method, string $endpoint, array $data = []): array
{
    $config = ptero_config();

    if (!empty($config["mock"])) {
        return [
            "ok" => false,
            "status" => 0,
            "mock" => true,
            "data" => null,
            "error" => "モックモードではこのエンドポイントは未実装です: " . $endpoint,
        ];
    }

    if (empty($config["enabled"])) {
        return [
            "ok" => false,
            "status" => 0,
            "mock" => false,
            "data" => null,
            "error" => "Pterodactyl連携が無効です。PTERO_ENABLED=true にしてください。",
        ];
    }

    $panelUrl = $config["panel_url"] ?? "";
    $apiKey = $config["api_key"] ?? "";

    if ($panelUrl === "" || $apiKey === "") {
        return [
            "ok" => false,
            "status" => 0,
            "mock" => false,
            "data" => null,
            "error" => "Pterodactyl Panel URL または APIキーが設定されていません。",
        ];
    }

    if (!function_exists("curl_init")) {
        return [
            "ok" => false,
            "status" => 0,
            "mock" => false,
            "data" => null,
            "error" => "PHP cURL拡張が有効ではありません。",
        ];
    }

    $url = rtrim($panelUrl, "/") . "/api/application/" . ltrim($endpoint, "/");

    $headers = [
        "Authorization: Bearer " . $apiKey,
        "Accept: Application/vnd.pterodactyl.v1+json",
        "Content-Type: application/json",
    ];

    $ch = curl_init($url);

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
    ];

    if (!empty($data)) {
        $options[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false) {
        return [
            "ok" => false,
            "status" => $statusCode,
            "mock" => false,
            "data" => null,
            "error" => $curlError ?: "Pterodactyl APIへの接続に失敗しました。",
        ];
    }

    $decoded = json_decode($response, true);

    if ($statusCode < 200 || $statusCode >= 300) {
        return [
            "ok" => false,
            "status" => $statusCode,
            "mock" => false,
            "data" => $decoded,
            "error" => "Pterodactyl APIがエラーを返しました。",
        ];
    }

    return [
        "ok" => true,
        "status" => $statusCode,
        "mock" => false,
        "data" => $decoded,
        "error" => null,
    ];
}

function ptero_get_nodes(): array
{
    if (ptero_is_mock()) {
        return ptero_mock_nodes();
    }

    return ptero_request("GET", "nodes");
}

function ptero_get_nests(): array
{
    if (ptero_is_mock()) {
        return ptero_mock_nests();
    }

    return ptero_request("GET", "nests");
}

function ptero_get_eggs(int $nestId): array
{
    if (ptero_is_mock()) {
        return ptero_mock_eggs($nestId);
    }

    return ptero_request("GET", "nests/" . $nestId . "/eggs");
}

function ptero_mock_create_server(array $payload = []): array
{
    return [
        "ok" => true,
        "status" => 201,
        "mock" => true,
        "data" => [
            "object" => "server",
            "attributes" => [
                "id" => random_int(1000, 9999),
                "external_id" => $payload["external_id"] ?? null,
                "uuid" => "mock-server-uuid-" . bin2hex(random_bytes(4)),
                "identifier" => "mock" . random_int(1000, 9999),
                "name" => $payload["name"] ?? "Mock Game Server",
                "description" => $payload["description"] ?? "Mock server created by HC Platform.",
                "status" => "installing",
                "suspended" => false,
                "limits" => [
                    "memory" => $payload["limits"]["memory"] ?? 2048,
                    "swap" => 0,
                    "disk" => $payload["limits"]["disk"] ?? 10240,
                    "io" => 500,
                    "cpu" => $payload["limits"]["cpu"] ?? 100,
                ],
            ],
        ],
        "error" => null,
    ];
}

function ptero_create_server(array $payload): array
{
    if (ptero_is_mock()) {
        return ptero_mock_create_server($payload);
    }

    return ptero_request("POST", "servers", $payload);
}