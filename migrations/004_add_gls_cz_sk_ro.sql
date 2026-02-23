-- Add GLS Czech Republic, Slovakia, Romania (same feed structure as Hungary)
-- Base URL: https://map.gls-hungary.com/data/deliveryPoints/{country}.json

UPDATE `vendors` SET `name` = 'GLS Hungary' WHERE `code` = 'gls';

INSERT INTO `vendors` (`code`, `name`, `api_url`, `logo_url`, `active`) VALUES
('gls_cz', 'GLS Czech', 'https://map.gls-hungary.com/data/deliveryPoints/cz.json', 'https://gls-group.com/CZ/media/images/logo_thumb_M02_ASIDE.png', TRUE),
('gls_sk', 'GLS Slovakia', 'https://map.gls-hungary.com/data/deliveryPoints/sk.json', 'https://gls-group.com/CZ/media/images/logo_thumb_M02_ASIDE.png', TRUE),
('gls_ro', 'GLS Romania', 'https://map.gls-hungary.com/data/deliveryPoints/ro.json', 'https://gls-group.com/CZ/media/images/logo_thumb_M02_ASIDE.png', TRUE)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `api_url` = VALUES(`api_url`),
    `logo_url` = VALUES(`logo_url`),
    `active` = VALUES(`active`);
