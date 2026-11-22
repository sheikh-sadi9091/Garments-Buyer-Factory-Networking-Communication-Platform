USE garments_network;

CREATE TABLE IF NOT EXISTS rfq_factory_forwardings (
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
