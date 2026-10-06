USE portfolio_db;

ALTER TABLE hero_sections
    ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT '' AFTER id,
    ADD COLUMN subtitle TEXT NULL AFTER title,
    MODIFY COLUMN heading VARCHAR(255) NULL,
    MODIFY COLUMN image_url VARCHAR(2000) NULL,
    MODIFY COLUMN video_url VARCHAR(2000) NULL;

UPDATE hero_sections
SET title = COALESCE(NULLIF(title, ''), heading, ''),
    subtitle = COALESCE(subtitle, subheading);

ALTER TABLE videos
    ADD COLUMN video_type VARCHAR(30) NOT NULL DEFAULT 'other' AFTER video_url,
    MODIFY COLUMN thumbnail VARCHAR(2000) NULL,
    MODIFY COLUMN video_url VARCHAR(2000) NOT NULL;

ALTER TABLE websites
    ADD COLUMN work_performed JSON NULL AFTER technologies,
    MODIFY COLUMN cover_image VARCHAR(2000) NULL,
    MODIFY COLUMN live_url VARCHAR(2000) NULL;

ALTER TABLE website_images
    MODIFY COLUMN image_url VARCHAR(2000) NOT NULL;

ALTER TABLE other_work
    MODIFY COLUMN image_url VARCHAR(2000) NULL;

ALTER TABLE services
    MODIFY COLUMN image_url VARCHAR(2000) NULL;

ALTER TABLE enquiries
    MODIFY COLUMN phone VARCHAR(100) NULL;

-- The legacy clients table is intentionally left untouched to preserve existing records.
-- Client routes and admin modules are removed; the table may be archived after backup review.
