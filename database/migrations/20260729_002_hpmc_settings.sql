BEGIN;

CREATE TABLE IF NOT EXISTS hpmc_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value JSONB NOT NULL,
    updated_by BIGINT,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO hpmc_settings (
    setting_key,
    setting_value
)
VALUES
    (
        'low_stock_threshold',
        '3'::jsonb
    ),
    (
        'default_country_code',
        '"JP"'::jsonb
    ),
    (
        'default_prefecture_code',
        '"NGN"'::jsonb
    ),
    (
        'default_site_code',
        '"HCDC01"'::jsonb
    ),
    (
        'id_prefix',
        '"HC"'::jsonb
    ),
    (
        'id_sequence_digits',
        '6'::jsonb
    ),
    (
        'default_label_size',
        '"62x29"'::jsonb
    ),
    (
        'default_label_layout',
        '"visual_right"'::jsonb
    )
ON CONFLICT (setting_key)
DO NOTHING;

COMMIT;
