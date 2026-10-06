USE portfolio_db;

INSERT INTO admin_users (email, password_hash) VALUES
('admin@portfolio.dev', '$2y$10$FqsjAfT8EEz3w/K3nGC5CudzoBzanlvmKp4sXtxyEbp0m4NsoB7FO')
ON DUPLICATE KEY UPDATE email = VALUES(email);
