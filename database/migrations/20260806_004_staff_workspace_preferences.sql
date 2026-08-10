BEGIN;

CREATE TABLE IF NOT EXISTS staff_workspace_preferences (
    staff_user_id BIGINT PRIMARY KEY
        REFERENCES staff_users(id) ON DELETE CASCADE,
    accent_color VARCHAR(7) NOT NULL DEFAULT '#2563eb',
    background_mode VARCHAR(20) NOT NULL DEFAULT 'plain',
    background_preset VARCHAR(40) NOT NULL DEFAULT 'aurora',
    background_image_path TEXT,
    background_position VARCHAR(20) NOT NULL DEFAULT 'center',
    background_scale SMALLINT NOT NULL DEFAULT 100,
    background_overlay SMALLINT NOT NULL DEFAULT 72,
    panel_style VARCHAR(20) NOT NULL DEFAULT 'solid',
    dashboard_layout VARCHAR(20) NOT NULL DEFAULT 'balanced',
    compact_mode BOOLEAN NOT NULL DEFAULT FALSE,
    custom_greeting VARCHAR(160),
    widgets JSONB NOT NULL DEFAULT
        '["summary", "systems", "tasks", "announcements", "categories", "context", "custom_links"]'::JSONB,
    custom_links JSONB NOT NULL DEFAULT '[]'::JSONB,
    profile_bio VARCHAR(500),
    avatar_image_path TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT staff_workspace_accent_check CHECK (
        accent_color ~ '^#[0-9A-Fa-f]{6}$'
    ),
    CONSTRAINT staff_workspace_background_mode_check CHECK (
        background_mode IN ('plain', 'preset', 'image')
    ),
    CONSTRAINT staff_workspace_background_position_check CHECK (
        background_position IN ('center', 'top', 'bottom', 'left', 'right')
    ),
    CONSTRAINT staff_workspace_background_scale_check CHECK (
        background_scale BETWEEN 100 AND 200
    ),
    CONSTRAINT staff_workspace_overlay_check CHECK (
        background_overlay BETWEEN 0 AND 90
    ),
    CONSTRAINT staff_workspace_panel_style_check CHECK (
        panel_style IN ('solid', 'glass')
    ),
    CONSTRAINT staff_workspace_layout_check CHECK (
        dashboard_layout IN ('balanced', 'wide', 'stacked')
    )
);

CREATE INDEX IF NOT EXISTS idx_staff_workspace_preferences_updated
    ON staff_workspace_preferences(updated_at DESC);

COMMIT;
