INSERT INTO account_roles (
    slug,
    name,
    role_type,
    is_system,
    is_staff_role,
    priority
)
VALUES
    ('owner', 'オーナー', 'system', TRUE, TRUE, 1000),
    ('administrator', '管理者', 'system', TRUE, TRUE, 900),
    ('manager', 'マネージャー', 'staff', FALSE, TRUE, 800),
    ('staff', 'スタッフ', 'staff', FALSE, TRUE, 700),
    ('temporary_staff', '臨時スタッフ', 'staff', FALSE, TRUE, 600),
    ('web_developer', 'Web開発者', 'job', FALSE, TRUE, 500),
    ('infra_operator', 'インフラ担当', 'job', FALSE, TRUE, 500),
    ('support_agent', 'サポート担当', 'job', FALSE, TRUE, 500),
    ('order_operator', '注文担当', 'job', FALSE, TRUE, 500),
    ('order_approver', '注文承認者', 'job', FALSE, TRUE, 500)
ON CONFLICT (slug) DO UPDATE SET
    name = EXCLUDED.name,
    role_type = EXCLUDED.role_type,
    is_system = EXCLUDED.is_system,
    is_staff_role = EXCLUDED.is_staff_role,
    priority = EXCLUDED.priority,
    updated_at = NOW();
