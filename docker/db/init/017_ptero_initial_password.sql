ALTER TABLE ptero_user_links
ADD COLUMN IF NOT EXISTS initial_password TEXT NULL,
ADD COLUMN IF NOT EXISTS initial_password_created_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS initial_password_viewed_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS password_setup_completed_at TIMESTAMP NULL;
