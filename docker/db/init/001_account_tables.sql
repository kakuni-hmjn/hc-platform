CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,

    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    password TEXT NOT NULL,

    role VARCHAR(20) NOT NULL DEFAULT 'user',
    status VARCHAR(20) NOT NULL DEFAULT 'active',

    email_verified BOOLEAN NOT NULL DEFAULT false,
    email_verified_at TIMESTAMP NULL,

    register_ip INET,
    last_login TIMESTAMP NULL,

    terms_accepted BOOLEAN NOT NULL DEFAULT false,
    terms_accepted_at TIMESTAMP NULL,

    login_failed_count INTEGER NOT NULL DEFAULT 0,
    locked_until TIMESTAMP NULL,

    deleted_at TIMESTAMP NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pending_registrations (
    id SERIAL PRIMARY KEY,

    username VARCHAR(50) NOT NULL,
    email VARCHAR(120) NOT NULL,
    password TEXT NOT NULL,

    verification_code_hash TEXT NOT NULL,

    ip_address INET,

    failed_attempts INTEGER NOT NULL DEFAULT 0,
    expires_at TIMESTAMP NOT NULL,

    terms_accepted BOOLEAN NOT NULL DEFAULT false,
    terms_accepted_at TIMESTAMP NULL,

    resend_count INTEGER NOT NULL DEFAULT 0,
    last_sent_at TIMESTAMP NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS registration_logs (
    id SERIAL PRIMARY KEY,

    email VARCHAR(120),
    username VARCHAR(50),
    ip_address INET,

    result VARCHAR(50),
    message TEXT,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS login_logs (
    id SERIAL PRIMARY KEY,

    user_id INTEGER NULL,
    email VARCHAR(120),
    ip_address INET,

    result VARCHAR(50),
    message TEXT,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id SERIAL PRIMARY KEY,

    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    email VARCHAR(120) NOT NULL,

    token_hash TEXT NOT NULL,
    used BOOLEAN NOT NULL DEFAULT false,

    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_email
ON users(email);

CREATE INDEX IF NOT EXISTS idx_users_username
ON users(username);

CREATE INDEX IF NOT EXISTS idx_pending_email
ON pending_registrations(email);

CREATE INDEX IF NOT EXISTS idx_pending_username
ON pending_registrations(username);

CREATE INDEX IF NOT EXISTS idx_pending_expires_at
ON pending_registrations(expires_at);

CREATE INDEX IF NOT EXISTS idx_registration_logs_ip
ON registration_logs(ip_address);

CREATE INDEX IF NOT EXISTS idx_registration_logs_email
ON registration_logs(email);

CREATE INDEX IF NOT EXISTS idx_login_logs_email
ON login_logs(email);

CREATE INDEX IF NOT EXISTS idx_login_logs_user_id
ON login_logs(user_id);

CREATE INDEX IF NOT EXISTS idx_password_reset_email
ON password_reset_tokens(email);

CREATE INDEX IF NOT EXISTS idx_password_reset_token_hash
ON password_reset_tokens(token_hash);

CREATE INDEX IF NOT EXISTS idx_password_reset_expires_at
ON password_reset_tokens(expires_at);
