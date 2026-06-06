<?php

require_once __DIR__ . "/../lib/env.php";

return [
    "enabled" => (bool)env_value("PTERO_ENABLED", false),
    "mock" => (bool)env_value("PTERO_MOCK", true),

    "panel_url" => rtrim((string)env_value("PTERO_PANEL_URL", ""), "/"),
    "api_key" => (string)env_value("PTERO_API_KEY", ""),

    "default_node_id" => (int)env_value("PTERO_DEFAULT_NODE_ID", 0),
    "default_nest_id" => (int)env_value("PTERO_DEFAULT_NEST_ID", 0),
    "default_egg_id" => (int)env_value("PTERO_DEFAULT_EGG_ID", 0),
];