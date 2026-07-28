INSERT INTO permissions (
    permission_key,
    name
)
VALUES
    ('infrastructure.view', 'インフラ情報の閲覧'),
    ('development.view', '開発情報の閲覧'),
    ('analytics.view', '分析情報の閲覧'),
    ('customers.view', '顧客情報の閲覧'),
    ('documents.view', '社内ドキュメントの閲覧'),
    ('calendar.view', '社内カレンダーの閲覧'),
    ('team.view', 'チーム情報の閲覧'),
    ('notifications.view', 'スタッフ通知の閲覧')
ON CONFLICT (permission_key)
DO UPDATE SET
    name = EXCLUDED.name;

INSERT INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    role.id,
    permission.id
FROM account_roles role
CROSS JOIN permissions permission
WHERE role.slug = 'owner'
ON CONFLICT DO NOTHING;
