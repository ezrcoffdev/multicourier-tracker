<?php
namespace NapAudit;

use DateInterval;
use DateTime;
use Exception;
use XMLWriter;

/**
 * Helper responsible for building NRA audit XML
 */
class Helper {
    protected $registry;
    protected $db;
    protected $config;
    protected $user;
    protected $settings;

    public function __construct($registry, array $settings) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->config = $registry->get('config');
        $this->user = $registry->has('user') ? $registry->get('user') : null;
        $this->settings = $settings;
    }

    /**
     * Build XML for given period and return relative file path.
     */
    public function buildXml($year, $month, $store_id, array $order_statuses) {
        $date_from = new DateTime(sprintf('%04d-%02d-01', $year, $month));
        $date_to = (clone $date_from)->add(new DateInterval('P1M'));

        $orders = $this->getOrders($date_from, $date_to, $store_id, $order_statuses);
        $refunds = $this->getRefunds($date_from, $date_to, $store_id);

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('audit');

        $xml->writeElement('eik', $this->getSetting('report_nap_audit_eik'));
        $xml->writeElement('e_shop_n', $this->getSetting('report_nap_audit_shop_id'));
        $xml->writeElement('domain_name', $this->getSetting('report_nap_audit_domain'));
        $xml->writeElement('e_shop_type', (int)$this->getSetting('report_nap_audit_shop_type'));
        $xml->writeElement('creation_date', (new DateTime())->format('Y-m-d'));
        $xml->writeElement('mon', sprintf('%02d', $month));
        $xml->writeElement('god', $year);

        $xml->startElement('order');
        foreach ($orders as $order) {
            $xml->startElement('orderenum');
            $xml->writeElement('ord_n', $order['order_id']);
            $xml->writeElement('ord_d', substr($order['date_added'], 0, 10));
            $xml->writeElement('doc_n', $order['doc_n']);
            $xml->writeElement('doc_date', $order['doc_date']);

            $xml->startElement('art');
            foreach ($order['products'] as $product) {
                $xml->startElement('artenum');
                $xml->writeElement('art_name', $product['name']);
                $xml->writeElement('art_quant', $product['quantity']);
                $xml->writeElement('art_price', number_format((float)$product['price'], 2, '.', ''));
                $xml->writeElement('art_vat_rate', $product['vat_rate']);
                $xml->writeElement('art_vat', number_format((float)$product['tax'], 2, '.', ''));
                $xml->writeElement('art_sum', number_format((float)$product['sum'], 2, '.', ''));
                $xml->endElement();
            }
            $xml->endElement();

            $xml->writeElement('ord_total1', number_format((float)$order['total_net'], 2, '.', ''));
            $xml->writeElement('ord_disc', number_format((float)$order['discount'], 2, '.', ''));
            $xml->writeElement('ord_vat', number_format((float)$order['vat'], 2, '.', ''));
            $xml->writeElement('ord_total2', number_format((float)$order['total'], 2, '.', ''));
            $xml->writeElement('paym', (int)$order['paym']);
            $xml->writeElement('pos_n', $order['pos_n']);
            $xml->writeElement('trans_n', $order['trans_n']);
            $xml->writeElement('proc_id', $order['proc_id']);
            $xml->endElement();
        }
        $xml->endElement();

        if ($refunds) {
            $xml->writeElement('r_ord', count($refunds));
            $xml->startElement('rorder');
            foreach ($refunds as $refund) {
                $xml->startElement('rorderenum');
                $xml->writeElement('r_ord_n', $refund['order_id']);
                $xml->writeElement('r_amount', number_format((float)$refund['refund_amount'], 2, '.', ''));
                $xml->writeElement('r_date', $refund['refund_date']);
                $xml->writeElement('r_paym', (int)$refund['refund_method']);
                $xml->endElement();
            }
            $xml->endElement();
            $xml->writeElement('r_total', number_format(array_sum(array_column($refunds, 'refund_amount')), 2, '.', ''));
        }

        $xml->endElement();
        $xml->endDocument();

        $storage = DIR_STORAGE . 'nap_audit/';
        if (!is_dir($storage)) {
            mkdir($storage, 0755, true);
        }

        $file_name = sprintf('nap_audit_%04d_%02d.xml', $year, $month);
        $path = $storage . $file_name;
        file_put_contents($path, $xml->outputMemory());

        $this->logExport($year, $month, $store_id, count($orders), count($refunds), $file_name, [
            'order_statuses' => $order_statuses,
            'store_id' => $store_id
        ]);

        return 'system/storage/nap_audit/' . $file_name;
    }

    protected function getOrders(DateTime $from, DateTime $to, $store_id, array $status_ids) {
        $sql = "SELECT * FROM `" . DB_PREFIX . "order` WHERE DATE(date_added) >= '" . $from->format('Y-m-d') . "' AND DATE(date_added) < '" . $to->format('Y-m-d') . "'";
        if ($store_id !== null) {
            $sql .= " AND store_id = " . (int)$store_id;
        }
        if ($status_ids) {
            $sql .= " AND order_status_id IN(" . implode(',', array_map('intval', $status_ids)) . ")";
        }

        $query = $this->db->query($sql);
        $orders = [];
        foreach ($query->rows as $row) {
            $orders[] = $this->expandOrder($row);
        }
        return $orders;
    }

    protected function expandOrder(array $order) {
        $order['products'] = $this->getOrderProducts($order['order_id']);
        $totals = $this->getOrderTotals($order['order_id']);
        $order['total_net'] = $totals['net'];
        $order['discount'] = $totals['discount'];
        $order['vat'] = $totals['vat'];
        $order['total'] = $totals['total'];

        $order['doc_n'] = $order['invoice_no'] ? ($order['invoice_prefix'] . $order['invoice_no']) : $order['order_id'];
        $invoice_source = $this->settings['report_nap_audit_invoice_date'] ?? 'date_added';
        $order['doc_date'] = substr($order[$invoice_source] ?? $order['date_added'], 0, 10);

        $map = $this->settings['report_nap_audit_payment_map'][$order['payment_code']] ?? [];
        $order['paym'] = $map['paym'] ?? 6;
        $order['pos_n'] = ($order['paym'] == 2) ? ($map['pos_n'] ?? '') : '';
        $order['proc_id'] = ($order['paym'] == 4) ? ($map['proc_id'] ?? '') : '';
        $order['trans_n'] = $order['payment_transaction_id'] ?? '';
        return $order;
    }

    protected function getOrderProducts($order_id) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE order_id = " . (int)$order_id);
        $products = [];
        $default_vat = (float)($this->settings['report_nap_audit_default_vat'] ?? 20);
        foreach ($query->rows as $row) {
            $sum = (float)$row['price'] * (float)$row['quantity'] + (float)$row['tax'];
            $vat_rate = $row['price'] > 0 ? round($row['tax'] / ($row['price'] * $row['quantity']) * 100) : $default_vat;
            $products[] = [
                'name' => $row['name'],
                'quantity' => $row['quantity'],
                'price' => $row['price'],
                'tax' => $row['tax'],
                'sum' => $sum,
                'vat_rate' => $vat_rate
            ];
        }
        return $products;
    }

    protected function getOrderTotals($order_id) {
        $totals = [
            'net' => 0,
            'discount' => 0,
            'vat' => 0,
            'total' => 0
        ];

        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_total` WHERE order_id = " . (int)$order_id . " ORDER BY sort_order ASC");
        foreach ($query->rows as $row) {
            if (in_array($row['code'], ['sub_total'])) {
                $totals['net'] += (float)$row['value'];
            }
            if (in_array($row['code'], ['coupon', 'discount', 'reward'])) {
                $totals['discount'] += abs((float)$row['value']);
            }
            if ($row['code'] == 'tax') {
                $totals['vat'] += (float)$row['value'];
            }
            if ($row['code'] == 'total') {
                $totals['total'] = (float)$row['value'];
            }
        }
        return $totals;
    }

    protected function getRefunds(DateTime $from, DateTime $to, $store_id) {
        $refunds = [];
        $sql = "SELECT r.* FROM `" . DB_PREFIX . "nap_refund` r LEFT JOIN `" . DB_PREFIX . "order` o ON (r.order_id = o.order_id) WHERE r.refund_date >= '" . $from->format('Y-m-d') . "' AND r.refund_date < '" . $to->format('Y-m-d') . "'";
        if ($store_id !== null) {
            $sql .= " AND o.store_id = " . (int)$store_id;
        }
        $query = $this->db->query($sql);
        foreach ($query->rows as $row) {
            $refunds[] = [
                'order_id' => $row['order_id'],
                'refund_amount' => $row['refund_amount'],
                'refund_date' => $row['refund_date'],
                'refund_method' => $row['refund_method'] ?: ($this->settings['report_nap_audit_refund_default'] ?? 1)
            ];
        }
        return $refunds;
    }

    protected function logExport($year, $month, $store_id, $orders_count, $refunds_count, $file, array $params) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "nap_audit_log` SET year = " . (int)$year . ", month = '" . $this->db->escape(sprintf('%02d', $month)) . "', store_id = " . (int)$store_id . ", created_at = NOW(), admin_user_id = " . (int)($this->user ? $this->user->getId() : 0) . ", orders_count = " . (int)$orders_count . ", refunds_count = " . (int)$refunds_count . ", file_name = '" . $this->db->escape($file) . "', params = '" . $this->db->escape(json_encode($params)) . "'");
    }

    protected function getSetting($key) {
        if (!isset($this->settings[$key])) {
            throw new Exception('Missing configuration for ' . $key);
        }
        return $this->settings[$key];
    }
}
