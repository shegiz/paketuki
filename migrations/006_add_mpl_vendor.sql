-- Add Magyar Posta (MPL) parcel terminals
-- Feed: PostInfo_CS.xml (Partner Extra, XML)

INSERT INTO `vendors` (`code`, `name`, `api_url`, `logo_url`, `active`) VALUES
('mpl', 'Magyar Posta (MPL)', 'http://httpmegosztas.posta.hu/PartnerExtra/Out/PostInfo_CS.xml', NULL, TRUE)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `api_url` = VALUES(`api_url`),
    `logo_url` = COALESCE(VALUES(`logo_url`), `logo_url`),
    `active` = VALUES(`active`);
