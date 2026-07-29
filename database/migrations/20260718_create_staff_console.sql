BEGIN;

CREATE TABLE IF NOT EXISTS staff_users (
    id BIGSERIAL PRIMARY KEY,
    account_id BIGINT NOT NULL UNIQUE,
    employee_code VARCHAR(50),
    display_name VARCHAR(150),
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    work_status VARCHAR(30) NOT NULL DEFAULT 'offline',
    discord_user_id VARCHAR(40),
    discord_display_name VARCHAR(150),
    discord_linked_at TIMESTAMPTZ,
    last_seen_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT staff_users_status_check CHECK (
        status IN (
            'active',
            'inactive',
            'suspended',
            'temporary',
            'external'
        )
    ),
    CONSTRAINT staff_users_work_status_check CHECK (
        work_status IN (
            'offline',
            'online',
            'working',
            'busy',
            'away',
            'break'
        )
    )
);

CREATE TABLE IF NOT EXISTS staff_roles (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    type VARCHAR(30) NOT NULL,
    description TEXT,
    priority INTEGER NOT NULL DEFAULT 100,
    is_system BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT staff_roles_type_check CHECK (
        type IN ('base', 'permission', 'system')
    )
);

CREATE TABLE IF NOT EXISTS staff_categories (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(80),
    sort_order INTEGER NOT NULL DEFAULT 100,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS staff_departments (
    id BIGSERIAL PRIMARY KEY,
    parent_id BIGINT REFERENCES staff_departments(id)
        ON DELETE SET NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    description TEXT,
    sort_order INTEGER NOT NULL DEFAULT 100,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS staff_permissions (
    id BIGSERIAL PRIMARY KEY,
    permission_key VARCHAR(180) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    category VARCHAR(80),
    is_system BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS staff_user_roles (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES staff_users(id)
        ON DELETE CASCADE,
    role_id BIGINT NOT NULL REFERENCES staff_roles(id)
        ON DELETE CASCADE,
    assigned_by BIGINT REFERENCES staff_users(id)
        ON DELETE SET NULL,
    expires_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, role_id)
);

CREATE TABLE IF NOT EXISTS staff_user_categories (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES staff_users(id)
        ON DELETE CASCADE,
    category_id BIGINT NOT NULL REFERENCES staff_categories(id)
        ON DELETE CASCADE,
    assigned_by BIGINT REFERENCES staff_users(id)
        ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, category_id)
);

CREATE TABLE IF NOT EXISTS staff_user_departments (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES staff_users(id)
        ON DELETE CASCADE,
    department_id BIGINT NOT NULL REFERENCES staff_departments(id)
        ON DELETE CASCADE,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    assigned_by BIGINT REFERENCES staff_users(id)
        ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, department_id)
);

CREATE TABLE IF NOT EXISTS staff_role_permissions (
    id BIGSERIAL PRIMARY KEY,
    role_id BIGINT NOT NULL REFERENCES staff_roles(id)
        ON DELETE CASCADE,
    permission_id BIGINT NOT NULL REFERENCES staff_permissions(id)
        ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (role_id, permission_id)
);

CREATE TABLE IF NOT EXISTS staff_tasks (
    id BIGSERIAL PRIMARY KEY,
    task_number VARCHAR(40) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category_id BIGINT REFERENCES staff_categories(id)
        ON DELETE SET NULL,
    department_id BIGINT REFERENCES staff_departments(id)
        ON DELETE SET NULL,
    assigned_user_id BIGINT REFERENCES staff_users(id)
        ON DELETE SET NULL,
    requested_by BIGINT REFERENCES staff_users(id)
        ON DELETE SET NULL,
    priority VARCHAR(30) NOT NULL DEFAULT 'normal',
    status VARCHAR(30) NOT NULL DEFAULT 'todo',
    due_at TIMESTAMPTZ,
    started_at TIMESTAMPTZ,
    completed_at TIMESTAMPTZ,
    related_type VARCHAR(80),
    related_id BIGINT,
    discord_thread_id VARCHAR(40),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT staff_tasks_priority_check CHECK (
        priority IN ('low', 'normal', 'high', 'urgent')
    ),
    CONSTRAINT staff_tasks_status_check CHECK (
        status IN (
            'todo',
            'in_progress',
            'review',
            'waiting',
            'completed',
            'cancelled'
        )
    )
);

