INSERT INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    r.id,
    p.id
FROM account_roles r
CROSS JOIN permissions p
WHERE r.slug = 'owner'
ON CONFLICT DO NOTHING;
