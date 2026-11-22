USE garments_network;

INSERT INTO roles (id, slug, name) VALUES
(1, 'admin', 'Administrator'),
(2, 'buyer', 'Buyer'),
(3, 'buying_house', 'Buying House'),
(4, 'factory', 'Factory'),
(5, 'supplier', 'Supplier');

-- Demo-only password for every account below: DemoPassword123!
-- Replace or remove these accounts before exposing an installation to the internet.
INSERT INTO users (id, role_id, name, email, password_hash) VALUES
(1, 1, 'Platform Admin', 'admin@example.com', '$2y$10$xJh/I1qZdyM4WRn6Eyu1s.6WbUiw2l00bmzqfRHvkrpbme7hoZZLK'),
(2, 2, 'Avery Buyer', 'avery.buyer@example.com', '$2y$10$xJh/I1qZdyM4WRn6Eyu1s.6WbUiw2l00bmzqfRHvkrpbme7hoZZLK'),
(3, 2, 'Morgan Buyer', 'morgan.buyer@example.com', '$2y$10$xJh/I1qZdyM4WRn6Eyu1s.6WbUiw2l00bmzqfRHvkrpbme7hoZZLK'),
(4, 2, 'Taylor Buyer', 'taylor.buyer@example.com', '$2y$10$xJh/I1qZdyM4WRn6Eyu1s.6WbUiw2l00bmzqfRHvkrpbme7hoZZLK'),
(5, 3, 'Dhaka Sourcing House', 'dhaka.house@example.com', '$2y$10$xJh/I1qZdyM4WRn6Eyu1s.6WbUiw2l00bmzqfRHvkrpbme7hoZZLK'),
(6, 3, 'Global Apparel Link', 'global.house@example.com', '$2y$10$xJh/I1qZdyM4WRn6Eyu1s.6WbUiw2l00bmzqfRHvkrpbme7hoZZLK'),
(7, 4, 'Narayanganj Knitwear', 'factory1@example.com', '$2y$10$xJh/I1qZdyM4WRn6Eyu1s.6WbUiw2l00bmzqfRHvkrpbme7hoZZLK'),
(8, 4, 'Gazipur Garments Ltd', 'factory2@example.com', '$2y$10$xJh/I1qZdyM4WRn6Eyu1s.6WbUiw2l00bmzqfRHvkrpbme7hoZZLK'),
(9, 4, 'Savar Stitch Works', 'factory3@example.com', '$2y$10$xJh/I1qZdyM4WRn6Eyu1s.6WbUiw2l00bmzqfRHvkrpbme7hoZZLK'),
(10, 4, 'Chattogram Apparel Co', 'factory4@example.com', '$2y$10$xJh/I1qZdyM4WRn6Eyu1s.6WbUiw2l00bmzqfRHvkrpbme7hoZZLK'),
(11, 4, 'Ashulia Fashion Makers', 'factory5@example.com', '$2y$10$xJh/I1qZdyM4WRn6Eyu1s.6WbUiw2l00bmzqfRHvkrpbme7hoZZLK');

INSERT INTO companies (id, owner_user_id, name, description, location, capacity_per_month, moq, lead_time_days, verification_status) VALUES
(1, 1, 'Garments Network Administration', 'Platform administrator account.', 'Dhaka, Bangladesh', NULL, NULL, NULL, 'verified'),
(2, 2, 'Avery Trading Group', 'International apparel sourcing buyer.', 'London, United Kingdom', NULL, NULL, NULL, 'unverified'),
(3, 3, 'Morgan Outfitters', 'Retail apparel buyer.', 'Toronto, Canada', NULL, NULL, NULL, 'unverified'),
(4, 4, 'Taylor Apparel Imports', 'Fashion and apparel importer.', 'Sydney, Australia', NULL, NULL, NULL, 'unverified'),
(5, 5, 'Dhaka Sourcing House', 'Buying house serving global apparel buyers.', 'Dhaka, Bangladesh', NULL, NULL, NULL, 'unverified'),
(6, 6, 'Global Apparel Link', 'Buyer and factory coordination services.', 'Chattogram, Bangladesh', NULL, NULL, NULL, 'unverified'),
(7, 7, 'Narayanganj Knitwear', 'Knit garments manufacturer.', 'Narayanganj, Bangladesh', 120000, 1000, 45, 'verified'),
(8, 8, 'Gazipur Garments Ltd', 'Woven and knit garment production.', 'Gazipur, Bangladesh', 180000, 1500, 50, 'pending'),
(9, 9, 'Savar Stitch Works', 'Cut-and-sew garment manufacturer.', 'Savar, Bangladesh', 90000, 800, 40, 'unverified'),
(10, 10, 'Chattogram Apparel Co', 'Export-ready apparel production.', 'Chattogram, Bangladesh', 150000, 2000, 55, 'unverified'),
(11, 11, 'Ashulia Fashion Makers', 'Fashion basics and jersey manufacturer.', 'Ashulia, Bangladesh', 75000, 500, 35, 'unverified');

