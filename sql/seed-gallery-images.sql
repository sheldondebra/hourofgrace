-- Replace gallery with bundled site images (run in phpMyAdmin after uploading assets/gallery/)
TRUNCATE TABLE gallery_images;
TRUNCATE TABLE hero_slides;

INSERT INTO gallery_images (image_path, sort_order, is_active) VALUES
('assets/gallery/ministry-leaders.png', 1, 1),
('assets/gallery/worship-speaker.png', 2, 1),
('assets/gallery/dr-vida-graduation.png', 3, 1),
('assets/gallery/dr-vida-anniversary.png', 4, 1),
('assets/gallery/seminary-graduation.png', 5, 1),
('assets/gallery/community-fellowship.png', 6, 1);

INSERT INTO hero_slides (image_path, caption, sort_order, is_active) VALUES
('assets/gallery/ministry-leaders.png', 'Hour of Grace leadership', 1, 1),
('assets/gallery/worship-speaker.png', 'Worship and preaching', 2, 1),
('assets/gallery/dr-vida-graduation.png', 'Dr Vida Owusu — graduation', 3, 1),
('assets/gallery/dr-vida-anniversary.png', '5-year anniversary celebration', 4, 1),
('assets/gallery/seminary-graduation.png', 'Patmos Fidelis Seminary graduation', 5, 1);
