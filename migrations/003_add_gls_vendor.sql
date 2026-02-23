-- Add GLS Hungary as a vendor (delivery points: parcel lockers + parcel shops)
-- Feed: https://map.gls-hungary.com/data/deliveryPoints/hu.json

INSERT INTO `vendors` (`code`, `name`, `api_url`, `logo_url`, `active`) VALUES
('gls', 'GLS', 'https://map.gls-hungary.com/data/deliveryPoints/hu.json', 'https://gls-group.com/CZ/media/images/logo_thumb_M02_ASIDE.png', TRUE)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `api_url` = VALUES(`api_url`),
    `logo_url` = VALUES(`logo_url`),
    `active` = VALUES(`active`);
