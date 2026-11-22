USE garments_network;

CREATE TABLE IF NOT EXISTS production_updates (
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

CREATE TABLE IF NOT EXISTS events (
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

CREATE TABLE IF NOT EXISTS news_articles (
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

INSERT INTO events (title, description, venue, location, starts_at, ends_at, status, created_by)
SELECT 'Bangladesh Apparel Sourcing Meetup',
       'Meet verified garment factories, buying houses, and sourcing professionals.',
       'International Convention City Bashundhara', 'Dhaka, Bangladesh',
       '2027-02-12 09:00:00', '2027-02-12 17:00:00', 'published', users.id
FROM users
INNER JOIN roles ON roles.id = users.role_id
WHERE roles.slug = 'admin' AND NOT EXISTS (SELECT 1 FROM events LIMIT 1)
LIMIT 1;

INSERT INTO news_articles (title, slug, summary, content, status, author_id, published_at)
SELECT 'Welcome to the Garments Network', 'welcome-to-garments-network',
       'A shared sourcing platform for apparel buyers, buying houses, factories, and suppliers.',
       'The Garments Network helps apparel businesses discover partners and coordinate sourcing requests. Create a company profile to get started.',
       'published', users.id, CURRENT_TIMESTAMP
FROM users
INNER JOIN roles ON roles.id = users.role_id
WHERE roles.slug = 'admin'
  AND NOT EXISTS (SELECT 1 FROM news_articles WHERE slug = 'welcome-to-garments-network')
LIMIT 1;
