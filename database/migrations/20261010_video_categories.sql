USE portfolio_db;

CREATE TABLE video_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    display_order INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_video_categories_name (name),
    KEY idx_video_categories_order (display_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO video_categories (name, display_order) VALUES
('Brand Marketing', 1),
('Digital Marketing', 2),
('Performance Marketing', 3),
('Creative Strategy', 4),
('AI Videos', 5),
('Business Automation', 6);

INSERT INTO video_categories (name, display_order)
SELECT DISTINCT TRIM(category), 1000
FROM videos
WHERE TRIM(category) <> ''
  AND NOT EXISTS (
      SELECT 1
      FROM video_categories
      WHERE video_categories.name = TRIM(videos.category)
  );

INSERT INTO video_categories (name, display_order)
SELECT 'Uncategorized', 1001
WHERE EXISTS (SELECT 1 FROM videos WHERE TRIM(category) = '')
  AND NOT EXISTS (SELECT 1 FROM video_categories WHERE name = 'Uncategorized');

ALTER TABLE videos
    ADD COLUMN category_id INT UNSIGNED NULL AFTER category;

UPDATE videos
JOIN video_categories ON video_categories.name = NULLIF(TRIM(videos.category), '')
SET videos.category_id = video_categories.id;

UPDATE videos
JOIN video_categories ON video_categories.name = 'Uncategorized'
SET videos.category_id = video_categories.id
WHERE videos.category_id IS NULL;

ALTER TABLE videos
    MODIFY COLUMN category_id INT UNSIGNED NOT NULL,
    ADD KEY idx_videos_category (category_id),
    ADD CONSTRAINT fk_videos_category
        FOREIGN KEY (category_id) REFERENCES video_categories(id) ON DELETE RESTRICT;
