BEGIN;

-- ==================================================
-- 1. スタッフロールをHCアカウントロールへ統合
-- ==================================================

INSERT INTO account_roles (
    slug,
    name,
    role_type,
    description,
    priority,
    is_system,
    is_staff_role,
    created_at,
    updated_at
)
SELECT
    CASE staff_role.slug
        WHEN 'temporary-staff'
            THEN 'temporary_staff'
        WHEN 'web-developer'
            THEN 'web_developer'
        WHEN 'infrastructure-operator'
            THEN 'infra_operator'
        WHEN 'support-agent'
            THEN 'support_agent'
        WHEN 'order-operator'
            THEN 'order_operator'
        WHEN 'order-approver'
            THEN 'order_approver'
        ELSE REPLACE(staff_role.slug, '-', '_')
    END AS slug,

    staff_role.name,

    CASE
        WHEN staff_role.type = 'base'
            AND staff_role.slug IN (
                'owner',
                'administrator'
            )
            THEN 'system'

        WHEN staff_role.type = 'base'
            THEN 'staff'

        ELSE 'job'
    END AS role_type,

    staff_role.description,

    CASE staff_role.slug
        WHEN 'owner'
            THEN 1000
        WHEN 'administrator'
            THEN 900
        WHEN 'manager'
            THEN 800
        WHEN 'staff'
            THEN 700
        WHEN 'temporary-staff'
            THEN 600
        ELSE 500
    END AS priority,

    staff_role.is_system,

    TRUE,

    staff_role.created_at,
    CURRENT_TIMESTAMP
FROM staff_roles staff_role
WHERE staff_role.is_active = TRUE
ON CONFLICT (slug)
DO UPDATE SET
    name = EXCLUDED.name,
    role_type = EXCLUDED.role_type,
    description = EXCLUDED.description,
    priority = EXCLUDED.priority,
    is_system = EXCLUDED.is_system,
    is_staff_role = TRUE,
    updated_at = CURRENT_TIMESTAMP;


-- ==================================================
-- 2. スタッフ権限をHCアカウント権限へ統合
-- JSON変換を使い、旧テーブルの列差異に対応
-- ==================================================

INSERT INTO permissions (
    permission_key,
    name,
    description
)
SELECT
    staff_permission.permission_key,

    COALESCE(
        NULLIF(
            to_jsonb(staff_permission) ->> 'name',
            ''
        ),
        staff_permission.permission_key
    ),

    NULLIF(
        to_jsonb(staff_permission) ->> 'description',
        ''
    )
FROM staff_permissions staff_permission
ON CONFLICT (permission_key)
DO UPDATE SET
    name = EXCLUDED.name,
    description = COALESCE(
        EXCLUDED.description,
        permissions.description
    );


-- ==================================================
-- 3. ロール権限の関連を移行
-- ==================================================

INSERT INTO role_permissions (
    role_id,
    permission_id
)
SELECT DISTINCT
    account_role.id,
    account_permission.id
FROM staff_role_permissions old_relation
INNER JOIN staff_roles old_role
    ON old_role.id = old_relation.role_id
INNER JOIN staff_permissions old_permission
    ON old_permission.id = old_relation.permission_id
INNER JOIN account_roles account_role
    ON account_role.slug = CASE old_role.slug
        WHEN 'temporary-staff'
            THEN 'temporary_staff'
        WHEN 'web-developer'
            THEN 'web_developer'
        WHEN 'infrastructure-operator'
            THEN 'infra_operator'
        WHEN 'support-agent'
            THEN 'support_agent'
        WHEN 'order-operator'
            THEN 'order_operator'
        WHEN 'order-approver'
            THEN 'order_approver'
        ELSE REPLACE(old_role.slug, '-', '_')
    END
INNER JOIN permissions account_permission
    ON account_permission.permission_key =
       old_permission.permission_key
ON CONFLICT DO NOTHING;


-- ==================================================
-- 4. users.roleの既存ロールをuser_rolesへ移行
-- ==================================================

INSERT INTO user_roles (
    user_id,
    role_id,
    assigned_at
)
SELECT
    account.id,
    account_role.id,
    CURRENT_TIMESTAMP
FROM users account
INNER JOIN account_roles account_role
    ON account_role.slug = CASE LOWER(account.role)
        WHEN 'admin'
            THEN 'administrator'
        WHEN 'administrator'
            THEN 'administrator'
        WHEN 'owner'
            THEN 'owner'
        WHEN 'manager'
            THEN 'manager'
        WHEN 'staff'
            THEN 'staff'
        WHEN 'temporary-staff'
            THEN 'temporary_staff'
        WHEN 'temporary_staff'
            THEN 'temporary_staff'
        ELSE 'staff'
    END
ON CONFLICT (user_id, role_id)
DO NOTHING;


-- ==================================================
-- 5. staff_user_rolesをuser_rolesへ移行
-- staff_user_roles.user_id は staff_users.id
-- ==================================================

INSERT INTO user_roles (
    user_id,
    role_id,
    assigned_at
)
SELECT DISTINCT
    staff_user.account_id,
    account_role.id,
    COALESCE(
        old_relation.created_at,
        CURRENT_TIMESTAMP
    )
FROM staff_user_roles old_relation
INNER JOIN staff_users staff_user
    ON staff_user.id = old_relation.user_id
INNER JOIN staff_roles old_role
    ON old_role.id = old_relation.role_id
INNER JOIN account_roles account_role
    ON account_role.slug = CASE old_role.slug
        WHEN 'temporary-staff'
            THEN 'temporary_staff'
        WHEN 'web-developer'
            THEN 'web_developer'
        WHEN 'infrastructure-operator'
            THEN 'infra_operator'
        WHEN 'support-agent'
            THEN 'support_agent'
        WHEN 'order-operator'
            THEN 'order_operator'
        WHEN 'order-approver'
            THEN 'order_approver'
        ELSE REPLACE(old_role.slug, '-', '_')
    END
WHERE staff_user.account_id IS NOT NULL
ON CONFLICT (user_id, role_id)
DO NOTHING;


-- ==================================================
-- 6. Ownerは全権限
-- ==================================================

INSERT INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    account_role.id,
    permission.id
FROM account_roles account_role
CROSS JOIN permissions permission
WHERE account_role.slug = 'owner'
ON CONFLICT DO NOTHING;


-- ==================================================
-- 7. Administratorへ基本管理権限
-- ==================================================

INSERT INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    account_role.id,
    permission.id
FROM account_roles account_role
INNER JOIN permissions permission
    ON permission.permission_key IN (
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
WHERE account_role.slug = 'administrator'
ON CONFLICT DO NOTHING;

COMMIT;
