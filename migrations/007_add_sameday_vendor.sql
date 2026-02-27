-- Add Sameday easybox (RO, HU, BG). Placeholder api_url; replace with working endpoint when available.
-- Locker Plugin techdoc: https://cdn.sameday.ro/locker-plugin/techdoc.html
-- Sync will fail for this vendor until api_url returns JSON (array of lockers with lat/lng). Then locations will appear.

INSERT INTO `vendors` (`code`, `name`, `api_url`, `logo_url`, `active`) VALUES
('sameday', 'Sameday easybox', 'https://api.sameday.ro/api/public/lockers', NULL, TRUE)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `api_url` = COALESCE(NULLIF(VALUES(`api_url`), ''), `api_url`),
    `logo_url` = COALESCE(VALUES(`logo_url`), `logo_url`),
    `active` = VALUES(`active`);