INSERT INTO buying_house_factories (buying_house_company_id, factory_company_id) VALUES
(5, 7), (5, 8), (5, 9), (6, 8), (6, 10), (6, 11);

INSERT INTO categories (id, name, slug) VALUES
(1, 'T-Shirts', 't-shirts'),
(2, 'Sweatshirts', 'sweatshirts'),
(3, 'Denim', 'denim'),
(4, 'Woven Shirts', 'woven-shirts');

INSERT INTO products (company_id, category_id, name, fabric, description, moq, price_min, price_max, currency) VALUES
(7, 1, 'Cotton Crew Neck T-Shirt', '100% Cotton Jersey', 'Customizable export-quality crew neck tee.', 1000, 3.20, 5.50, 'USD'),
(8, 2, 'Fleece Pullover Hoodie', 'Cotton-Poly Fleece', 'Brushed fleece hoodie with custom trims.', 1500, 8.00, 13.00, 'USD'),
(9, 3, 'Five-Pocket Denim Jean', 'Stretch Denim', 'Five-pocket denim trousers with wash options.', 800, 9.50, 16.00, 'USD'),
(10, 4, 'Oxford Woven Shirt', 'Cotton Oxford', 'Long-sleeve woven shirt for private label.', 2000, 7.00, 12.00, 'USD'),
(11, 1, 'Organic Cotton Basic Tee', 'Organic Cotton Jersey', 'Basic jersey T-shirt with organic cotton option.', 500, 4.00, 6.50, 'USD');

INSERT INTO requirements (id, buyer_id, product_name, quantity, fabric, target_price, currency, delivery_date, certification_needed, details) VALUES
(1, 2, 'Cotton Crew Neck T-Shirt', 10000, '100% Cotton Jersey', 4.25, 'USD', '2027-03-15', 'OEKO-TEX preferred', 'Private-label tees in assorted colors.'),
(2, 3, 'Fleece Pullover Hoodie', 5000, 'Cotton-Poly Fleece', 10.00, 'USD', '2027-04-20', 'BSCI', 'Midweight hoodies with custom branding.'),
(3, 4, 'Five-Pocket Denim Jean', 8000, 'Stretch Denim', 13.00, 'USD', '2027-05-10', NULL, 'Indigo denim with two wash options.');

INSERT INTO rfqs (id, requirement_id, buyer_id, reference_code, message, response_deadline, status) VALUES
(1, 1, 2, 'RFQ-2026-0001', 'Please provide your best FOB quotation and sample timeline.', '2026-12-01', 'sent'),
(2, 2, 3, 'RFQ-2026-0002', 'Quote for two colorways and advise available certifications.', '2026-12-10', 'sent'),
(3, 3, 4, 'RFQ-2026-0003', 'Please quote with wash options and delivery schedule.', '2026-12-15', 'sent');

INSERT INTO rfq_recipients (rfq_id, recipient_company_id, status) VALUES
(1, 5, 'invited'),
(1, 6, 'invited'),
(2, 5, 'invited'),
(2, 6, 'invited'),
(3, 5, 'invited'),
(3, 6, 'invited');

INSERT INTO events (title, description, venue, location, starts_at, ends_at, status, created_by) VALUES
('Bangladesh Apparel Sourcing Meetup', 'Meet verified garment factories, buying houses, and sourcing professionals.', 'International Convention City Bashundhara', 'Dhaka, Bangladesh', '2027-02-12 09:00:00', '2027-02-12 17:00:00', 'published', 1);

INSERT INTO news_articles (title, slug, summary, content, status, author_id, published_at) VALUES
('Welcome to the Garments Network', 'welcome-to-garments-network', 'A shared sourcing platform for apparel buyers, buying houses, factories, and suppliers.', 'The Garments Network helps apparel businesses discover partners and coordinate sourcing requests. Create a company profile to get started.', 'published', 1, CURRENT_TIMESTAMP);
