BEGIN;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM users WHERE email = 'preview@hc.local') THEN
        RAISE EXCEPTION 'preview@hc.local account is required';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM game_server_plans WHERE status = 'published') THEN
        RAISE EXCEPTION 'published game server plans are required';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM ptero_nodes WHERE status = 'active') THEN
        RAISE EXCEPTION 'active Pterodactyl nodes are required';
    END IF;
END;
$$;

WITH fixtures (
    server_name,
    plan_slug,
    node_name,
    order_status,
    payment_status,
    minecraft_version,
    player_count,
    age_minutes,
    provision_error,
    approval_error
) AS (
    VALUES
        ('HC-TEST-READY-PAID',       'standard-8gb',   'mock-node-01', 'paid',             'paid',   '1.21.4', 20,  15, NULL, NULL),
        ('HC-TEST-CREATING',         'light-4gb',      'mock-node-01', 'creating',         'paid',   '1.21.4', 10,  30, NULL, NULL),
        ('HC-TEST-PENDING-APPROVAL', 'highclock-16gb', 'mock-node-02', 'pending_approval', 'paid',   '1.21.4', 40,  45, NULL, NULL),
        ('HC-TEST-APPROVAL-FAILED',  'standard-8gb',   'mock-node-01', 'approval_failed',  'paid',   '1.20.6', 20,  60, NULL, 'テスト用: 前回の承認処理が失敗した状態です。再承認を確認できます。'),
        ('HC-TEST-PROVISION-FAILED', 'entry-2gb',      'mock-node-01', 'provision_failed', 'paid',   '1.20.4',  5,  75, 'テスト用: Pterodactyl作成処理が失敗した状態です。再試行を確認できます。', NULL),
        ('HC-TEST-ACTIVE',           'highclock-32gb', 'mock-node-02', 'active',           'paid',   '1.21.4', 80,  90, NULL, NULL),
        ('HC-TEST-SUSPENDED',        'light-4gb',      'mock-node-01', 'suspended',        'paid',   '1.20.6', 10, 105, NULL, NULL),
        ('HC-TEST-UNPAID',           'entry-2gb',      'mock-node-01', 'pending_payment',  'unpaid', '1.21.4',  5, 120, NULL, NULL),
        ('HC-TEST-REJECTED',         'light-4gb',      'mock-node-01', 'rejected',         'paid',   '1.21.1', 10, 135, NULL, 'テスト用: 審査で却下された契約です。'),
        ('HC-TEST-CANCELLED',        'entry-2gb',      'mock-node-01', 'cancelled',        'paid',   '1.20.4',  5, 150, NULL, NULL),
        ('HC-TEST-EXPIRED',          'entry-2gb',      'mock-node-01', 'expired',          'paid',   '1.19.4',  5, 165, NULL, NULL)
)
INSERT INTO game_server_orders (
    user_id,
    plan_id,
    selected_node_id,
    server_name,
    minecraft_type,
    server_software,
    minecraft_version,
    player_count_estimate,
    note,
    billing_type,
    billing_period,
    status,
    payment_status,
    amount,
    currency,
    stripe_checkout_session_id,
    stripe_customer_id,
    stripe_subscription_id,
    stripe_payment_intent_id,
    paid_at,
    expires_at,
    next_payment_due_at,
    provisioning_started_at,
    provisioned_at,
    failed_at,
    provision_error,
    cancelled_at,
    cancel_requested_at,
    cancel_effective_at,
    cancel_reason,
    refund_policy_agreed,
    auto_renew_cancelled,
    approval_requested_at,
    approval_started_at,
    approved_at,
    approved_by,
    approved_via,
    approval_error,
    approval_attempts,
    created_at,
    updated_at
)
SELECT
    account.id,
    plan.id,
    node.id,
    fixture.server_name,
    'java',
    'paper',
    fixture.minecraft_version,
    fixture.player_count,
    'HC_LOCAL_SERVER_TEST_FIXTURE: 実課金・実サーバーを使用しないローカルテスト契約です。',
    'auto_subscription',
    'monthly',
    fixture.order_status,
    fixture.payment_status,
    plan.price_monthly,
    'jpy',
    'cs_test_' || lower(replace(fixture.server_name, '-', '_')),
    'cus_test_preview_owner',
    CASE WHEN fixture.payment_status = 'paid' THEN 'sub_test_' || lower(replace(fixture.server_name, '-', '_')) ELSE NULL END,
    CASE WHEN fixture.payment_status = 'paid' THEN 'pi_test_' || lower(replace(fixture.server_name, '-', '_')) ELSE NULL END,
    CASE WHEN fixture.payment_status = 'paid' THEN NOW() - fixture.age_minutes * INTERVAL '1 minute' ELSE NULL END,
    CASE WHEN fixture.order_status = 'expired' THEN NOW() - INTERVAL '1 day' ELSE NOW() + INTERVAL '30 days' END,
    CASE WHEN fixture.payment_status = 'paid' AND fixture.order_status NOT IN ('cancelled', 'expired') THEN NOW() + INTERVAL '30 days' ELSE NULL END,
    CASE WHEN fixture.order_status IN ('creating', 'pending_approval', 'approval_failed', 'provision_failed', 'active', 'suspended') THEN NOW() - (fixture.age_minutes - 5) * INTERVAL '1 minute' ELSE NULL END,
    CASE WHEN fixture.order_status IN ('pending_approval', 'approval_failed', 'active', 'suspended') THEN NOW() - (fixture.age_minutes - 10) * INTERVAL '1 minute' ELSE NULL END,
    CASE WHEN fixture.order_status = 'provision_failed' THEN NOW() - (fixture.age_minutes - 10) * INTERVAL '1 minute' ELSE NULL END,
    fixture.provision_error,
    CASE WHEN fixture.order_status = 'cancelled' THEN NOW() - INTERVAL '1 hour' ELSE NULL END,
    CASE WHEN fixture.order_status = 'cancelled' THEN NOW() - INTERVAL '2 hours' ELSE NULL END,
    CASE WHEN fixture.order_status = 'cancelled' THEN NOW() - INTERVAL '1 hour' ELSE NULL END,
    CASE WHEN fixture.order_status = 'cancelled' THEN 'テスト用の解約済み契約' ELSE NULL END,
    fixture.order_status = 'cancelled',
    fixture.order_status = 'cancelled',
    CASE WHEN fixture.order_status IN ('pending_approval', 'approval_failed', 'active', 'suspended') THEN NOW() - (fixture.age_minutes - 12) * INTERVAL '1 minute' ELSE NULL END,
    CASE WHEN fixture.order_status = 'approval_failed' THEN NOW() - INTERVAL '20 minutes' ELSE NULL END,
    CASE WHEN fixture.order_status IN ('active', 'suspended') THEN NOW() - (fixture.age_minutes - 15) * INTERVAL '1 minute' ELSE NULL END,
    CASE WHEN fixture.order_status IN ('active', 'suspended') THEN account.id ELSE NULL END,
    CASE WHEN fixture.order_status IN ('active', 'suspended') THEN 'web' ELSE NULL END,
    fixture.approval_error,
    CASE WHEN fixture.order_status IN ('approval_failed', 'active', 'suspended') THEN 1 ELSE 0 END,
    NOW() - fixture.age_minutes * INTERVAL '1 minute',
    NOW()
