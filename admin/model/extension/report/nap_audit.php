<?php
/**
 * Model for NRA audit report helper
 */
class ModelExtensionReportNapAudit extends Model {
    public function install() {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "nap_audit_log` (\n            `id` INT(11) NOT NULL AUTO_INCREMENT,\n            `year` INT(4) NOT NULL,\n            `month` CHAR(2) NOT NULL,\n            `store_id` INT(11) NOT NULL,\n            `created_at` DATETIME NOT NULL,\n            `admin_user_id` INT(11) NOT NULL,\n            `orders_count` INT(11) NOT NULL DEFAULT 0,\n            `refunds_count` INT(11) NOT NULL DEFAULT 0,\n            `file_name` VARCHAR(255) NOT NULL,\n            `params` TEXT,\n            PRIMARY KEY (`id`)\n        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "nap_refund` (\n            `id` INT(11) NOT NULL AUTO_INCREMENT,\n            `order_id` INT(11) NOT NULL,\n            `refund_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,\n            `refund_date` DATE NOT NULL,\n            `refund_method` TINYINT(1) NOT NULL DEFAULT 1,\n            `comment` TEXT,\n            PRIMARY KEY (`id`),\n            KEY `order_id` (`order_id`)\n        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
    }

    public function uninstall() {
        // Keep history table by default; uncomment to drop
        // $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "nap_audit_log`");
    }

    public function getPaymentMethods() {
        $methods = [];
        $this->load->model('setting/extension');
        $extensions = $this->model_setting_extension->getInstalled('payment');

        foreach ($extensions as $code) {
            $this->load->language('extension/payment/' . $code);
            $methods[] = [
                'code' => $code,
                'name' => $this->language->get('heading_title') ?: $code
            ];
        }

        return $methods;
    }

    public function getHistory() {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "nap_audit_log` ORDER BY id DESC LIMIT 20");
        return $query->rows;
    }
}
