INSERT INTO permissions (
    permission_key,
    name
)
VALUES
    ('staff.access', 'スタッフコンソールへのアクセス'),
    ('staff.dashboard.view', 'スタッフダッシュボード閲覧'),
    ('staff.users.view', 'スタッフ一覧閲覧'),
    ('staff.roles.manage', 'スタッフロール管理'),
    ('staff.permissions.manage', 'スタッフ権限管理'),
    ('tasks.view.own', '自分のタスク閲覧'),
    ('tasks.assign', 'タスク割り当て'),
    ('orders.view', '注文閲覧'),
    ('orders.process', '注文処理'),
    ('orders.approve', '注文承認'),
    ('development.deploy.production', '本番デプロイ'),
    ('audit.logs.view', '操作ログ閲覧'),
    ('system.settings', 'システム設定')
ON CONFLICT (permission_key) DO UPDATE SET
    name = EXCLUDED.name;