FROM fixtures fixture
JOIN users account ON account.email = 'preview@hc.local'
JOIN game_server_plans plan ON plan.slug = fixture.plan_slug
JOIN ptero_nodes node ON node.name = fixture.node_name
WHERE NOT EXISTS (
    SELECT 1
    FROM game_server_orders existing
    WHERE existing.server_name = fixture.server_name
      AND existing.note LIKE 'HC_LOCAL_SERVER_TEST_FIXTURE:%'
);

INSERT INTO ptero_servers (
    user_id,
    order_id,
    plan_id,
    node_id,
    ptero_user_id,
    ptero_server_id,
    ptero_identifier,
    ptero_uuid,
    name,
    status,
    suspended_at,
    created_at,
    updated_at
)
SELECT
    orders.user_id,
    orders.id,
    orders.plan_id,
    orders.selected_node_id,
    1,
    990000 + orders.id,
    'hc-test-' || orders.id,
    '00000000-0000-4000-8000-' || lpad(orders.id::text, 12, '0'),
    orders.server_name,
    CASE
        WHEN orders.status IN ('pending_approval', 'approval_failed') THEN 'pending_approval'
        WHEN orders.status = 'suspended' THEN 'suspended'
        ELSE 'active'
    END,
    CASE WHEN orders.status IN ('pending_approval', 'approval_failed', 'suspended') THEN NOW() - INTERVAL '10 minutes' ELSE NULL END,
    COALESCE(orders.provisioned_at, orders.created_at),
    NOW()
