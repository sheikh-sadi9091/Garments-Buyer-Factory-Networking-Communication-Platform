CREATE DATABASE IF NOT EXISTS garments_network
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE garments_network;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS complaints;
DROP TABLE IF EXISTS news_articles;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS production_updates;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS samples;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS quotations;
DROP TABLE IF EXISTS rfq_recipients;
DROP TABLE IF EXISTS rfqs;
DROP TABLE IF EXISTS requirements;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS verification_requests;
DROP TABLE IF EXISTS company_certificates;
DROP TABLE IF EXISTS companies;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(64) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id TINYINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_users_role_status (role_id, status)
) ENGINE=InnoDB;

CREATE TABLE companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    name VARCHAR(190) NOT NULL,
    description TEXT NULL,
    location VARCHAR(190) NOT NULL,
    website VARCHAR(255) NULL,
    phone VARCHAR(40) NULL,
    logo_path VARCHAR(255) NULL,
    cover_image_path VARCHAR(255) NULL,
    capacity_per_month INT UNSIGNED NULL,
    moq INT UNSIGNED NULL,
    lead_time_days SMALLINT UNSIGNED NULL,
    verification_status ENUM('unverified', 'pending', 'verified', 'rejected') NOT NULL DEFAULT 'unverified',
    verification_remarks TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_companies_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_companies_location (location),
    INDEX idx_companies_verification (verification_status)
) ENGINE=InnoDB;

CREATE TABLE buying_house_factories (
    buying_house_company_id BIGINT UNSIGNED NOT NULL,
    factory_company_id BIGINT UNSIGNED NOT NULL,
    connected_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (buying_house_company_id, factory_company_id),
    CONSTRAINT fk_buying_house_factories_house FOREIGN KEY (buying_house_company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_buying_house_factories_factory FOREIGN KEY (factory_company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_buying_house_factories_factory (factory_company_id)
) ENGINE=InnoDB;

CREATE TABLE company_certificates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    certificate_name VARCHAR(120) NOT NULL,
    certificate_number VARCHAR(120) NULL,
    issued_by VARCHAR(190) NULL,
    issued_on DATE NULL,
    expires_on DATE NULL,
    file_path VARCHAR(255) NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_certificates_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_certificates_company_status (company_id, status),
    INDEX idx_certificates_expiry (expires_on)
) ENGINE=InnoDB;

CREATE TABLE verification_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    remarks TEXT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    CONSTRAINT fk_verification_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_verification_requester FOREIGN KEY (requested_by) REFERENCES users(id),
    CONSTRAINT fk_verification_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_verification_status_date (status, requested_at)
) ENGINE=InnoDB;

CREATE TABLE categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    fabric VARCHAR(120) NULL,
    description TEXT NULL,
    moq INT UNSIGNED NOT NULL DEFAULT 1,
    price_min DECIMAL(12,2) NULL,
    price_max DECIMAL(12,2) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    image_path VARCHAR(255) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id),
    INDEX idx_products_category_status (category_id, status),
    INDEX idx_products_company_status (company_id, status),
    INDEX idx_products_moq (moq)
) ENGINE=InnoDB;

