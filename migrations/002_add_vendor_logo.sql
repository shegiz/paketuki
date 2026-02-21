-- Add logo_url to vendors for marker icons
ALTER TABLE `vendors`
    ADD COLUMN `logo_url` VARCHAR(512) NULL DEFAULT NULL COMMENT 'URL to vendor logo/icon for map markers' AFTER `api_url`;

-- Set Foxpost logo (from their CDN)
UPDATE `vendors` SET `logo_url` = 'https://cdn.foxpost.hu/icons/FOXPOST_icon_low.png' WHERE `code` = 'foxpost';
