<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * HCアカウントが指定権限を持っているかDBから直接確認する。
 *
 * Staffプロフィール作成前のアクセス判定にも使用する。
 */
function staff_account_has_permission(
    int $accountId,
    string $permissionKey
): bool {
    if (
        $accountId <= 0
        || trim($permissionKey) === ''
    ) {
        return false;
    }

    $statement = staff_db()->prepare(
        'SELECT 1
        FROM user_roles user_role
        INNER JOIN account_roles role
            ON role.id = user_role.role_id
        INNER JOIN role_permissions role_permission
            ON role_permission.role_id = role.id
        INNER JOIN permissions permission
            ON permission.id = role_permission.permission_id
        WHERE user_role.user_id = :account_id
          AND permission.permission_key = :permission_key
        LIMIT 1'
    );

    $statement->execute([
        'account_id' => $accountId,
        'permission_key' => $permissionKey,
    ]);

    return $statement->fetchColumn() !== false;
}

/**
 * HCアカウントのロール、権限と
 * Staff Console固有情報をまとめて読み込む。
 */
function staff_load_context(
    array $account,
    array $staffUser
): array {
    $pdo = staff_db();

    $accountId = (int) ($account['id'] ?? 0);
    $staffUserId = (int) ($staffUser['id'] ?? 0);

    $rolesStatement = $pdo->prepare(
        'SELECT
            role.id,
            role.name,
            role.slug,
            role.role_type AS type,
            role.description,
            role.priority,
            role.is_system,
            role.is_staff_role
        FROM user_roles user_role
        INNER JOIN account_roles role
            ON role.id = user_role.role_id
        WHERE user_role.user_id = :account_id
        ORDER BY
            role.priority DESC,
            role.id ASC'
    );

    $rolesStatement->execute([
        'account_id' => $accountId,
    ]);

    $roles = $rolesStatement->fetchAll();

    $permissionsStatement = $pdo->prepare(
        'SELECT DISTINCT
            permission.permission_key
        FROM user_roles user_role
        INNER JOIN role_permissions role_permission
            ON role_permission.role_id = user_role.role_id
        INNER JOIN permissions permission
            ON permission.id = role_permission.permission_id
        WHERE user_role.user_id = :account_id
        ORDER BY permission.permission_key ASC'
    );

    $permissionsStatement->execute([
        'account_id' => $accountId,
    ]);

    $permissions = array_column(
        $permissionsStatement->fetchAll(),
        'permission_key'
    );

    $categoriesStatement = $pdo->prepare(
        'SELECT
            category.id,
            category.name,
            category.slug,
            category.description,
            category.icon,
            category.sort_order
        FROM staff_user_categories user_category
        INNER JOIN staff_categories category
            ON category.id = user_category.category_id
        WHERE user_category.user_id = :staff_user_id
          AND category.is_active = TRUE
        ORDER BY
            category.sort_order ASC,
            category.id ASC'
    );

    $categoriesStatement->execute([
        'staff_user_id' => $staffUserId,
    ]);

    $categories = $categoriesStatement->fetchAll();

    $departmentsStatement = $pdo->prepare(
        'SELECT
            department.id,
            department.name,
            department.slug,
            department.description,
            user_department.is_primary
        FROM staff_user_departments user_department
        INNER JOIN staff_departments department
            ON department.id = user_department.department_id
        WHERE user_department.user_id = :staff_user_id
          AND department.is_active = TRUE
        ORDER BY
            user_department.is_primary DESC,
            department.sort_order ASC,
            department.id ASC'
    );

    $departmentsStatement->execute([
        'staff_user_id' => $staffUserId,
    ]);

    $departments = $departmentsStatement->fetchAll();

    $primaryRole = $roles[0] ?? [
        'id' => 0,
        'name' => 'Staff',
        'slug' => 'staff',
        'type' => 'staff',
        'description' => '',
        'priority' => 0,
        'is_system' => false,
        'is_staff_role' => true,
    ];

    return [
        'account' => $account,
        'user' => $staffUser,
        'roles' => $roles,
        'primary_role' => $primaryRole,
        'permissions' => $permissions,
        'categories' => $categories,
        'departments' => $departments,
    ];
}