CREATE TABLE requirements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buyer_id BIGINT UNSIGNED NOT NULL,
    product_name VARCHAR(190) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    fabric VARCHAR(120) NULL,
    target_price DECIMAL(12,2) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    delivery_date DATE NULL,
    certification_needed VARCHAR(255) NULL,
    details TEXT NULL,
    status ENUM('draft', 'open', 'closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_requirements_buyer FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_requirements_status_date (status, created_at),
    INDEX idx_requirements_buyer (buyer_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE rfqs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requirement_id BIGINT UNSIGNED NOT NULL,
    buyer_id BIGINT UNSIGNED NOT NULL,
    reference_code VARCHAR(40) NOT NULL UNIQUE,
    message TEXT NULL,
    response_deadline DATE NULL,
    status ENUM('sent', 'quoting', 'awarded', 'closed', 'cancelled') NOT NULL DEFAULT 'sent',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rfqs_requirement FOREIGN KEY (requirement_id) REFERENCES requirements(id) ON DELETE CASCADE,
    CONSTRAINT fk_rfqs_buyer FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_rfqs_buyer_status (buyer_id, status)
) ENGINE=InnoDB;

CREATE TABLE rfq_recipients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rfq_id BIGINT UNSIGNED NOT NULL,
    recipient_company_id BIGINT UNSIGNED NOT NULL,
    status ENUM('invited', 'viewed', 'quoted', 'declined') NOT NULL DEFAULT 'invited',
    invited_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rfq_recipients_rfq FOREIGN KEY (rfq_id) REFERENCES rfqs(id) ON DELETE CASCADE,
    CONSTRAINT fk_rfq_recipients_company FOREIGN KEY (recipient_company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY uq_rfq_recipient_company (rfq_id, recipient_company_id),
    INDEX idx_recipients_company_status (recipient_company_id, status)
) ENGINE=InnoDB;

CREATE TABLE rfq_factory_forwardings (
    rfq_id BIGINT UNSIGNED NOT NULL,
    buying_house_company_id BIGINT UNSIGNED NOT NULL,
    factory_company_id BIGINT UNSIGNED NOT NULL,
    forwarded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rfq_id, buying_house_company_id, factory_company_id),
    CONSTRAINT fk_rfq_forwarding_rfq FOREIGN KEY (rfq_id) REFERENCES rfqs(id) ON DELETE CASCADE,
    CONSTRAINT fk_rfq_forwarding_house FOREIGN KEY (buying_house_company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_rfq_forwarding_factory FOREIGN KEY (factory_company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_rfq_forwarding_factory (factory_company_id, forwarded_at)
) ENGINE=InnoDB;

CREATE TABLE quotations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rfq_recipient_id BIGINT UNSIGNED NOT NULL UNIQUE,
    factory_company_id BIGINT UNSIGNED NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    moq INT UNSIGNED NOT NULL,
    lead_time_days SMALLINT UNSIGNED NOT NULL,
    terms TEXT NULL,
    status ENUM('submitted', 'shortlisted', 'accepted', 'rejected', 'withdrawn') NOT NULL DEFAULT 'submitted',
    valid_until DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_quotations_recipient FOREIGN KEY (rfq_recipient_id) REFERENCES rfq_recipients(id) ON DELETE CASCADE,
    CONSTRAINT fk_quotations_company FOREIGN KEY (factory_company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_quotations_factory_status (factory_company_id, status)
) ENGINE=InnoDB;

CREATE TABLE messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id BIGINT UNSIGNED NOT NULL,
    receiver_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL,
    attachment_name VARCHAR(255) NULL,
    attachment_mime VARCHAR(100) NULL,
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_messages_thread (sender_id, receiver_id, sent_at),
    INDEX idx_messages_unread (receiver_id, read_at)
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(190) NOT NULL,
    body TEXT NULL,
    target_url VARCHAR(255) NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_user_unread (user_id, read_at, created_at)
) ENGINE=InnoDB;

CREATE TABLE samples (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quotation_id BIGINT UNSIGNED NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    status ENUM('requested', 'sent', 'received', 'approved', 'rejected') NOT NULL DEFAULT 'requested',
    tracking_number VARCHAR(120) NULL,
    notes TEXT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_samples_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
    CONSTRAINT fk_samples_requester FOREIGN KEY (requested_by) REFERENCES users(id),
    INDEX idx_samples_status (status, updated_at)
) ENGINE=InnoDB;

CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quotation_id BIGINT UNSIGNED NOT NULL UNIQUE,
    buyer_id BIGINT UNSIGNED NOT NULL,
    factory_company_id BIGINT UNSIGNED NOT NULL,
    purchase_order_number VARCHAR(100) NULL UNIQUE,
    quantity INT UNSIGNED NOT NULL,
    total_amount DECIMAL(14,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    delivery_date DATE NULL,
    status ENUM('sample', 'approval', 'purchase_order', 'production', 'shipment', 'completed', 'cancelled') NOT NULL DEFAULT 'sample',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id),
    CONSTRAINT fk_orders_buyer FOREIGN KEY (buyer_id) REFERENCES users(id),
    CONSTRAINT fk_orders_factory FOREIGN KEY (factory_company_id) REFERENCES companies(id),
    INDEX idx_orders_buyer_status (buyer_id, status),
    INDEX idx_orders_factory_status (factory_company_id, status)
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    status ENUM('sample', 'approval', 'purchase_order', 'production', 'shipment', 'completed', 'cancelled') NOT NULL,
    note TEXT NULL,
    changed_by BIGINT UNSIGNED NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_order_history_order_date (order_id, changed_at)
) ENGINE=InnoDB;

CREATE TABLE production_updates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    stage ENUM('cutting', 'sewing', 'finishing', 'qc', 'shipment') NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'issue') NOT NULL DEFAULT 'in_progress',
    note TEXT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_production_updates_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_production_updates_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_production_updates_order_stage (order_id, stage, created_at)
) ENGINE=InnoDB;

CREATE TABLE reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    reviewer_id BIGINT UNSIGNED NOT NULL,
    reviewee_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id),
    CONSTRAINT fk_reviews_reviewee FOREIGN KEY (reviewee_id) REFERENCES users(id),
    CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5),
    UNIQUE KEY uq_review_order_pair (order_id, reviewer_id, reviewee_id),
    INDEX idx_reviews_reviewee (reviewee_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    venue VARCHAR(190) NULL,
    location VARCHAR(190) NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    registration_url VARCHAR(255) NULL,
    status ENUM('draft', 'published', 'cancelled') NOT NULL DEFAULT 'draft',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_events_status_date (status, starts_at)
) ENGINE=InnoDB;

CREATE TABLE news_articles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(210) NOT NULL UNIQUE,
    summary TEXT NULL,
    content MEDIUMTEXT NOT NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    author_id BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_news_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_news_status_published (status, published_at)
) ENGINE=InnoDB;

CREATE TABLE complaints (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reporter_id BIGINT UNSIGNED NOT NULL,
    against_user_id BIGINT UNSIGNED NULL,
    order_id BIGINT UNSIGNED NULL,
    subject VARCHAR(190) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('open', 'investigating', 'resolved', 'dismissed') NOT NULL DEFAULT 'open',
    resolution TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_complaints_reporter FOREIGN KEY (reporter_id) REFERENCES users(id),
    CONSTRAINT fk_complaints_against FOREIGN KEY (against_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_complaints_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_complaints_status_date (status, created_at)
) ENGINE=InnoDB;
