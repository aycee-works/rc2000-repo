CREATE DATABASE rc2000_db;


-- 0. UPDATED_AT TRIGGER FUNCTION

CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


-- 1. ADMINS

CREATE TABLE admins (
    admin_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'admin'
        CHECK (role IN ('admin', 'super_admin')),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    failed_login_attempts INTEGER NOT NULL DEFAULT 0,
    locked_until TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER trg_admins_updated_at
BEFORE UPDATE ON admins
FOR EACH ROW EXECUTE FUNCTION set_updated_at();


-- 2. ADMIN AUDIT LOGS

CREATE TABLE admin_audit_logs (
    log_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    admin_id BIGINT REFERENCES admins(admin_id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(80),
    entity_id BIGINT,
    details TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_audit_logs_created_at
ON admin_audit_logs(created_at DESC);


-- 3. CONTENT

CREATE TABLE content (
    content_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    content_type VARCHAR(30) NOT NULL
        CHECK (content_type IN (
            'announcement', 'story', 'article',
            'featured_story', 'review'
        )),
    title VARCHAR(250) NOT NULL,
    body TEXT NOT NULL,
    cover_image_path TEXT,
    author_name VARCHAR(250),
    status VARCHAR(20) NOT NULL DEFAULT 'draft'
        CHECK (status IN ('draft', 'published', 'archived')),
    created_by BIGINT REFERENCES admins(admin_id) ON DELETE SET NULL,
    updated_by BIGINT REFERENCES admins(admin_id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at TIMESTAMPTZ
);

CREATE INDEX idx_content_type_status
ON content(content_type, status);

CREATE TRIGGER trg_content_updated_at
BEFORE UPDATE ON content
FOR EACH ROW EXECUTE FUNCTION set_updated_at();


-- 4. TRIVIA

CREATE TABLE trivia (
    trivia_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    title VARCHAR(200),
    body TEXT NOT NULL,
    image_path TEXT,
    source VARCHAR(250),
    status VARCHAR(20) NOT NULL DEFAULT 'draft'
        CHECK (status IN ('draft', 'published', 'archived')),
    created_by BIGINT REFERENCES admins(admin_id) ON DELETE SET NULL,
    updated_by BIGINT REFERENCES admins(admin_id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at TIMESTAMPTZ
);

CREATE INDEX idx_trivia_status ON trivia(status);

CREATE TRIGGER trg_trivia_updated_at
BEFORE UPDATE ON trivia
FOR EACH ROW EXECUTE FUNCTION set_updated_at();


-- 5. BOOK DONATIONS

CREATE TABLE donations (
    donation_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    donor_name VARCHAR(150) NOT NULL,
    contact_info VARCHAR(255),
    quantity INTEGER NOT NULL CHECK (quantity > 0),
    donation_description TEXT,
    donation_photo_path TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN (
            'pending', 'approved', 'to_receive',
            'received', 'rejected'
        )),
    admin_notes TEXT,
    handled_by BIGINT REFERENCES admins(admin_id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_donations_status ON donations(status);

CREATE TRIGGER trg_donations_updated_at
BEFORE UPDATE ON donations
FOR EACH ROW EXECUTE FUNCTION set_updated_at();


-- 6. DONATION METHODS / QR CODES

CREATE TABLE donation_methods (
    donation_method_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    method_name VARCHAR(100) NOT NULL,
    payment_provider VARCHAR(100) NOT NULL,
    qr_image_path TEXT NOT NULL,
    instructions TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by BIGINT REFERENCES admins(admin_id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_donation_methods_active
ON donation_methods(is_active);

CREATE TRIGGER trg_donation_methods_updated_at
BEFORE UPDATE ON donation_methods
FOR EACH ROW EXECUTE FUNCTION set_updated_at();


-- 7. MONETARY DONATIONS
-- No approval, rejection, or verification status.

CREATE TABLE monetary_donations (
    monetary_donation_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    donor_name VARCHAR(150) NOT NULL,
    contact_info VARCHAR(255),

    donation_method_id BIGINT NOT NULL
        REFERENCES donation_methods(donation_method_id)
        ON DELETE RESTRICT,

    amount_reported NUMERIC(12, 2) NOT NULL
        CHECK (amount_reported > 0),

    transaction_reference VARCHAR(150) NOT NULL,
    payment_date TIMESTAMPTZ NOT NULL,
    proof_of_payment_path TEXT,
    admin_notes TEXT,

    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_monetary_method_reference
        UNIQUE (donation_method_id, transaction_reference)
);

CREATE INDEX idx_monetary_donations_created_at
ON monetary_donations(created_at DESC);

CREATE INDEX idx_monetary_donations_payment_date
ON monetary_donations(payment_date DESC);

CREATE TRIGGER trg_monetary_donations_updated_at
BEFORE UPDATE ON monetary_donations
FOR EACH ROW EXECUTE FUNCTION set_updated_at();


-- 8. INQUIRIES

CREATE TABLE inquiries (
    inquiry_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    sender_name VARCHAR(150) NOT NULL,
    sender_email VARCHAR(255) NOT NULL,
    subject VARCHAR(200),
    message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'in_progress', 'resolved')),
    admin_notes TEXT,
    handled_by BIGINT REFERENCES admins(admin_id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_inquiries_status ON inquiries(status);

CREATE TRIGGER trg_inquiries_updated_at
BEFORE UPDATE ON inquiries
FOR EACH ROW EXECUTE FUNCTION set_updated_at();


-- 9. GALLERY IMAGES

CREATE TABLE gallery_images (
    image_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    image_path TEXT NOT NULL,
    caption VARCHAR(255),
    alt_text VARCHAR(255),
    display_order INTEGER NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    uploaded_by BIGINT REFERENCES admins(admin_id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_gallery_active_order
ON gallery_images(is_active, display_order);

CREATE TRIGGER trg_gallery_images_updated_at
BEFORE UPDATE ON gallery_images
FOR EACH ROW EXECUTE FUNCTION set_updated_at();


-- 10. DASHBOARD SUMMARY VIEW

CREATE OR REPLACE VIEW dashboard_summary AS
SELECT
    (SELECT COUNT(*) FROM donations)
        AS total_book_donation_records,

    (SELECT COUNT(*) FROM donations WHERE status = 'pending')
        AS pending_book_donations,

    (SELECT COUNT(*) FROM donations WHERE status = 'received')
        AS received_book_donations,

    (SELECT COALESCE(SUM(quantity), 0)
     FROM donations WHERE status = 'received')
        AS books_received_quantity,

    (SELECT COUNT(*) FROM inquiries WHERE status = 'pending')
        AS pending_inquiries,

    (SELECT COUNT(*) FROM monetary_donations)
        AS total_monetary_donation_records,

    (SELECT COALESCE(SUM(amount_reported), 0.00)
     FROM monetary_donations)
        AS total_amount_reported,

    (SELECT COUNT(*)
     FROM monetary_donations
     WHERE created_at >= CURRENT_DATE
       AND created_at < CURRENT_DATE + INTERVAL '1 day')
        AS monetary_donation_records_today;

