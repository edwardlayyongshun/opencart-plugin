<?php

class ControllerPaymentHelloPay extends Controller
{

    const HELLOPAY_PURCHASE_ID = 'helloPayPurchaseId';

    /**
     * @var HelloPay\HelloPay helloPay object
     */
    protected $helloPay;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->init();
    }

    public function index()
    {
        $data['button_confirm'] = $this->language->get('button_confirm');

        $this->load->model('checkout/order');
        $this->load->model('tool/image');
        $this->load->model('localisation/country');

        $country_info = $this->model_localisation_country->getCountry($this->config->get('config_country_id'));

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $totalAmount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);

        $data = [];
        $data['priceAmount'] = $totalAmount;
        $data['priceCurrency'] = $order_info['currency_code'];
        $data['description'] = $this->config->get('config_meta_title') . " purchase create";
        $data['merchantReferenceId'] = $order_info['order_id'];
        $data['purchaseReturnUrl'] = $this->url->link('payment/hellopay/response', '', 'SSL');
        $data['purchaseCallbackUrl'] = $this->url->link('payment/hellopay/callback&token=' . $this->config->get('hellopay_secret_token'), '', 'SSL');
        $data['basket'] = [];
        $data['basket']['basketItems'] = [];

        $products = $this->cart->getProducts();

        foreach ($products as $product) {
            if ($product['image']) {
                $image = $this->model_tool_image->resize($product['image'], $this->config->get('config_image_cart_width'), $this->config->get('config_image_cart_height'));
            } else {
                $image = $this->model_tool_image->resize('placeholder.png', 55, 55);
            }
            $data['basket']['basketItems'][] = array(
                'name' => $product['name'],
                'quantity' => $product['quantity'],
                'amount' => $this->currency->format($product['price'], $order_info['currency_code'], $order_info['currency_value'], false),
                'taxAmount' => 0,
                'currency' => $order_info['currency_code'],
                'imageUrl' => $image
            );
        }

        $data['basket']['shipping'] = $this->currency->format($this->getShippingPrice(), $order_info['currency_code'], $order_info['currency_value'], false);
        $data['basket']['totalAmount'] = $totalAmount;
        $data['basket']['currency'] = $order_info['currency_code'];

        $data['billingAddress']['name'] = $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
        $data['billingAddress']['firstName'] = $order_info['payment_firstname'];
        $data['billingAddress']['lastName'] = $order_info['payment_lastname'];;
        $data['billingAddress']['addressLine1'] = $order_info['payment_address_1'];
        $data['billingAddress']['province'] = "";
        $data['billingAddress']['city'] = $order_info['payment_city'];
        $data['billingAddress']['country'] = $country_info['iso_code_2'];
        $data['billingAddress']['mobilePhoneNumber'] = $order_info['telephone'];
        $data['billingAddress']['houseNumber'] = "";
        $data['billingAddress']['addressLine2'] = $order_info['payment_address_2'];
        $data['billingAddress']['district'] = '';
        $data['billingAddress']['zip'] = $order_info['payment_postcode'];

        if ($this->cart->hasShipping()) {
            $data['shippingAddress']['name'] = $order_info['shipping_firstname'] . ' ' . $order_info['shipping_lastname'];
            $data['shippingAddress']['firstName'] = $order_info['shipping_firstname'];
            $data['shippingAddress']['lastName'] = $order_info['shipping_lastname'];
            $data['shippingAddress']['addressLine1'] = $order_info['shipping_address_1'];
            $data['shippingAddress']['province'] = '';
            $data['shippingAddress']['city'] = $order_info['shipping_city'];
            $data['shippingAddress']['country'] = $country_info['iso_code_2'];
            $data['shippingAddress']['mobilePhoneNumber'] = $order_info['telephone'];
            $data['shippingAddress']['houseNumber'] = "";
            $data['shippingAddress']['addressLine2'] = $order_info['shipping_address_2'];
            $data['shippingAddress']['district'] = "";
            $data['shippingAddress']['zip'] = $order_info['shipping_postcode'];
        } else {
            $data['shippingAddress'] = $data['billingAddress'];
        }

        $data['consumerData']['mobilePhoneNumber'] = $order_info['telephone'];
        $data['consumerData']['emailAddress'] = $order_info['email'];;
        $data['consumerData']['country'] = $country_info['iso_code_2'];
        $data['consumerData']['language'] = $order_info['language_code'];
        $data['consumerData']['dateOfBirth'] = "";
        $data['consumerData']['gender'] = "";
        $data['consumerData']['ipAddress'] = $order_info['ip'];
        $data['consumerData']['name'] = $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
        $data['consumerData']['firstName'] = $order_info['payment_firstname'];
        $data['consumerData']['lastName'] = $order_info['payment_lastname'];

        $data['additionalData']['platform'] = "OpenCart";

        $dataView = array(
            'error' => false
        );

        try {
            $response = $this->helloPay->createPurchase($data);

            if ($response) {
                $dataView['action'] = $response->getCheckoutUrl();
                $dataView['button_confirm'] = $this->language->get('button_confirm');
                $this->session->data[static::HELLOPAY_PURCHASE_ID] = $response->getPurchaseId();
            } else {
                $dataView['error'] = true;
                $dataView['errorMessage'] = $this->helloPay->getLastMessage();
            }
        } catch (\HelloPay\Exceptions\HelloPaySDKException $e) {
            $dataView['error'] = true;
            $dataView['errorMessage'] = $this->language->get('error_purchase_create');
            $this->log->write('helloPay :: ERROR CREATING PURCHASE! ' . $e->getMessage());
        }

        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/hellopay.tpl')) {
            return $this->load->view($this->config->get('config_template') . '/template/payment/hellopay.tpl', $dataView);
        } else {
            return $this->load->view('default/template/payment/hellopay.tpl', $dataView);
        }
    }

    /**
     * Handle the redirection from helloPay
     */
    public function response()
    {
        $this->load->model('checkout/order');

        $status = $this->request->get['paymentStatus'];

        $order_status_id = $this->config->get('config_order_status_id');
        $returnUrl = $this->url->link('checkout/success');

        if ($this->session->data['order_id'] > 0) {
            switch ($status) {
                case 'Cancelled':
                    $order_status_id = $this->config->get('hellopay_canceled_status_id');
                    $returnUrl = $this->url->link('checkout/checkout');
                    break;
                case 'Failed':
                    $order_status_id = $this->config->get('hellopay_failed_status_id');
                    break;
                case 'Pending':
                    $order_status_id = $this->config->get('hellopay_pending_status_id');
                    break;
                case 'Success':
                    try {
                        // query the status of the purchase first
                        $response = $this->helloPay->getTransactionEvents(array(
                            'transactionId' => $this->session->data[static::HELLOPAY_PURCHASE_ID],
                            'transactionType' => 'Purchase'
                        ));

                        if ($response && $response->isCompleted()) {
                            $order_status_id = $this->config->get('hellopay_completed_status_id');
                        } else {
                            $this->log->write('helloPay :: ERROR FETCHING TRANSACTION EVENTS! ' . $this->helloPay->getLastMessage());
                        }
                    } catch (\HelloPay\Exceptions\HelloPaySDKException $e) {
                        $this->log->write('helloPay :: ERROR FETCHING TRANSACTION EVENTS! ' . $e->getMessage());
                    }

                    $returnUrl = $this->url->link('checkout/success');
                    break;
            }

            $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $order_status_id);
        }

        $this->response->redirect($returnUrl);
        exit;
    }

    /**
     * Handle the notification service
     */
    public function callback()
    {
        // validate the secure hash
        $token = $this->request->get['token'];
        if ($token != $this->config->get('hellopay_secret_token')) {
            die();
        }

        $this->load->model('checkout/order');

        $statusMap = [
            'Completed' => $this->config->get('hellopay_completed_status_id'),
            'Cancelled' => $this->config->get('hellopay_cancelled_status_id'),
            'Failed' => $this->config->get('hellopay_failed_status_id'),
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $postData = file_get_contents('php://input');

            $response = $this->helloPay->parseNotificationPayload($postData);

            if ($response && is_array($response)) {
                foreach ($response as $item) {
                    if (array_key_exists($item->getNewStatus(), $statusMap)) {
                        $this->model_checkout_order->addOrderHistory(
                            $item->getMerchantReferenceId(),
                            $statusMap[$item->getNewStatus()]
                        );
                    }
                }
            }
        }
    }

    /**
     * Initialize the helloPay object by using SDK
     */
    protected function init()
    {
        require_once(DIR_SYSTEM . 'library/helloPay/autoload.php');

        $this->helloPay = new \HelloPay\HelloPay(
            array(
                'shopConfig' => $this->config->get('hellopay_shop_config'),
                'apiUrl' => $this->config->get('hellopay_api_url')
            )
        );
    }

    /**
     * Get the total shipping price
     *
     * @return int
     */
    protected function getShippingPrice()
    {
        $total_data = array();
        $total = 0;
        $taxes = $this->cart->getTaxes();

        $this->load->model('total/shipping');

        $this->model_total_shipping->getTotal($total_data, $total, $taxes);

        return $total;
    }
}
