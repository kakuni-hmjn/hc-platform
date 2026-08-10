<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/game_server_approval.php';
require_once __DIR__ . '/operations.php';

function staff_approval_append_note(?string $current, string $note, string $actorName): ?string
{
    $note = trim($note);
    if ($note === '') return $current;
    $line = '[' . date('Y/m/d H:i') . ' / ' . $actorName . "]\n" . $note;
    $current = trim((string) $current);
    return $current === '' ? $line : $current . "\n\n" . $line;
}

function staff_approval_add_event(PDO $pdo, int $orderId, int $actorAccountId, string $type, string $title, string $message, ?string $status = null): void
{
    try {
        $statement = $pdo->prepare(
            'INSERT INTO server_order_events (order_id, actor_user_id, event_type, title, message,
                old_status, new_status, old_payment_status, new_payment_status, ip_address, created_at)
             SELECT :order_id, :actor_user_id, :event_type, :title, :message,
                status, status, payment_status, payment_status, :ip_address, NOW()
             FROM game_server_orders WHERE id = :order_id'
        );
        $statement->execute([
            'order_id' => $orderId, 'actor_user_id' => $actorAccountId, 'event_type' => $type,
            'title' => $title, 'message' => $message, 'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $exception) {
        // 履歴テーブルが未適用でも主処理は継続する。
    }
}

function staff_approval_apply_plan(PDO $pdo, array $request): void
{
    $statement = $pdo->prepare('UPDATE game_server_orders SET plan_id=:plan_id, amount=:amount, updated_at=NOW() WHERE id=:order_id');
    $statement->execute([
        'plan_id' => (int) $request['requested_plan_id'],
        'amount' => (int) $request['requested_price_monthly'],
        'order_id' => (int) $request['order_id'],
    ]);
    $statement = $pdo->prepare('UPDATE ptero_servers SET plan_id=:plan_id, updated_at=NOW() WHERE order_id=:order_id');
    $statement->execute(['plan_id' => (int) $request['requested_plan_id'], 'order_id' => (int) $request['order_id']]);
}

function staff_approval_execute(
    PDO $pdo,
    int $actorAccountId,
    int $actorStaffId,
    string $actorName,
    string $action,
    array $input
): string {
    if ($action === 'approve_server') {
        $orderId = max(0, (int) ($input['order_id'] ?? 0));
        if ($orderId <= 0) throw new InvalidArgumentException('注文IDが不正です。');
        $result = hc_approve_game_server_order($pdo, $orderId, $actorAccountId, 'staff_console');
        if (empty($result['ok'])) throw new RuntimeException('承認処理に失敗しました: ' . (string) ($result['error'] ?? '不明なエラー'));
        staff_ops_audit($actorStaffId, 'order.approve', 'game_server_order', $orderId, 'スタッフコンソールから利用開始を承認しました。');
        return !empty($result['already']) ? 'このサーバーはすでに承認済みです。' : 'ゲームサーバーを承認し、利用可能にしました。';
    }

    $requestId = max(0, (int) ($input['request_id'] ?? 0));
    $adminNote = trim((string) ($input['admin_note'] ?? ''));
    if ($requestId <= 0) throw new InvalidArgumentException('申請IDが不正です。');
    if (mb_strlen($adminNote) > 3000) throw new InvalidArgumentException('メモは3000文字以内で入力してください。');
    if (!in_array($action, ['process_plan_change', 'reject_plan_change', 'apply_plan_change'], true)) {
        throw new InvalidArgumentException('不明な承認操作です。');
    }

    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'SELECT r.*, gso.status AS order_status, gso.payment_status AS order_payment_status,
                current_plan.name AS current_plan_name,
                requested_plan.name AS requested_plan_name,
                requested_plan.price_monthly AS requested_price_monthly
             FROM server_order_plan_change_requests r
             JOIN game_server_orders gso ON gso.id=r.order_id
             JOIN game_server_plans current_plan ON current_plan.id=r.current_plan_id
             JOIN game_server_plans requested_plan ON requested_plan.id=r.requested_plan_id
             WHERE r.id=:id FOR UPDATE'
        );
        $statement->execute(['id' => $requestId]);
        $request = $statement->fetch();
        if (!is_array($request)) throw new RuntimeException('指定された申請が見つかりません。');
        $orderId = (int) $request['order_id'];

        if ($action === 'reject_plan_change') {
            if (!in_array((string) $request['status'], ['pending', 'approved'], true)) throw new DomainException('この申請は却下できない状態です。');
            $message = $adminNote !== '' ? $adminNote : 'スタッフによりプラン変更申請を却下しました。';
            $statement = $pdo->prepare("UPDATE server_order_plan_change_requests SET status='rejected', admin_note=:note, rejected_at=NOW(), updated_at=NOW() WHERE id=:id");
            $statement->execute(['note' => staff_approval_append_note($request['admin_note'] ?? null, $message, $actorName), 'id' => $requestId]);
            staff_approval_add_event($pdo, $orderId, $actorAccountId, 'staff_plan_change_rejected', 'プラン変更申請を却下', $message);
            $resultMessage = 'プラン変更申請を却下しました。';
        } elseif ($action === 'process_plan_change') {
            if ((string) $request['status'] !== 'pending') throw new DomainException('この申請は確認待ちではありません。');
            if ((string) $request['change_type'] === 'next_renewal') {
                $message = $adminNote !== '' ? $adminNote : '次回更新時のプラン変更として承認しました。';
                $statement = $pdo->prepare("UPDATE server_order_plan_change_requests SET status='approved', admin_note=:note, approved_at=NOW(), updated_at=NOW() WHERE id=:id");
                $statement->execute(['note' => staff_approval_append_note($request['admin_note'] ?? null, $message, $actorName), 'id' => $requestId]);
                staff_approval_add_event($pdo, $orderId, $actorAccountId, 'staff_plan_change_approved', '次回更新時のプラン変更を承認', $message);
                $resultMessage = '次回更新時のプラン変更として承認しました。';
            } elseif ((string) $request['change_type'] === 'immediate') {
                staff_approval_apply_plan($pdo, $request);
                $message = $adminNote !== '' ? $adminNote : '今すぐ変更として契約へ反映しました。';
                $statement = $pdo->prepare("UPDATE server_order_plan_change_requests SET status='processed', admin_note=:note, approved_at=COALESCE(approved_at,NOW()), processed_at=NOW(), updated_at=NOW() WHERE id=:id");
                $statement->execute(['note' => staff_approval_append_note($request['admin_note'] ?? null, $message, $actorName), 'id' => $requestId]);
                staff_approval_add_event($pdo, $orderId, $actorAccountId, 'staff_plan_change_processed', 'プラン変更を即時反映', $message);
                $resultMessage = 'プラン変更を契約へ反映しました。';
            } else {
                throw new DomainException('変更タイミングが不正です。');
            }
        } else {
            if ((string) $request['status'] !== 'approved') throw new DomainException('承認済みの申請のみ反映できます。');
            staff_approval_apply_plan($pdo, $request);
            $message = $adminNote !== '' ? $adminNote : '承認済みの変更を契約へ反映しました。';
            $statement = $pdo->prepare("UPDATE server_order_plan_change_requests SET status='processed', admin_note=:note, processed_at=NOW(), updated_at=NOW() WHERE id=:id");
            $statement->execute(['note' => staff_approval_append_note($request['admin_note'] ?? null, $message, $actorName), 'id' => $requestId]);
            staff_approval_add_event($pdo, $orderId, $actorAccountId, 'staff_plan_change_applied', '承認済みプラン変更を反映', $message);
            $resultMessage = '承認済みのプラン変更を契約へ反映しました。';
        }
        $pdo->commit();
        staff_ops_audit($actorStaffId, 'plan_change.' . $action, 'plan_change_request', $requestId, $resultMessage);
        return $resultMessage;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

