USE portfolio_db;

INSERT INTO video_categories (name, display_order) VALUES
('Brand Marketing', 1),
('Digital Marketing', 2),
('Performance Marketing', 3),
('Creative Strategy', 4),
('AI Videos', 5),
('Business Automation', 6)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO admin_users (email, password_hash) VALUES
('admin@portfolio.dev', '$2y$10$FqsjAfT8EEz3w/K3nGC5CudzoBzanlvmKp4sXtxyEbp0m4NsoB7FO')
ON DUPLICATE KEY UPDATE email = VALUES(email);
