<?php
/**
 * OpenCart 3.0.4.1 NRA audit export module controller
 *
 * Generates XML audit file for NRA according to dec_audit.xsd.
 */
class ControllerExtensionReportNapAudit extends Controller {
    private $error = [];

    public function index() {
        $this->load->language('extension/report/nap_audit');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $this->load->model('localisation/order_status');
        $this->load->model('setting/store');
        $this->load->model('extension/report/nap_audit');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('report_nap_audit', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/report/nap_audit', 'user_token=' . $this->session->data['user_token'], true));
        }

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['text_select'] = $this->language->get('text_select');

        $data['tab_store'] = $this->language->get('tab_store');
        $data['tab_vat'] = $this->language->get('tab_vat');
        $data['tab_payment'] = $this->language->get('tab_payment');
        $data['tab_refund'] = $this->language->get('tab_refund');
        $data['tab_export'] = $this->language->get('tab_export');
        $data['tab_history'] = $this->language->get('tab_history');

        $data['entry_eik'] = $this->language->get('entry_eik');
        $data['entry_shop_id'] = $this->language->get('entry_shop_id');
        $data['entry_domain'] = $this->language->get('entry_domain');
        $data['entry_shop_type'] = $this->language->get('entry_shop_type');
        $data['entry_default_vat'] = $this->language->get('entry_default_vat');
        $data['entry_year'] = $this->language->get('entry_year');
        $data['entry_month'] = $this->language->get('entry_month');
        $data['entry_store'] = $this->language->get('entry_store');
        $data['entry_order_status'] = $this->language->get('entry_order_status');
        $data['entry_paym_code'] = $this->language->get('entry_paym_code');
        $data['entry_paym_type'] = $this->language->get('entry_paym_type');
        $data['entry_pos'] = $this->language->get('entry_pos');
        $data['entry_proc'] = $this->language->get('entry_proc');
        $data['entry_refund_default'] = $this->language->get('entry_refund_default');
        $data['entry_invoice_date'] = $this->language->get('entry_invoice_date');

        $data['help_vat'] = $this->language->get('help_vat');
        $data['help_payment'] = $this->language->get('help_payment');

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['button_generate'] = $this->language->get('button_generate');
        $data['button_preview'] = $this->language->get('button_preview');

        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=report', true)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/report/nap_audit', 'user_token=' . $this->session->data['user_token'], true)
        ];

        $data['action'] = $this->url->link('extension/report/nap_audit', 'user_token=' . $this->session->data['user_token'], true);
        $data['generate'] = $this->url->link('extension/report/nap_audit/generate', 'user_token=' . $this->session->data['user_token'], true);

        $config_keys = [
            'report_nap_audit_eik',
            'report_nap_audit_shop_id',
            'report_nap_audit_domain',
            'report_nap_audit_shop_type',
            'report_nap_audit_default_vat',
            'report_nap_audit_payment_map',
            'report_nap_audit_refund_default',
            'report_nap_audit_invoice_date'
        ];

        foreach ($config_keys as $key) {
            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } else {
                $data[$key] = $this->config->get($key);
            }
        }

        if (!$data['report_nap_audit_payment_map']) {
            $data['report_nap_audit_payment_map'] = [];
        }

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['stores'] = $this->model_setting_store->getStores();
        array_unshift($data['stores'], ['store_id' => 0, 'name' => $this->config->get('config_name')]);

        $data['payment_methods'] = $this->model_extension_report_nap_audit->getPaymentMethods();
        $data['paym_options'] = [1,2,3,4,5,6];
        $data['refund_options'] = [1,2,3,4];
        $data['invoice_dates'] = [
            'date_added',
            'date_modified',
            'invoice_date'
        ];

        $data['user_token'] = $this->session->data['user_token'];
        $data['error_warning'] = $this->error['warning'] ?? '';
        $data['success'] = $this->session->data['success'] ?? '';
        unset($this->session->data['success']);

        $data['history'] = $this->model_extension_report_nap_audit->getHistory();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/report/nap_audit', $data));
    }

    public function install() {
        $this->load->model('extension/report/nap_audit');
        $this->model_extension_report_nap_audit->install();
    }

    public function uninstall() {
        $this->load->model('extension/report/nap_audit');
        $this->model_extension_report_nap_audit->uninstall();
    }

    public function generate() {
        $this->load->language('extension/report/nap_audit');
        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/report/nap_audit')) {
            $json['error'] = $this->language->get('error_permission');
        }

        $year = (int)$this->request->post['year'];
        $month = $this->request->post['month'];
        $store_id = (int)$this->request->post['store_id'];
        $order_statuses = $this->request->post['order_status'] ?? [];

        if (!$year || !$month) {
            $json['error'] = $this->language->get('error_period');
        }

        if (!$json) {
            $this->load->model('extension/report/nap_audit');
            $this->load->model('setting/setting');
            $settings = $this->model_setting_setting->getSetting('report_nap_audit');

            try {
                require_once DIR_SYSTEM . 'library/nap_audit/Helper.php';
                $helper = new \NapAudit\Helper($this->registry, $settings);
                $file = $helper->buildXml($year, $month, $store_id, $order_statuses);
                $json['success'] = $this->language->get('text_generated');
                $json['file'] = $file;
            } catch (Exception $e) {
                $json['error'] = $e->getMessage();
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/report/nap_audit')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}