CREATE TABLE IF NOT EXISTS staff_task_logs (
    id BIGSERIAL PRIMARY KEY,
    task_id BIGINT NOT NULL REFERENCES staff_tasks(id)
        ON DELETE CASCADE,
    user_id BIGINT REFERENCES staff_users(id)
        ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL,
    message TEXT,
    old_data JSONB,
    new_data JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS staff_announcements (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    priority VARCHAR(30) NOT NULL DEFAULT 'normal',
    target_type VARCHAR(30) NOT NULL DEFAULT 'all',
    target_id BIGINT,
    requires_confirmation BOOLEAN NOT NULL DEFAULT FALSE,
    published_at TIMESTAMPTZ,
    expires_at TIMESTAMPTZ,
    created_by BIGINT REFERENCES staff_users(id)
        ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT staff_announcements_priority_check CHECK (
        priority IN ('normal', 'important', 'urgent')
    ),
    CONSTRAINT staff_announcements_target_check CHECK (
        target_type IN (
            'all',
            'user',
            'role',
            'category',
            'department'
        )
    )
);

CREATE TABLE IF NOT EXISTS staff_announcement_reads (
    id BIGSERIAL PRIMARY KEY,
    announcement_id BIGINT NOT NULL
        REFERENCES staff_announcements(id)
        ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES staff_users(id)
        ON DELETE CASCADE,
    read_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMPTZ,
    UNIQUE (announcement_id, user_id)
);

CREATE TABLE IF NOT EXISTS staff_notifications (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES staff_users(id)
        ON DELETE CASCADE,
    type VARCHAR(80) NOT NULL DEFAULT 'general',
    title VARCHAR(255) NOT NULL,
    body TEXT,
    action_url TEXT,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    read_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS staff_audit_logs (
    id BIGSERIAL PRIMARY KEY,
    actor_staff_id BIGINT REFERENCES staff_users(id)
        ON DELETE SET NULL,
    action VARCHAR(180) NOT NULL,
    target_type VARCHAR(100),
    target_id VARCHAR(100),
    description TEXT,
    old_data JSONB,
    new_data JSONB,
    ip_address INET,
    user_agent TEXT,
    source VARCHAR(30) NOT NULL DEFAULT 'web',
    result VARCHAR(30) NOT NULL DEFAULT 'success',
    approved_by BIGINT REFERENCES staff_users(id)
        ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT staff_audit_source_check CHECK (
        source IN ('web', 'api', 'discord', 'system')
    ),
    CONSTRAINT staff_audit_result_check CHECK (
        result IN ('success', 'failed', 'denied', 'pending')
    )
);

CREATE INDEX IF NOT EXISTS idx_staff_users_account_id
    ON staff_users(account_id);

CREATE INDEX IF NOT EXISTS idx_staff_tasks_assigned_user
    ON staff_tasks(assigned_user_id);

CREATE INDEX IF NOT EXISTS idx_staff_tasks_status
    ON staff_tasks(status);

CREATE INDEX IF NOT EXISTS idx_staff_tasks_due_at
    ON staff_tasks(due_at);

CREATE INDEX IF NOT EXISTS idx_staff_notifications_user_read
    ON staff_notifications(user_id, is_read);

CREATE INDEX IF NOT EXISTS idx_staff_audit_logs_actor
    ON staff_audit_logs(actor_staff_id);

CREATE INDEX IF NOT EXISTS idx_staff_audit_logs_created_at
    ON staff_audit_logs(created_at DESC);

INSERT INTO staff_roles (
    name,
    slug,
    type,
    description,
    priority,
    is_system
) VALUES
    (
        'Owner',
        'owner',
        'base',
        'HC全体の管理と最終承認を行います。',
        1,
        TRUE
    ),
    (
        'Administrator',
        'administrator',
        'base',
        'スタッフ、権限、業務設定を管理します。',
        10,
        TRUE
    ),
    (
        'Manager',
        'manager',
        'base',
        '部署やチームの業務を管理します。',
        20,
        TRUE
    ),
    (
        'Staff',
        'staff',
        'base',
        '割り当てられた日常業務を行います。',
        50,
        TRUE
    ),
    (
        'Temporary Staff',
        'temporary-staff',
        'base',
        '期限付きのスタッフ権限です。',
        80,
        TRUE
    ),
    (
        'Web Developer',
        'web-developer',
        'permission',
        'Web開発業務を行います。',
        100,
        FALSE
    ),
    (
        'Infrastructure Operator',
        'infrastructure-operator',
        'permission',
        'サーバーやネットワークの運用を行います。',
        100,
        FALSE
    ),
    (
        'Support Agent',
        'support-agent',
        'permission',
        '問い合わせや顧客対応を行います。',
        100,
        FALSE
    ),
    (
        'Order Operator',
        'order-operator',
        'permission',
        '注文確認と構築処理を行います。',
        100,
        FALSE
    ),
    (
        'Order Approver',
        'order-approver',
        'permission',
        '注文の最終承認を行います。',
        100,
        FALSE
    )
ON CONFLICT (slug) DO NOTHING;

INSERT INTO staff_categories (
    name,
    slug,
    description,
    icon,
    sort_order
) VALUES
    (
        '開発',
        'development',
        'Web、API、Botなどの開発業務',
        'code',
        10
    ),
    (
        'インフラ',
        'infrastructure',
        'サーバー、ネットワーク、監視業務',
        'server',
        20
    ),
    (
        'カスタマーサポート',
        'support',
        '問い合わせと顧客対応',
        'message',
        30
    ),
    (
        'ゲームサーバー運営',
        'game-operations',
        '注文、構築、Pterodactyl運営業務',
        'gamepad',
        40
    ),
    (
        '広報・マーケティング',
        'marketing',
        '広報、SNS、マーケティング業務',
        'megaphone',
        50
    ),
    (
        '映像・デザイン',
        'creative',
        '映像、デザイン、クリエイティブ制作',
        'image',
        60
    )
ON CONFLICT (slug) DO NOTHING;

INSERT INTO staff_departments (
    name,
    slug,
    description,
    sort_order
) VALUES
    (
        '経営',
        'management',
        'HC全体の経営と運営管理',
        10
    ),
    (
        'Web開発部',
        'web-development',
        'HC PlatformとWebサービスの開発',
        20
    ),
    (
        'サーバー基盤チーム',
        'server-infrastructure',
        'サーバーと仮想基盤の運用',
        30
    ),
    (
        'ゲームサーバー運営部',
        'game-server-operations',
        'ゲームサーバーサービスの運営',
        40
    ),
    (
        'カスタマーサポート部',
        'customer-support',
        '顧客対応と問い合わせ対応',
        50
    )
ON CONFLICT (slug) DO NOTHING;

INSERT INTO staff_permissions (
    permission_key,
    name,
    description,
    category,
    is_system
) VALUES
    (
        'staff.dashboard.view',
        'スタッフダッシュボード閲覧',
        'スタッフコンソールへアクセスできます。',
        'staff',
        TRUE
    ),
    (
        'staff.admin.view_all',
        '全カテゴリ表示',
        '担当外を含むすべての業務メニューを表示できます。',
        'staff',
        TRUE
    ),
    (
        'staff.users.view',
        'スタッフ情報閲覧',
        'スタッフとユーザー情報を閲覧できます。',
        'staff',
        TRUE
    ),
    (
        'staff.users.edit',
        'スタッフ情報編集',
        'スタッフ情報を変更できます。',
        'staff',
        TRUE
    ),
    (
        'staff.roles.assign',
        'ロール割り当て',
        'スタッフへロールを付与できます。',
        'staff',
        TRUE
    ),
    (
        'tasks.view.own',
        '自分のタスク閲覧',
        '自分に割り当てられたタスクを確認できます。',
        'tasks',
        TRUE
    ),
    (
        'tasks.view.department',
        '部署タスク閲覧',
        '所属部署のタスクを確認できます。',
        'tasks',
        TRUE
    ),
    (
        'tasks.create',
        'タスク作成',
        '新しいタスクを作成できます。',
        'tasks',
        TRUE
    ),
    (
        'tasks.assign',
        'タスク割り当て',
        '他のスタッフへタスクを割り当てできます。',
        'tasks',
        TRUE
    ),
    (
        'tasks.complete',
        'タスク完了',
        '担当タスクを完了できます。',
        'tasks',
        TRUE
    ),
    (
        'announcements.view',
        '社内連絡閲覧',
        '社内のお知らせを閲覧できます。',
        'announcements',
        TRUE
    ),
    (
        'announcements.manage',
        '社内連絡管理',
        '社内のお知らせを作成・編集できます。',
        'announcements',
        TRUE
    ),
    (
        'orders.view',
        '注文閲覧',
        'ゲームサーバー注文を閲覧できます。',
        'orders',
        TRUE
    ),
    (
        'orders.process',
        '注文処理',
        '注文確認や構築処理を行えます。',
        'orders',
        TRUE
    ),
    (
        'orders.approve',
        '注文承認',
        '注文を承認できます。',
        'orders',
        TRUE
    ),
    (
        'support.tickets.view',
        '問い合わせ閲覧',
        'サポート問い合わせを閲覧できます。',
        'support',
        TRUE
    ),
    (
        'support.tickets.reply',
        '問い合わせ返信',
        '問い合わせへ返信できます。',
        'support',
        TRUE
    ),
    (
        'infrastructure.servers.view',
        'サーバー状態閲覧',
        'サーバーやNode状態を閲覧できます。',
        'infrastructure',
        TRUE
    ),
    (
        'infrastructure.servers.restart',
        'サーバー再起動',
        '許可されたサーバーを再起動できます。',
        'infrastructure',
        TRUE
    ),
    (
        'development.projects.view',
        '開発プロジェクト閲覧',
        '開発プロジェクトを閲覧できます。',
        'development',
        TRUE
    ),
    (
        'development.deploy.staging',
        'ステージングデプロイ',
        'ステージング環境へデプロイできます。',
        'development',
        TRUE
    ),
    (
        'development.deploy.production',
        '本番デプロイ',
        '承認後に本番環境へデプロイできます。',
        'development',
        TRUE
    ),
    (
        'audit.logs.view',
        '操作ログ閲覧',
        'スタッフ操作ログを閲覧できます。',
        'audit',
        TRUE
    )
ON CONFLICT (permission_key) DO NOTHING;

INSERT INTO staff_role_permissions (
    role_id,
    permission_id
)
SELECT
    role.id,
    permission.id
FROM staff_roles role
CROSS JOIN staff_permissions permission
WHERE role.slug IN ('owner', 'administrator')
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO staff_role_permissions (
    role_id,
    permission_id
)
SELECT
    role.id,
    permission.id
FROM staff_roles role
JOIN staff_permissions permission
    ON permission.permission_key IN (
        'staff.dashboard.view',
        'tasks.view.own',
        'tasks.complete',
        'announcements.view'
    )
WHERE role.slug = 'staff'
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO staff_role_permissions (
    role_id,
    permission_id
)
SELECT
    role.id,
    permission.id
FROM staff_roles role
JOIN staff_permissions permission
    ON permission.permission_key IN (
        'staff.dashboard.view',
        'tasks.view.own',
        'tasks.view.department',
        'tasks.create',
        'tasks.assign',
        'tasks.complete',
        'announcements.view'
    )
WHERE role.slug = 'manager'
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO staff_role_permissions (
    role_id,
    permission_id
)
SELECT
    role.id,
    permission.id
FROM staff_roles role
JOIN staff_permissions permission
    ON permission.permission_key IN (
        'development.projects.view',
        'development.deploy.staging'
    )
WHERE role.slug = 'web-developer'
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO staff_role_permissions (
    role_id,
    permission_id
)
SELECT
    role.id,
    permission.id
FROM staff_roles role
JOIN staff_permissions permission
    ON permission.permission_key IN (
        'infrastructure.servers.view'
    )
WHERE role.slug = 'infrastructure-operator'
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO staff_role_permissions (
    role_id,
    permission_id
)
SELECT
    role.id,
    permission.id
FROM staff_roles role
JOIN staff_permissions permission
    ON permission.permission_key IN (
        'support.tickets.view',
        'support.tickets.reply'
    )
WHERE role.slug = 'support-agent'
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO staff_role_permissions (
    role_id,
    permission_id
)
SELECT
    role.id,
    permission.id
FROM staff_roles role
JOIN staff_permissions permission
    ON permission.permission_key IN (
        'orders.view',
        'orders.process'
    )
WHERE role.slug = 'order-operator'
ON CONFLICT (role_id, permission_id) DO NOTHING;

INSERT INTO staff_role_permissions (
    role_id,
    permission_id
)
SELECT
    role.id,
    permission.id
FROM staff_roles role
JOIN staff_permissions permission
    ON permission.permission_key IN (
        'orders.view',
        'orders.process',
        'orders.approve'
    )
WHERE role.slug = 'order-approver'
ON CONFLICT (role_id, permission_id) DO NOTHING;

COMMIT;
