-- HC Platform development tables

CREATE TABLE IF NOT EXISTS services (
    id SERIAL PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    label VARCHAR(100),
    summary TEXT NOT NULL,
    service_phase VARCHAR(30) NOT NULL DEFAULT 'planned',
    has_detail_page BOOLEAN NOT NULL DEFAULT false,
    detail_url VARCHAR(255),
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS news (
    id SERIAL PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    category VARCHAR(50) NOT NULL DEFAULT 'other',
    summary TEXT NOT NULL,
    body TEXT NOT NULL,
    has_image BOOLEAN NOT NULL DEFAULT false,
    image_path VARCHAR(255),
    has_related_link BOOLEAN NOT NULL DEFAULT false,
    related_url VARCHAR(255),
    related_button_text VARCHAR(80),
    is_pinned BOOLEAN NOT NULL DEFAULT false,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS contacts (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'general',
    subject VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    ip_address INET,
    assigned_to INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    handled_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS ptero_nodes (
    id SERIAL PRIMARY KEY,
    ptero_node_id INTEGER NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    label VARCHAR(100),
    fqdn VARCHAR(255),
    description TEXT,
    cpu_type VARCHAR(100),
    is_high_performance BOOLEAN NOT NULL DEFAULT false,
    memory_mb INTEGER NOT NULL DEFAULT 0,
    disk_mb INTEGER NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    sort_order INTEGER NOT NULL DEFAULT 100,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS game_server_plans (
    id SERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    price_monthly INTEGER NOT NULL DEFAULT 0,
    memory_mb INTEGER NOT NULL,
    cpu_limit INTEGER NOT NULL,
    disk_mb INTEGER NOT NULL,
    backup_limit INTEGER NOT NULL DEFAULT 1,
    database_limit INTEGER NOT NULL DEFAULT 0,
    allocation_limit INTEGER NOT NULL DEFAULT 1,
    server_software_note TEXT,
    ptero_nest_id INTEGER NULL,
    ptero_egg_id INTEGER NULL,
    ptero_docker_image VARCHAR(255),
    ptero_startup_command TEXT,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    sort_order INTEGER NOT NULL DEFAULT 100,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS game_server_plan_nodes (
    id SERIAL PRIMARY KEY,
    plan_id INTEGER NOT NULL REFERENCES game_server_plans(id) ON DELETE CASCADE,
    node_id INTEGER NOT NULL REFERENCES ptero_nodes(id) ON DELETE CASCADE,
    is_primary BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(plan_id, node_id)
);

CREATE TABLE IF NOT EXISTS game_server_orders (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    plan_id INTEGER NOT NULL REFERENCES game_server_plans(id) ON DELETE RESTRICT,
    selected_node_id INTEGER NULL REFERENCES ptero_nodes(id) ON DELETE SET NULL,

    server_name VARCHAR(100) NOT NULL,
    minecraft_type VARCHAR(50) NOT NULL DEFAULT 'java',
    server_software VARCHAR(50) NOT NULL DEFAULT 'paper',
    minecraft_version VARCHAR(50),
    player_count_estimate INTEGER,
    note TEXT,

    billing_type VARCHAR(50) NOT NULL DEFAULT 'auto_subscription',
    billing_period VARCHAR(30) NOT NULL DEFAULT 'monthly',

    status VARCHAR(50) NOT NULL DEFAULT 'pending_payment',
    payment_status VARCHAR(50) NOT NULL DEFAULT 'unpaid',

    amount INTEGER NOT NULL DEFAULT 0,
    currency VARCHAR(10) NOT NULL DEFAULT 'jpy',

    stripe_checkout_session_id VARCHAR(255),
    stripe_customer_id VARCHAR(255),
    stripe_subscription_id VARCHAR(255),
    stripe_payment_intent_id VARCHAR(255),

    paid_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    next_payment_due_at TIMESTAMP NULL,

    provisioning_started_at TIMESTAMP NULL,
    provisioned_at TIMESTAMP NULL,
    failed_at TIMESTAMP NULL,
    provision_error TEXT,

    cancelled_at TIMESTAMP NULL,
    cancel_requested_at TIMESTAMP NULL,
    cancel_effective_at TIMESTAMP NULL,
    cancel_reason TEXT,
    refund_policy_agreed BOOLEAN NOT NULL DEFAULT false,
    auto_renew_cancelled BOOLEAN NOT NULL DEFAULT false,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS ptero_servers (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    order_id INTEGER NOT NULL UNIQUE REFERENCES game_server_orders(id) ON DELETE CASCADE,
    plan_id INTEGER NOT NULL REFERENCES game_server_plans(id) ON DELETE RESTRICT,
    node_id INTEGER NULL REFERENCES ptero_nodes(id) ON DELETE SET NULL,

    ptero_user_id INTEGER,
    ptero_server_id INTEGER,
    ptero_identifier VARCHAR(50),
    ptero_uuid VARCHAR(100),

    name VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS stripe_events (
    id SERIAL PRIMARY KEY,
    stripe_event_id VARCHAR(255) NOT NULL UNIQUE,
    event_type VARCHAR(120) NOT NULL,
    processed BOOLEAN NOT NULL DEFAULT false,
    payload JSONB NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_services_status ON services(status);
CREATE INDEX IF NOT EXISTS idx_news_status_published_at ON news(status, published_at);
CREATE INDEX IF NOT EXISTS idx_contacts_user_id ON contacts(user_id);
CREATE INDEX IF NOT EXISTS idx_ptero_nodes_status ON ptero_nodes(status);
CREATE INDEX IF NOT EXISTS idx_game_server_plans_status ON game_server_plans(status);
CREATE INDEX IF NOT EXISTS idx_game_server_orders_user_id ON game_server_orders(user_id);
CREATE INDEX IF NOT EXISTS idx_game_server_orders_status ON game_server_orders(status);
CREATE INDEX IF NOT EXISTS idx_ptero_servers_user_id ON ptero_servers(user_id);
CREATE INDEX IF NOT EXISTS idx_stripe_events_processed ON stripe_events(processed);

INSERT INTO ptero_nodes
(ptero_node_id, name, label, fqdn, description, cpu_type, is_high_performance, memory_mb, disk_mb, status, sort_order)
VALUES
(1, 'mock-node-01', '標準Node', 'mock-node-01.local', '開発環境用の標準ダミーNode', 'Standard CPU', false, 32768, 200000, 'active', 10),
(2, 'mock-node-02', '高性能Node', 'mock-node-02.local', '開発環境用の高性能ダミーNode', 'High Clock CPU', true, 65536, 500000, 'active', 20)
ON CONFLICT (ptero_node_id) DO UPDATE SET
    name = EXCLUDED.name,
    label = EXCLUDED.label,
    fqdn = EXCLUDED.fqdn,
    description = EXCLUDED.description,
    cpu_type = EXCLUDED.cpu_type,
    is_high_performance = EXCLUDED.is_high_performance,
    memory_mb = EXCLUDED.memory_mb,
    disk_mb = EXCLUDED.disk_mb,
    status = EXCLUDED.status,
    sort_order = EXCLUDED.sort_order,
    updated_at = NOW();

INSERT INTO game_server_plans
(name, slug, description, price_monthly, memory_mb, cpu_limit, disk_mb, backup_limit, database_limit, allocation_limit, server_software_note, ptero_nest_id, ptero_egg_id, ptero_docker_image, ptero_startup_command, status, sort_order)
VALUES
('Entry 2GB', 'entry-2gb', '小規模なJava/Paperサーバー向けの最低価格プランです。', 300, 2048, 100, 10240, 1, 0, 1, 'Paper / Spigot / Vanilla 向け', 1, 1, 'ghcr.io/pterodactyl/yolks:java_21', 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}', 'published', 10),
('Light 4GB', 'light-4gb', '少人数のマルチプレイや軽量プラグイン向けの低価格標準プランです。', 500, 4096, 200, 20480, 1, 1, 1, 'Paper / Spigot / Fabric 向け', 1, 1, 'ghcr.io/pterodactyl/yolks:java_21', 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}', 'published', 20),
('Standard 8GB', 'standard-8gb', 'プラグイン入りの一般的なMinecraftサーバー向け標準プランです。', 800, 8192, 400, 40960, 2, 1, 2, 'Paper / Purpur / Fabric 向け', 1, 1, 'ghcr.io/pterodactyl/yolks:java_21', 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}', 'published', 30),
('High Clock 16GB', 'highclock-16gb', '高クロックCPUを想定した中規模サーバー向けプランです。', 1500, 16384, 600, 81920, 3, 2, 3, 'Paper / Purpur / Mod構成向け', 1, 1, 'ghcr.io/pterodactyl/yolks:java_21', 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}', 'published', 40),
('High Clock 32GB', 'highclock-32gb', '大規模ワールドや重めのプラグイン構成向けの高性能プランです。', 2500, 32768, 800, 120000, 5, 3, 4, 'Paper / Purpur / 大規模構成向け', 1, 1, 'ghcr.io/pterodactyl/yolks:java_21', 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}', 'published', 50)
ON CONFLICT (slug) DO UPDATE SET
    name = EXCLUDED.name,
    description = EXCLUDED.description,
    price_monthly = EXCLUDED.price_monthly,
    memory_mb = EXCLUDED.memory_mb,
    cpu_limit = EXCLUDED.cpu_limit,
    disk_mb = EXCLUDED.disk_mb,
    backup_limit = EXCLUDED.backup_limit,
    database_limit = EXCLUDED.database_limit,
    allocation_limit = EXCLUDED.allocation_limit,
    server_software_note = EXCLUDED.server_software_note,
    ptero_nest_id = EXCLUDED.ptero_nest_id,
    ptero_egg_id = EXCLUDED.ptero_egg_id,
    ptero_docker_image = EXCLUDED.ptero_docker_image,
    ptero_startup_command = EXCLUDED.ptero_startup_command,
    status = EXCLUDED.status,
    sort_order = EXCLUDED.sort_order,
    updated_at = NOW();

INSERT INTO game_server_plan_nodes (plan_id, node_id, is_primary)
SELECT p.id, n.id, true
FROM game_server_plans p
JOIN ptero_nodes n ON n.ptero_node_id = 1
WHERE p.slug IN ('entry-2gb', 'light-4gb', 'standard-8gb')
ON CONFLICT (plan_id, node_id) DO UPDATE SET
    is_primary = EXCLUDED.is_primary;

INSERT INTO game_server_plan_nodes (plan_id, node_id, is_primary)
SELECT p.id, n.id, true
FROM game_server_plans p
JOIN ptero_nodes n ON n.ptero_node_id = 2
WHERE p.slug IN ('highclock-16gb', 'highclock-32gb')
ON CONFLICT (plan_id, node_id) DO UPDATE SET
    is_primary = EXCLUDED.is_primary;

INSERT INTO services
(title, slug, label, summary, service_phase, has_detail_page, detail_url, status, sort_order)
VALUES
('ゲームサーバーレンタル', 'game-server-rental', 'Game Server', 'Minecraftなどのゲームサーバーを手軽に申し込めるサービスです。', 'developing', true, '/order/game-server/', 'published', 10),
('Webサービス基盤', 'web-platform', 'Web Platform', 'HC Accountを中心に、Webサービスや管理機能を順次整備しています。', 'developing', false, NULL, 'published', 20),
('クリエイター支援', 'creator-support', 'Creator Support', '配信者や制作活動を支えるサービス展開を準備しています。', 'planned', false, NULL, 'published', 30),
('インフラ・サーバー運用', 'infrastructure', 'Infrastructure', 'VPS、専用サーバー、バックアップ環境などの提供を目指しています。', 'planned', false, NULL, 'published', 40)
ON CONFLICT (slug) DO UPDATE SET
    title = EXCLUDED.title,
    label = EXCLUDED.label,
    summary = EXCLUDED.summary,
    service_phase = EXCLUDED.service_phase,
    has_detail_page = EXCLUDED.has_detail_page,
    detail_url = EXCLUDED.detail_url,
    status = EXCLUDED.status,
    sort_order = EXCLUDED.sort_order,
    updated_at = NOW();

INSERT INTO news
(title, slug, category, summary, body, has_image, image_path, has_related_link, related_url, related_button_text, is_pinned, status, published_at)
VALUES
('HC Platform 開発環境を整備中', 'hc-platform-dev-start', 'site_update', 'HC Platformの開発環境とアカウント基盤を整備しています。', 'HC Platformでは、HC Account、ゲームサーバー申込、管理画面、Pterodactyl連携などを順番に開発しています。現在は開発環境で各機能を検証中です。', false, NULL, true, '/services/', '事業一覧を見る', true, 'published', NOW())
ON CONFLICT (slug) DO UPDATE SET
    title = EXCLUDED.title,
    category = EXCLUDED.category,
    summary = EXCLUDED.summary,
    body = EXCLUDED.body,
    has_image = EXCLUDED.has_image,
    image_path = EXCLUDED.image_path,
    has_related_link = EXCLUDED.has_related_link,
    related_url = EXCLUDED.related_url,
    related_button_text = EXCLUDED.related_button_text,
    is_pinned = EXCLUDED.is_pinned,
    status = EXCLUDED.status,
    published_at = EXCLUDED.published_at,
    updated_at = NOW();
