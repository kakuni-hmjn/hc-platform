CREATE TABLE IF NOT EXISTS header_operation_links (
    id SERIAL PRIMARY KEY,
    item_key VARCHAR(80) NOT NULL UNIQUE,
    label VARCHAR(120) NOT NULL,
    url VARCHAR(255) NOT NULL,
    required_role VARCHAR(40) NOT NULL DEFAULT 'staff',
    is_visible BOOLEAN NOT NULL DEFAULT true,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);

INSERT INTO header_operation_links
(item_key, label, url, required_role, is_visible, sort_order)
VALUES
('staff', 'スタッフページ', '/admin/staff/', 'staff', true, 10),
('admin', '管理者ページ', '/admin/', 'admin', true, 20),
('server_orders', 'ゲームサーバー申込管理', '/admin/server-orders/', 'admin', true, 30),
('plan_change_requests', 'プラン変更申請管理', '/admin/plan-change-requests/', 'admin', true, 40),
('game_plans', 'ゲームサーバープラン管理', '/admin/game-plans/', 'admin', true, 50),
('services', '事業管理', '/admin/services/', 'admin', true, 60),
('news', 'お知らせ管理', '/admin/news/', 'admin', true, 70),
('ptero', 'Pterodactyl連携', '/admin/ptero/', 'admin', true, 80),
('dev', '開発者ページ', '/admin/dev/', 'developer', true, 90),
('header_settings', 'ヘッダー表示設定', '/admin/header-settings/', 'admin', true, 100)
ON CONFLICT (item_key) DO UPDATE SET
    label = EXCLUDED.label,
    url = EXCLUDED.url,
    required_role = EXCLUDED.required_role,
    sort_order = EXCLUDED.sort_order,
    updated_at = NOW();

CREATE INDEX IF NOT EXISTS idx_header_operation_links_visible ON header_operation_links(is_visible);
CREATE INDEX IF NOT EXISTS idx_header_operation_links_sort_order ON header_operation_links(sort_order);
CREATE INDEX IF NOT EXISTS idx_header_operation_links_required_role ON header_operation_links(required_role);