FROM game_server_orders orders
WHERE orders.note LIKE 'HC_LOCAL_SERVER_TEST_FIXTURE:%'
  AND orders.status IN ('pending_approval', 'approval_failed', 'active', 'suspended')
  AND NOT EXISTS (
      SELECT 1 FROM ptero_servers servers WHERE servers.order_id = orders.id
  );

INSERT INTO server_order_events (
    order_id,
    actor_user_id,
    event_type,
    title,
    message,
    old_status,
    new_status,
    old_payment_status,
    new_payment_status,
    metadata_json,
    created_at
)
SELECT
    orders.id,
    orders.user_id,
    'test_fixture_created',
    'テスト用サーバー契約を作成しました',
    '実決済や実サーバーへ接続しないローカル検証用データです。',
    NULL,
    orders.status,
    NULL,
    orders.payment_status,
    jsonb_build_object('fixture', true, 'server_name', orders.server_name),
    orders.created_at
FROM game_server_orders orders
WHERE orders.note LIKE 'HC_LOCAL_SERVER_TEST_FIXTURE:%'
  AND NOT EXISTS (
      SELECT 1
      FROM server_order_events events
      WHERE events.order_id = orders.id
        AND events.event_type = 'test_fixture_created'
  );

INSERT INTO payment_events (
    order_id,
    user_id,
    event_type,
    payment_status,
    amount,
    currency,
    provider,
    provider_event_id,
    provider_object_id,
    message,
    raw_payload,
    created_at
)
SELECT
    orders.id,
    orders.user_id,
    CASE WHEN orders.payment_status = 'paid' THEN 'test.payment.succeeded' ELSE 'test.checkout.created' END,
    orders.payment_status,
    orders.amount,
    orders.currency,
    'mock',
    'evt_test_order_' || orders.id,
    COALESCE(orders.stripe_payment_intent_id, orders.stripe_checkout_session_id),
    CASE WHEN orders.payment_status = 'paid' THEN 'テスト決済を支払い済みにしました。' ELSE 'テスト用の未払い申込です。' END,
    jsonb_build_object('fixture', true, 'livemode', false),
    COALESCE(orders.paid_at, orders.created_at)
FROM game_server_orders orders
WHERE orders.note LIKE 'HC_LOCAL_SERVER_TEST_FIXTURE:%'
  AND NOT EXISTS (
      SELECT 1
      FROM payment_events events
      WHERE events.provider_event_id = 'evt_test_order_' || orders.id
  );

INSERT INTO provisioning_jobs (
    order_id,
    job_type,
    status,
    attempts,
    max_attempts,
    last_error,
    available_at,
    started_at,
    completed_at,
    created_at,
    updated_at
)
SELECT
    orders.id,
    'provision_server',
    CASE WHEN orders.status = 'provision_failed' THEN 'failed' ELSE 'completed' END,
    CASE WHEN orders.status = 'provision_failed' THEN 5 ELSE 1 END,
    5,
    orders.provision_error,
    orders.created_at,
    orders.provisioning_started_at,
    CASE WHEN orders.status = 'provision_failed' THEN NULL ELSE orders.provisioned_at END,
    orders.created_at,
    NOW()
FROM game_server_orders orders
WHERE orders.note LIKE 'HC_LOCAL_SERVER_TEST_FIXTURE:%'
  AND orders.status IN ('pending_approval', 'approval_failed', 'provision_failed', 'active', 'suspended')
  AND NOT EXISTS (
      SELECT 1
      FROM provisioning_jobs jobs
      WHERE jobs.order_id = orders.id
        AND jobs.job_type = 'provision_server'
  );

INSERT INTO staff_order_notes (
    order_id,
    staff_user_id,
    note,
    created_at
)
SELECT
    orders.id,
    staff.id,
    'テストデータです。実決済・実サーバーとして扱わないでください。',
    orders.created_at
FROM game_server_orders orders
JOIN staff_users staff ON staff.account_id = orders.user_id
WHERE orders.note LIKE 'HC_LOCAL_SERVER_TEST_FIXTURE:%'
  AND NOT EXISTS (
      SELECT 1
      FROM staff_order_notes notes
      WHERE notes.order_id = orders.id
        AND notes.note = 'テストデータです。実決済・実サーバーとして扱わないでください。'
  );

COMMIT;

SELECT
    orders.id,
    orders.server_name,
    plans.name AS plan_name,
    orders.status,
    orders.payment_status,
    orders.amount,
    orders.currency
FROM game_server_orders orders
JOIN game_server_plans plans ON plans.id = orders.plan_id
WHERE orders.note LIKE 'HC_LOCAL_SERVER_TEST_FIXTURE:%'
ORDER BY orders.id;
