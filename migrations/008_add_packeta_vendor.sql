-- Add Packeta (Zásilkovna) pickup points. API typically requires credentials.
-- Set api_url to your Packeta API endpoint when available; sync will run once configured.

INSERT INTO `vendors` (`code`, `name`, `api_url`, `logo_url`, `active`) VALUES
('packeta', 'Packeta', 'https://api.packeta.com/v1/branches', NULL, TRUE)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `api_url` = COALESCE(NULLIF(VALUES(`api_url`), ''), `api_url`),
    `logo_url` = COALESCE(VALUES(`logo_url`), `logo_url`),
    `active` = VALUES(`active`);
