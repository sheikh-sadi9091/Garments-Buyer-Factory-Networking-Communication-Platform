USE garments_network;

CREATE TABLE IF NOT EXISTS buying_house_factories (
    buying_house_company_id BIGINT UNSIGNED NOT NULL,
    factory_company_id BIGINT UNSIGNED NOT NULL,
    connected_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (buying_house_company_id, factory_company_id),
    CONSTRAINT fk_buying_house_factories_house FOREIGN KEY (buying_house_company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_buying_house_factories_factory FOREIGN KEY (factory_company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_buying_house_factories_factory (factory_company_id)
) ENGINE=InnoDB;

ALTER TABLE rfq_recipients
    DROP FOREIGN KEY fk_rfq_recipients_company,
    DROP INDEX uq_rfq_factory,
    DROP INDEX idx_recipients_company_status,
    CHANGE COLUMN factory_company_id recipient_company_id BIGINT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_rfq_recipient_company_target
        FOREIGN KEY (recipient_company_id) REFERENCES companies(id) ON DELETE CASCADE,
    ADD UNIQUE KEY uq_rfq_recipient_company (rfq_id, recipient_company_id),
    ADD INDEX idx_recipients_company_status (recipient_company_id, status);
