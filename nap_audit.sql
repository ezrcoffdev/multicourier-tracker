-- Tables required for NRA audit module
CREATE TABLE IF NOT EXISTS `oc_nap_audit_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `year` INT(4) NOT NULL,
  `month` CHAR(2) NOT NULL,
  `store_id` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `admin_user_id` INT(11) NOT NULL,
  `orders_count` INT(11) NOT NULL DEFAULT 0,
  `refunds_count` INT(11) NOT NULL DEFAULT 0,
  `file_name` VARCHAR(255) NOT NULL,
  `params` TEXT,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `oc_nap_refund` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `refund_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `refund_date` DATE NOT NULL,
  `refund_method` TINYINT(1) NOT NULL DEFAULT 1,
  `comment` TEXT,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
