BEGIN;

CREATE TABLE IF NOT EXISTS hpmc_categories (
    id BIGSERIAL PRIMARY KEY,
    category_key VARCHAR(50) NOT NULL UNIQUE,
    category_code VARCHAR(10) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    icon_name VARCHAR(100),
    form_schema JSONB NOT NULL DEFAULT '{}'::jsonb,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS hpmc_locations (
    id BIGSERIAL PRIMARY KEY,
    parent_id BIGINT
        REFERENCES hpmc_locations(id)
        ON DELETE RESTRICT,
    location_type VARCHAR(30) NOT NULL,
    location_code VARCHAR(30) NOT NULL,
    display_name VARCHAR(150) NOT NULL,
    country_code VARCHAR(3),
    prefecture_code VARCHAR(10),
    address TEXT,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (parent_id, location_code)
);

CREATE INDEX IF NOT EXISTS idx_hpmc_locations_parent
    ON hpmc_locations(parent_id);

CREATE INDEX IF NOT EXISTS idx_hpmc_locations_type
    ON hpmc_locations(location_type);

CREATE TABLE IF NOT EXISTS hpmc_id_counters (
    category_code VARCHAR(10) NOT NULL,
    country_code VARCHAR(3) NOT NULL,
    prefecture_code VARCHAR(10) NOT NULL,
    site_code VARCHAR(30) NOT NULL,
    current_value BIGINT NOT NULL DEFAULT 0,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (
        category_code,
        country_code,
        prefecture_code,
        site_code
    )
);

CREATE TABLE IF NOT EXISTS hpmc_assets (
    id BIGSERIAL PRIMARY KEY,
    management_id VARCHAR(100) NOT NULL UNIQUE,

    category_id BIGINT NOT NULL
        REFERENCES hpmc_categories(id)
        ON DELETE RESTRICT,

    name VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',

    manufacturer VARCHAR(150),
    model VARCHAR(200),
    serial_number VARCHAR(200),
    barcode VARCHAR(200),
    sku VARCHAR(150),

    quantity INTEGER NOT NULL DEFAULT 1,
    unit VARCHAR(30) NOT NULL DEFAULT '個',

    purchase_price NUMERIC(14, 2) NOT NULL DEFAULT 0,
    selling_price NUMERIC(14, 2) NOT NULL DEFAULT 0,

    country_code VARCHAR(3) NOT NULL DEFAULT 'JP',
    prefecture_code VARCHAR(10) NOT NULL,
    site_code VARCHAR(30) NOT NULL,

    location_id BIGINT
        REFERENCES hpmc_locations(id)
        ON DELETE SET NULL,

    building VARCHAR(150),
    floor VARCHAR(50),
    room VARCHAR(150),
    rack_code VARCHAR(100),
    shelf_code VARCHAR(100),
    start_u INTEGER,
    height_u INTEGER,

    hostname VARCHAR(255),
    management_ip INET,
    management_vlan INTEGER,
    mac_address MACADDR,

    purchase_date DATE,
    warranty_expiry DATE,
    supplier VARCHAR(200),

    specifications JSONB NOT NULL DEFAULT '{}'::jsonb,
    notes TEXT,

    qr_issued_at TIMESTAMPTZ,
    qr_issued_by BIGINT,

    created_by BIGINT,
    updated_by BIGINT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    deleted_at TIMESTAMPTZ,

    CONSTRAINT hpmc_assets_quantity_check
        CHECK (quantity >= 0),

    CONSTRAINT hpmc_assets_start_u_check
        CHECK (start_u IS NULL OR start_u >= 0),

    CONSTRAINT hpmc_assets_height_u_check
        CHECK (height_u IS NULL OR height_u >= 0)
);

CREATE INDEX IF NOT EXISTS idx_hpmc_assets_category
    ON hpmc_assets(category_id);

CREATE INDEX IF NOT EXISTS idx_hpmc_assets_status
    ON hpmc_assets(status);

CREATE INDEX IF NOT EXISTS idx_hpmc_assets_site
    ON hpmc_assets(site_code);

CREATE INDEX IF NOT EXISTS idx_hpmc_assets_location
    ON hpmc_assets(location_id);

CREATE INDEX IF NOT EXISTS idx_hpmc_assets_management_ip
    ON hpmc_assets(management_ip);

CREATE INDEX IF NOT EXISTS idx_hpmc_assets_active
    ON hpmc_assets(deleted_at)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_hpmc_assets_search
    ON hpmc_assets
    USING GIN (
        to_tsvector(
            'simple',
            COALESCE(management_id, '') || ' ' ||
            COALESCE(name, '') || ' ' ||
            COALESCE(manufacturer, '') || ' ' ||
            COALESCE(model, '') || ' ' ||
            COALESCE(serial_number, '') || ' ' ||
            COALESCE(barcode, '') || ' ' ||
            COALESCE(sku, '') || ' ' ||
            COALESCE(hostname, '')
        )
    );

CREATE TABLE IF NOT EXISTS hpmc_inventory_movements (
    id BIGSERIAL PRIMARY KEY,

    asset_id BIGINT NOT NULL
        REFERENCES hpmc_assets(id)
        ON DELETE RESTRICT,

    movement_type VARCHAR(30) NOT NULL,
    quantity INTEGER NOT NULL,
    quantity_before INTEGER NOT NULL,
    quantity_after INTEGER NOT NULL,

    source_location_id BIGINT
        REFERENCES hpmc_locations(id)
        ON DELETE SET NULL,

    destination_location_id BIGINT
        REFERENCES hpmc_locations(id)
        ON DELETE SET NULL,

    reference_type VARCHAR(50),
    reference_id VARCHAR(100),
    reason TEXT,

    operated_by BIGINT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT hpmc_inventory_quantity_check
        CHECK (quantity > 0)
);

CREATE INDEX IF NOT EXISTS idx_hpmc_inventory_asset
    ON hpmc_inventory_movements(asset_id);

CREATE INDEX IF NOT EXISTS idx_hpmc_inventory_created
    ON hpmc_inventory_movements(created_at DESC);

CREATE TABLE IF NOT EXISTS hpmc_qr_issues (
    id BIGSERIAL PRIMARY KEY,

    asset_id BIGINT NOT NULL
        REFERENCES hpmc_assets(id)
        ON DELETE CASCADE,

    print_mode VARCHAR(30) NOT NULL,
    label_layout VARCHAR(50),
    label_width_mm NUMERIC(8, 2),
    label_height_mm NUMERIC(8, 2),
    copies INTEGER NOT NULL DEFAULT 1,

    issued_by BIGINT,
    issued_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT hpmc_qr_copies_check
        CHECK (copies > 0)
);

CREATE INDEX IF NOT EXISTS idx_hpmc_qr_asset
    ON hpmc_qr_issues(asset_id);

CREATE TABLE IF NOT EXISTS hpmc_access_grants (
    id BIGSERIAL PRIMARY KEY,

    user_id BIGINT NOT NULL,
    permission_key VARCHAR(100) NOT NULL,

    granted_by BIGINT,
    granted_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at TIMESTAMPTZ,
    revoked_at TIMESTAMPTZ,

    UNIQUE (user_id, permission_key)
);

CREATE INDEX IF NOT EXISTS idx_hpmc_access_user
    ON hpmc_access_grants(user_id);

CREATE TABLE IF NOT EXISTS hpmc_audit_logs (
    id BIGSERIAL PRIMARY KEY,

    user_id BIGINT,
    action_key VARCHAR(100) NOT NULL,

    target_type VARCHAR(50),
    target_id VARCHAR(100),

    before_data JSONB,
    after_data JSONB,

    ip_address INET,
    user_agent TEXT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_hpmc_audit_created
    ON hpmc_audit_logs(created_at DESC);

CREATE INDEX IF NOT EXISTS idx_hpmc_audit_target
    ON hpmc_audit_logs(target_type, target_id);

INSERT INTO hpmc_categories (
    category_key,
    category_code,
    display_name,
    icon_name,
    sort_order
)
VALUES
    (
        'product',
        'PRD',
        '商品',
        'shopping_bag',
        10
    ),
    (
        'equipment',
        'EQP',
        '備品',
        'inventory_2',
        20
    ),
    (
        'physical_server',
        'SRV',
        '物理サーバー',
        'dns',
        30
    ),
    (
        'network_device',
        'NET',
        'ネットワーク機器',
        'account_tree',
        40
    ),
    (
        'computer',
        'PC',
        'PC・ワークステーション',
        'computer',
        50
    ),
    (
        'storage_device',
        'STG',
        'ストレージ機器',
        'storage',
        60
    ),
    (
        'rack',
        'RCK',
        'ラック',
        'view_stream',
        70
    ),
    (
        'other',
        'AST',
        'その他',
        'category',
        90
    )
ON CONFLICT (category_key)
DO UPDATE SET
    category_code = EXCLUDED.category_code,
    display_name = EXCLUDED.display_name,
    icon_name = EXCLUDED.icon_name,
    sort_order = EXCLUDED.sort_order,
    updated_at = NOW();

COMMIT;
