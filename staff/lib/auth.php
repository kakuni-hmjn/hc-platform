<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/db.php';

/**
 * HCアカウントのログイン情報を取得する。
 *
 * Staff Console独自のログインやセッションは持たない。
 */
function staff_require_account(): array
{
    $account = require_login();

    return [
        ...$account,

        'id' => (int) ($account['id'] ?? 0),

        'username' => trim(
            (string) (
                $account['username']
                ?? $account['display_name']
                ?? 'Staff'
            )
        ),

        // 移行期間中の互換用。
        'account_role' => strtolower(
            trim(
                (string) (
                    $account['role']
                    ?? 'user'
                )
            )
        ),
    ];
}

/**
 * Staff Console専用プロフィールを取得または作成する。
 *
 * 認証情報、メール、パスワード、ロールは保存しない。
 * 勤務状態などスタッフ固有情報のみを管理する。
 */
function staff_find_or_create_user(array $account): array
{
    $accountId = (int) ($account['id'] ?? 0);

    if ($accountId <= 0) {
        throw new RuntimeException(
            'HCアカウントIDを取得できませんでした。'
        );
    }

    $displayName = trim(
        (string) (
            $account['display_name']
            ?? $account['username']
            ?? 'Staff'
        )
    );

    if ($displayName === '') {
        $displayName = 'Staff';
    }

    $statement = staff_db()->prepare(
        'INSERT INTO staff_users (
            account_id,
            display_name,
            status,
            work_status,
            last_seen_at,
            created_at,
            updated_at
        ) VALUES (
            :account_id,
            :display_name,
            :status,
            :work_status,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        )
        ON CONFLICT (account_id)
        DO UPDATE SET
            display_name = EXCLUDED.display_name,
            last_seen_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        RETURNING *'
    );

    $statement->execute([
        'account_id' => $accountId,
        'display_name' => $displayName,
        'status' => 'active',
        'work_status' => 'online',
    ]);

    $staffUser = $statement->fetch();

    if (!is_array($staffUser)) {
        throw new RuntimeException(
            'スタッフプロフィールを取得できませんでした。'
        );
    }

    return $staffUser;
}
