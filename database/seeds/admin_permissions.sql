INSERT INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    r.id,
    p.id
FROM account_roles r
JOIN permissions p
    ON p.permission_key IN (
        'staff.access',
        'staff.dashboard.view',
        'staff.users.view',
        'staff.roles.manage',
        'staff.permissions.manage',
        'tasks.view.own',
        'tasks.assign',
        'orders.view',
        'orders.process',
        'orders.approve',
        'audit.logs.view'
    )
WHERE r.slug = 'administrator'
ON CONFLICT DO NOTHING;
