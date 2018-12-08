<?php

use Becopay\PaymentGateway;

/**
 * Class becopayPaymentModuleFrontController
 */
class becopayPaymentModuleFrontController extends ModuleFrontController
{
    /**
     * @var bool
     */
    public $ssl = true;
    /**
     * @var bool
     */
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
     * @return mixed
     */
    private function __createInvoice($cart)
    {

        global $currency;

        //get api service configuration
        $mobile = Configuration::get(BECOPAY_PREFIX . 'mobile');
        $apiBaseUrl = Configuration::get(BECOPAY_PREFIX . 'apiBaseUrl');
        $apiKey = Configuration::get(BECOPAY_PREFIX . 'apiKey');
        $merchantCurrency = Configuration::get(BECOPAY_PREFIX . 'merchantCurrency') ?: DEFAULT_MERCHANT_CURRENCY;

        //get order total price
        $total = floatval($cart->getOrderTotal(true));
        $currency = $currency->iso_code;

        $customerId = (int)$cart->id_customer;
        $cartId = intval($cart->id);
        $paymentOrderId = uniqid($cartId . '-');

        $description = implode(array(
            'card id:' . $cartId,
            'customer id:' . $customerId,
            'price: '.$total.' '.$currency
        ),', ');

        error_log('card id : ' . $cartId . ', payment id:' . $paymentOrderId .
            ', cost = ' . $total . ' ' . $currency . ', merchant currency = '.$merchantCurrency);

        try {
            $payment = new PaymentGateway($apiBaseUrl, $apiKey, $mobile);

            //Create becopay invoice
            $invoice = $payment->create($paymentOrderId, $total, $description,$currency,$merchantCurrency);
            if ($invoice) {
                error_log('invoiceId:' . $invoice->id . ' ,card id : ' . $cartId . ', payment id:' . $paymentOrderId . ' cost = ' . $total);

                if(
                    $invoice->payerCur != $currency ||
                    $invoice->payerAmount != $total ||
                    $invoice->merchantCur != $merchantCurrency
                )
                {
                    error_log('Error: gateway price or currency is not same with order');
                    return Tools::redirect('index.php?controller=order&step=1');

                }

                //Insert Becopay invoice on order_becopay table
                self::__insertBecopayOrder($cartId, $paymentOrderId, $invoice->id);

                Tools::redirect($invoice->gatewayUrl);
            } else
                error_log("Exception PaymentGateway : " . $payment->error);

        } catch (\Exception $e) {
            error_log("Exception PaymentGateway : " . $e->getMessage());
        }

        return Tools::redirect('index.php?controller=order&step=1');
    }

    /**
     * Insert Becopay invoice on order_becopay table
     *
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