function staff_has_permission(
    array $context,
    string $permissionKey
): bool {
    return in_array(
        $permissionKey,
        $context['permissions'] ?? [],
        true
    );
}

function staff_has_role(
    array $context,
    string $roleSlug
): bool {
    foreach ($context['roles'] ?? [] as $role) {
        if (
            (string) ($role['slug'] ?? '')
            === $roleSlug
        ) {
            return true;
        }
    }

    return false;
}

function staff_can_access_admin(array $context): bool
{
    return staff_has_role(
        $context,
        'owner'
    )
        || staff_has_role(
            $context,
            'administrator'
        )
        || staff_has_permission(
            $context,
            'staff.roles.manage'
        );
}

function staff_has_category(
    array $context,
    string $categorySlug
): bool {
    if (
        staff_can_access_admin($context)
        || staff_has_permission(
            $context,
            'staff.admin.view_all'
        )
    ) {
        return true;
    }

    foreach (
        $context['categories'] ?? []
        as $category
    ) {
        if (
            (string) ($category['slug'] ?? '')
            === $categorySlug
        ) {
            return true;
        }
    }

    return false;
}

function staff_require_permission(
    array $context,
    string $permissionKey
): void {
    if (
        staff_has_permission(
            $context,
            $permissionKey
        )
    ) {
        return;
    }

    http_response_code(403);

    exit(
        'このスタッフページへアクセスする権限がありません。'
    );
}


/*
|--------------------------------------------------------------------------
| Department / Service access
|--------------------------------------------------------------------------
*/

function staff_has_department(
    array $context,
    string $departmentSlug
): bool {
    if (
        staff_has_permission(
            $context,
            'staff.admin.view_all'
        )
        || staff_can_access_admin($context)
    ) {
        return true;
    }

    foreach (
        $context['departments'] ?? []
        as $department
    ) {
        if (
            (string) (
                $department['slug'] ?? ''
            ) === $departmentSlug
        ) {
            return true;
        }
    }

    return false;
}

/**
 * サービス単位の表示可否を判定する。
 *
 * オーナー・管理者:
 *   すべて表示
 *
 * 一般スタッフ:
 *   対象カテゴリまたは対象部署に所属し、
 *   さらに必要権限を持つ場合のみ表示
 */
function staff_can_access_service(
    array $context,
    array $options
): bool {
    if (
        staff_has_permission(
            $context,
            'staff.admin.view_all'
        )
        || staff_can_access_admin($context)
    ) {
        return true;
    }

    $categories = array_values(
        array_filter(
            array_map(
                'strval',
                $options['categories'] ?? []
            )
        )
    );

    $departments = array_values(
        array_filter(
            array_map(
                'strval',
                $options['departments'] ?? []
            )
        )
    );

    $permissions = array_values(
        array_filter(
            array_map(
                'strval',
                $options['permissions'] ?? []
            )
        )
    );

    $categoryMatched = $categories === [];

    foreach ($categories as $category) {
        if (
            staff_has_category(
                $context,
                $category
            )
        ) {
            $categoryMatched = true;
            break;
        }
    }

    $departmentMatched = $departments === [];

    foreach ($departments as $department) {
        if (
            staff_has_department(
                $context,
                $department
            )
        ) {
            $departmentMatched = true;
            break;
        }
    }

    $permissionMatched = $permissions === [];

    foreach ($permissions as $permission) {
        if (
            staff_has_permission(
                $context,
                $permission
            )
        ) {
            $permissionMatched = true;
            break;
        }
    }

    /*
     * カテゴリまたは部署のどちらかが一致し、
     * 必要権限も持っている場合に表示する。
     */
    $assignmentMatched =
        (
            $categories === []
            && $departments === []
        )
        || $categoryMatched
        || $departmentMatched;

    return $assignmentMatched
        && $permissionMatched;
}


/*
|--------------------------------------------------------------------------
| Department / Service access
|--------------------------------------------------------------------------
*/
