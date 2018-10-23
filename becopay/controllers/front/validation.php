<?php

use Becopay\PaymentGateway;

class becopayValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {

        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 ||
            $cart->id_address_invoice == 0 || !$this->module->active ||
            !isset($_GET['orderId'])
        )
            Tools::redirect('index.php?controller=order&step=1');

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module)
            if ($module['name'] == 'becopay') {
                $authorized = true;
                break;
            }
        if (!$authorized)
            die($this->module->l('This payment method is not available.', 'validation'));

        //verify customer
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');


        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $mailVars = array();

        $paymentOrderId = $_GET['orderId'];
        $invoice = self::__checkInvoice($paymentOrderId, $cart);

        if ($invoice && $invoice->status != 'waiting') {

            if ($invoice->status == 'success') {

                $this->module->validateOrder(
                    $cart->id, Configuration::get('PS_OS_PAYMENT'),
                    $total, $this->module->displayName, null, $mailVars,
                    (int)$currency->id, false, $customer->secure_key);

                $this->__updateBecopayOrder($invoice->id, $this->module->currentOrder, $invoice->status);

                $this->saveOrderTransactionData($invoice->id, $this->module->displayName, $this->module->currentOrder);

                Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key);
            } else
                $this->__updateBecopayOrder($invoice->id, null, $invoice->status);
        }

        Tools::redirect('index.php?controller=order&step=1');
    }

    public function __checkInvoice($paymentOrderId, $cart)
    {
        //get order total price
        $total = $cart->getOrderTotal(true);

        //get api service configuration
        $mobile = Configuration::get(BECOPAY_PREFIX . 'mobile');
        $apiBaseUrl = Configuration::get(BECOPAY_PREFIX . 'apiBaseUrl');
        $apiKey = Configuration::get(BECOPAY_PREFIX . 'apiKey');

        //get cart id
        $cartId = intval($cart->id);

        //get becopay order information
        $becopayOrder = self::__selectBecopayOrder($cartId, $paymentOrderId);

        if (!$becopayOrder || !is_null($becopayOrder['order_id'])) {
            error_log('invalid payment order id, ' . $paymentOrderId);
            return false;
        }

        try {
            $payment = new PaymentGateway($apiBaseUrl, $apiKey, $mobile);

            //check becopay invoice
            $invoice = $payment->checkByOrderId($paymentOrderId);

            if ($invoice) {

                if ($invoice->price != intval($total)) {
                    error_log('invoice price not same. invoiceId:' . $invoice->id . ' ,card id : ' . $cartId . ', payment id:' . $becopayOrder['becopay_invoice_id'] .
                        ', cost = ' . $total . ', status = ' . $invoice->status);
                    return false;
                }
                //                $this->invoiceResponse = $invoice;
                return $invoice;

            } else {
                error_log("PaymentGateway Error: " . $payment->error);
                return false;
            }
        } catch (\Exception $e) {
            error_log("Exception PaymentGateway : " . $e->getMessage());
            return false;
        }
    }


    /**
     * @param $cartId
     * @param $paymentOrderId
     * @return mixed
     */
    private function __selectBecopayOrder($cartId, $paymentOrderId)
    {

        $sql = new DbQuery();
        $sql->select('`id`,`order_id`,`becopay_invoice_id`,`status`')
            ->from('order_becopay')
            ->where(
                'becopay_order_id = "' . $paymentOrderId . '" AND ' .
                'cart_id = "' . $cartId . '"'
            )
            ->limit(1, 0);
        $result = Db::getInstance()->executeS($sql);

        if (empty($result))
            return false;

        return reset($result);
    }

    private function __updateBecopayOrder($invoice, $order_id, $status)
    {
        $db = Db::getInstance();

        $query = 'UPDATE `' . _DB_PREFIX_ . 'order_becopay` SET `order_id`="' . $order_id .
            '",`status`="' . $status . '" where becopay_invoice_id = "' . $invoice . '"';

        $db->Execute($query);
    }

    /**
     * Retrieves the OrderPayment object, created at validateOrder. And add transaction data.
     *
     * @param string $transactionId
     * @param string $paymentMethod
     * @param int    $orderId
     *
     * @return void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function saveOrderTransactionData($transactionId, $paymentMethod, $orderId)
    {
        // retrieve ALL payments of order.
        // if no OrderPayment objects is retrieved in the collection, do nothing.
        $order = new Order((int)$orderId);
        $collection = OrderPayment::getByOrderReference($order->reference);
        if (count($collection) > 0) {
            $orderPayment = $collection[0];
            // for older versions (1.5) , we check if it hasn't been filled yet.
            if (!$orderPayment->transaction_id) {
                $orderPayment->transaction_id = $transactionId;
                $orderPayment->payment_method = $paymentMethod;
                $orderPayment->update();
            }
        }
    }
}
