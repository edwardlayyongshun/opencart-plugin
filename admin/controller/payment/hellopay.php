<?php

class ControllerPaymentHelloPay extends Controller
{
    const NAME_API_URL = 'hellopay_api_url';
    const NAME_SHOP_CONFIG = 'hellopay_shop_config';
    const NAME_TOTAL = 'hellopay_total';
    const NAME_SORT_ORDER = 'hellopay_sort_order';
    const NAME_GEO_ZONE_ID = 'hellopay_geo_zone_id';
    const NAME_STATUS = 'hellopay_status';
    const NAME_ORDER_STATUS_ID = 'hellopay_order_status_id';
    const NAME_SECRET_TOKEN = 'hellopay_secret_token';
    const NAME_WEBHOOK_URL = 'hellopay_webhook_url';

    const SUCCESS_STATUS = 'hellopay_completed_status_id';
    const FAILED_STATUS = 'hellopay_failed_status_id';
    const PENDING_STATUS = 'hellopay_pending_status_id';
    const CANCELLED_STATUS = 'hellopay_cancelled_status_id';

    private $data = array();
    private $error = array();

    public function index()
    {
        $this->load->language('payment/hellopay');
        $this->load->model('setting/setting');

        $this->document->setTitle($this->language->get('heading_title'));

        // handle the submmittion
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('hellopay', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'));
        }

        $this->data['heading_title'] = $this->language->get('heading_title');

        $this->data['text_edit'] = $this->language->get('text_edit');
        $this->data['text_enabled'] = $this->language->get('text_enabled');
        $this->data['text_disabled'] = $this->language->get('text_disabled');
        $this->data['text_all_zones'] = $this->language->get('text_all_zones');
        $this->data['text_yes'] = $this->language->get('text_yes');
        $this->data['text_no'] = $this->language->get('text_no');

        $this->data['entry_api_url'] = $this->language->get('entry_api_url');
        $this->data['entry_shop_config'] = $this->language->get('entry_shop_config');
        $this->data['entry_secret'] = $this->language->get('entry_secret');
        $this->data['entry_test'] = $this->language->get('entry_test');
        $this->data['entry_total'] = $this->language->get('entry_total');
        $this->data['entry_order_status'] = $this->language->get('entry_order_status');
        $this->data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
        $this->data['entry_status'] = $this->language->get('entry_status');
        $this->data['entry_sort_order'] = $this->language->get('entry_sort_order');
        $this->data['entry_webhook_url'] = $this->language->get('entry_webhook_url');
        $this->data['entry_success_status'] = $this->language->get('entry_success_status');
        $this->data['entry_failed_status'] = $this->language->get('entry_failed_status');
        $this->data['entry_cancelled_status'] = $this->language->get('entry_cancelled_status');
        $this->data['entry_pending_status'] = $this->language->get('entry_pending_status');

        $this->data['tab_settings'] = $this->language->get('tab_settings');
        $this->data['tab_order_status'] = $this->language->get('tab_order_status');

        $this->data['help_shop_config'] = $this->language->get('help_shop_config');
        $this->data['help_total'] = $this->language->get('help_total');
        $this->data['help_secret'] = $this->language->get('help_secret');
        $this->data['help_webhook_url'] = $this->language->get('help_webhook_url');

        $this->data['button_save'] = $this->language->get('button_save');
        $this->data['button_cancel'] = $this->language->get('button_cancel');

        // errors checking
        $errorsToBeChecked = ['warning', 'api_url', 'shop_config', 'secret'];

        foreach ($errorsToBeChecked as $key) {
            $this->checkError($key);
        }

        $this->data['breadcrumbs'] = array();

        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL')
        );

        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_payment'),
            'href' => $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL')
        );

        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('payment/hellopay', 'token=' . $this->session->data['token'], 'SSL')
        );

        $this->data['action'] = $this->url->link('payment/hellopay', 'token=' . $this->session->data['token'], 'SSL');

        $this->data['cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL');

        $this->prepareData(static::NAME_API_URL);
        $this->prepareData(static::NAME_SHOP_CONFIG);
        $this->prepareData(static::NAME_TOTAL);
        $this->prepareData(static::NAME_SORT_ORDER);
        $this->prepareData(static::NAME_GEO_ZONE_ID);
        $this->prepareData(static::NAME_STATUS);
        $this->prepareData(static::NAME_ORDER_STATUS_ID);
        $this->prepareData(static::SUCCESS_STATUS);
        $this->prepareData(static::CANCELLED_STATUS);
        $this->prepareData(static::PENDING_STATUS);
        $this->prepareData(static::FAILED_STATUS);

        $secretToken = $this->getSecretToken();
        $this->prepareData(static::NAME_SECRET_TOKEN, $secretToken);
        $this->data[static::NAME_WEBHOOK_URL] = $this->getWebhookUrl($secretToken);

        $this->load->model('localisation/order_status');

        $this->data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $this->load->model('localisation/geo_zone');

        $this->data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $this->data['header'] = $this->load->controller('common/header');
        $this->data['column_left'] = $this->load->controller('common/column_left');
        $this->data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('payment/hellopay.tpl', $this->data));
    }

    /**
     * Check error by key
     *
     * @param string $key
     */
    protected function checkError($key)
    {
        if (isset($this->error[$key])) {
            $this->data['error_' . $key] = $this->error[$key];
        } else {
            $this->data['error_' . $key] = '';
        }
    }

    /**
     * Validate the user's permission & input data
     *
     * @return bool
     */
    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'payment/hellopay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post[static::NAME_API_URL]) {
            $this->error['api_url'] = $this->language->get('error_api_url');
        }

        if (!$this->request->post[static::NAME_SHOP_CONFIG]) {
            $this->error['shop_config'] = $this->language->get('error_shop_config');
        }

        if (!$this->request->post[static::NAME_SECRET_TOKEN]) {
            $this->error['secret'] = $this->language->get('error_secret');
        }

        return !$this->error;
    }

    /**
     * Bind data to view by key - value
     *
     * @param string $key
     * @param mixed $defaultValue
     */
    protected function prepareData($key, $defaultValue = null)
    {
        if (isset($this->request->post[$key])) {
            $this->data[$key] = $this->request->post[$key];
        } else {
            $this->data[$key] = !is_null($defaultValue) ? $defaultValue : $this->config->get($key);
        }
    }

    /**
     * Get the secure hash for callback url
     *
     * @return string
     */
    protected function getSecretToken()
    {
        return $this->config->get(static::NAME_SECRET_TOKEN) != ''
            ? $this->config->get(static::NAME_SECRET_TOKEN)
            : hash('sha256', time());
    }

    /**
     * Get the callback url for notification service
     *
     * @param string $token the given secure hash
     * @return string
     */
    protected function getWebhookUrl($token)
    {
        return HTTPS_CATALOG . 'index.php?route=payment/hellopay/callback&token=' . $token;
    }
}
