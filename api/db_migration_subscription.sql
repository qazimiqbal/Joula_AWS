-- =============================================================
-- Subscription / Multi-Tenant Architecture Migration
-- Run once in phpMyAdmin or MySQL CLI.
-- =============================================================

-- 1. Organizations (one per paying customer / trial account)
CREATE TABLE IF NOT EXISTS organizations (
    id                     INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name                   VARCHAR(255)  NOT NULL DEFAULT 'My Organization',
    owner_user_id          INT           NOT NULL,
    -- Stripe identifiers (NULL until user adds payment)
    stripe_customer_id     VARCHAR(100)  DEFAULT NULL,
    stripe_subscription_id VARCHAR(100)  DEFAULT NULL,
    -- Subscription state
    plan_status            ENUM('trial','active','past_due','cancelled','expired')
                           NOT NULL DEFAULT 'trial',
    trial_ends_at          DATETIME      NOT NULL,
    -- Seat limits (configurable per org by super admin)
    max_editors            INT           NOT NULL DEFAULT 1,
    max_viewers            INT           NOT NULL DEFAULT 3,
    -- Free account flag (set by super admin to bypass subscription checks)
    free_account           TINYINT(1)    NOT NULL DEFAULT 0,
    -- Monthly price in cents (e.g. 2999 = $29.99)
    monthly_price_cents    INT           NOT NULL DEFAULT 2999,
    -- Stripe price ID for the product (set after Stripe product is created)
    stripe_price_id        VARCHAR(100)  DEFAULT NULL,
    created_at             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_org_owner FOREIGN KEY (owner_user_id)
        REFERENCES Login_user_AWS(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Link users to their organization and record their org-level role
--    org_role is separate from the existing Permissions column.
--    auth_token stores the Bearer token returned at login.
ALTER TABLE Login_user_AWS
    ADD COLUMN IF NOT EXISTS org_id    INT           DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS org_role  ENUM('org_admin','editor','viewer')
                                       NOT NULL DEFAULT 'viewer',
    ADD COLUMN IF NOT EXISTS auth_token VARCHAR(64)  DEFAULT NULL;

-- Fast token lookup
CREATE INDEX IF NOT EXISTS idx_login_user_token ON Login_user_AWS(auth_token);

-- Index for fast lookup of all users in an org
CREATE INDEX IF NOT EXISTS idx_login_user_org ON Login_user_AWS(org_id);

-- 3. Optional: invite tokens table (for emailing editor/viewer invitations)
CREATE TABLE IF NOT EXISTS org_invites (
    id          INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    org_id      INT NOT NULL,
    invited_by  INT NOT NULL,
    email       VARCHAR(255) NOT NULL,
    org_role    ENUM('editor','viewer') NOT NULL DEFAULT 'viewer',
    token       VARCHAR(64) NOT NULL UNIQUE,
    accepted    TINYINT(1) NOT NULL DEFAULT 0,
    expires_at  DATETIME NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_invite_org  FOREIGN KEY (org_id)     REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_invite_user FOREIGN KEY (invited_by) REFERENCES Login_user_AWS(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================
-- NOTES FOR AWS MIGRATION:
-- • Add org_id FK to Addresses_AWS, visit history, etc. for full
--   multi-tenant data isolation.
-- • Switch stripe_price_id to a live Stripe Price object.
-- • Store STRIPE_SECRET_KEY and STRIPE_WEBHOOK_SECRET in AWS
--   Secrets Manager / environment variables, not in PHP files.
-- =============================================================
