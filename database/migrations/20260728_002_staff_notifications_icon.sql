BEGIN;

ALTER TABLE staff_notifications
    ADD COLUMN IF NOT EXISTS icon VARCHAR(100);

UPDATE staff_notifications
SET icon = CASE category
    WHEN 'system' THEN 'settings'
    WHEN 'order' THEN 'shopping-cart'
    WHEN 'user' THEN 'user'
    WHEN 'discord' THEN 'discord'
    WHEN 'github' THEN 'github'
    WHEN 'development' THEN 'code'
    ELSE 'bell'
END
WHERE icon IS NULL
   OR TRIM(icon) = '';

ALTER TABLE staff_notifications
    ALTER COLUMN icon SET DEFAULT 'bell';

COMMENT ON COLUMN staff_notifications.icon IS
    '通知センターに表示するアイコン識別子';

COMMIT;
