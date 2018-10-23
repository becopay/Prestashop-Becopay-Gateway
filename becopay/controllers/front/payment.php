<?php

use Becopay\PaymentGateway;

class becopayPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $this->__createInvoice($this->context->cart);
        die;
    }

    /**
     * Create becopay invoice and redirect user to becopay gateway url
     *
     * @param $cart
     */
    private function __createInvoice($cart)
    {
        //get order total price
        $total = $cart->getOrderTotal(true);

        //get api service configuration
        $mobile = Configuration::get(BECOPAY_PREFIX . 'mobile');
        $apiBaseUrl = Configuration::get(BECOPAY_PREFIX . 'apiBaseUrl');
        $apiKey = Configuration::get(BECOPAY_PREFIX . 'apiKey');

        $cartId = intval($cart->id);
        $paymentOrderId = uniqid($cartId . '-');

        error_log('card id : ' . $cartId . ', payment id:' . $paymentOrderId . ', cost = ' . $total);

        $customerId = (int)$cart->id_customer;

        try {
            $payment = new PaymentGateway($apiBaseUrl, $apiKey, $mobile);

            //Create becopay invoice
            $invoice = $payment->create($paymentOrderId, intval($total), 'card id:' . $cartId . ', customer id:' . $customerId);
            if ($invoice) {
                error_log('invoiceId:' . $invoice->id . ' ,card id : ' . $cartId . ', payment id:' . $paymentOrderId . ' cost = ' . $total);

                //Insert Becopay invoice on order_becopay table
                self::__insertBecopayOrder($cartId,$paymentOrderId,$invoice->id);

                Tools::redirect($invoice->gatewayUrl);
            } else
                error_log("Exception PaymentGateway : " . $payment->error);
        } catch (\Exception $e) {
            error_log("Exception PaymentGateway : " . $e->getMessage());
        }

    }

    /**
     * Insert Becopay invoice on order_becopay table
     * @param $cartId
     * @param $paymentOrderId
     * @param $invoiceId
     */
    private function __insertBecopayOrder($cartId, $paymentOrderId, $invoiceId)
    {
        $db = Db::getInstance();
        $query = 'INSERT INTO `' . _DB_PREFIX_ . 'order_becopay`(`cart_id`,`becopay_order_id`, `becopay_invoice_id`) VALUES ("' .
            $cartId . '","' . $paymentOrderId . '","' . $invoiceId . '")';

        $db->Execute($query);
    }
}
