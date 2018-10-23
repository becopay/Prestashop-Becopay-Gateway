<?php

require_once __DIR__ . '/vendor/autoload.php';

use Becopay\PaymentGateway;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_'))
    exit;


/**
 * Define becopay prefix for use variable
 */
define('BECOPAY_PREFIX', 'becopay_');

class Becopay extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    /**
     * Plugin configuration value
     *
     * @var array
     */
    private $config = array(
        'configuration' => array(
            array(
                'title' => 'Mobile',
                'name' => 'mobile',
                'isRequired' => true,
                'type' => 'text',
                'placeholder' => '09...',
                'description' => 'Enter the phone number you registered in the Becopay here'
            ),
            array(
                'title' => 'Api Base URL',
                'name' => 'apiBaseUrl',
                'isRequired' => true,
                'type' => 'url',
                'placeholder' => 'https://api...',
                'description' => 'Enter Becopay api base url here'
            ),
            array(
                'title' => 'Api Key',
                'name' => 'apiKey',
                'isRequired' => true,
                'type' => 'text',
                'placeholder' => 'GEH45WS...',
                'description' => 'Enter your Becopay Api Key here'
            ),
        )
    );

    /**
     * Becopay constructor.
     */
    public function __construct()
    {

        $this->name = 'becopay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Becopay Team';

        $this->controllers = array('payment', 'validation');

        $this->is_eu_compatible = 1;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        //use bootstrap style
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Becopay');
        $this->description = $this->l('Pay via becopay: pay economically with cryptocurrency');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');

    }

    /**
     * Call this function when install plugin
     *
     * @return bool
     */
    public function install()
    {

        if (!function_exists('curl_version')) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module.');
            return false;
        }

        if (!parent::install() || !$this->registerHook('invoice') || !$this->registerHook('payment') || !$this->registerHook('paymentReturn') || !$this->registerHook('paymentOptions')) {
            return false;
        }

        /**
         * Create Becopay order table
         */
        $this->__createBecopayTable();

        return true;
    }

    /**
     * Call this function when uninstall plugin
     *
     * @return mixed
     */
    public function uninstall()
    {
        /**
         * Drop Becopay order table
         */
        $this->__dropBecopayTable();

        //delete configuration records
        foreach ($this->config['configuration'] as $config)
            Configuration::deleteByName(BECOPAY_PREFIX . $config['name']);

        return parent::uninstall();
    }

    /**
     * Return plugin configuration page
     *
     * @return string
     */
    public function getContent()
    {

        //Save Form configuration post data
        $this->__saveConfiguration();

        //Show Configuration form
        $this->__setConfigurationForm();

        return $this->_html;
    }


    /**
     * Run when show payment options list
     *
     * @param $params
     * @return array|void
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        $payment_options = array(
            $this->linkToBecopay(),
        );


        return $payment_options;
    }


    /**
     * Link to becopay gateway and show becopay on payment options list
     *
     * @return PaymentOption
     */
    public function linkToBecopay()
    {
        global $smarty;

        $smarty->assign(array(
            'description' => $this->description
        ));

        $becopay_option = new PaymentOption();
        $becopay_option->setCallToActionText($this->displayName)
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
            ->setAdditionalInformation($this->fetch('module:' . $this->name . '/view/becopay_intro.tpl'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/logo.png'));

        return $becopay_option;
    }

    public function hookPayment($params)
    {
        global $smarty;

        $smarty->assign(array(
                'this_path' => $this->_path,
                'description' => $this->description
            )
        );

        return $this->display(__FILE__, '/view/payment.tpl');
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
        /** @var order $order */
        $order = $params['order'];
        $currency = new Currency($order->id_currency);

        if (strcasecmp($order->module, $this->name) != 0) {
            return false;
        }

        if (Tools::getValue('status') != 'failed' && $order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR')) {
            $this->smarty->assign('status', 'ok');
        }

        $this->smarty->assign(array(
            'id_order'  => $order->id,
            'reference' => $order->reference,
            'params'    => $params,
            'total'     => Tools::displayPrice($order->getOrdersTotalPaid(), $currency, false),
        ));

        return $this->fetch('module:becopay/view/payment_return.tpl');
    }


    /**
     * Generate Configuration page
     */
    private function __setConfigurationForm()
    {
        ob_start();
        $formFields = $this->__getConfigurationFields();
        include 'view/formConfiguration.php';

        $this->_html .= ob_get_clean();
    }

    /**
     * Generate and return configuration fields html elements
     *
     * @return string
     */
    private function __getConfigurationFields()
    {
        ob_start();

        foreach ($this->config['configuration'] as $config) {
            include 'view/formFields.php';
        }

        $html = ob_get_clean();

        return $html;
    }

    /**
     * Save Form configuration
     * receive data with post method
     */
    private function __saveConfiguration()
    {
        //Check is submit the form
        if (Tools::isSubmit(BECOPAY_PREFIX . 'submit')) {

            //clear errors messages
            $this->_errors = array();

            //validate is set configuration field
            foreach ($this->config['configuration'] as $config) {
                if ($config['isRequired'] && Tools::getValue(BECOPAY_PREFIX . $config['name']) == NULL)
                    $this->_errors[] = $this->l($config['title'] . ' is require');
            }

            //if has no errors check with PaymentGateway Constructor validation
            if (empty($this->_errors)) {
                try {
                    new PaymentGateway(
                        Tools::getValue(BECOPAY_PREFIX . 'apiBaseUrl'),
                        Tools::getValue(BECOPAY_PREFIX . 'apiKey'),
                        Tools::getValue(BECOPAY_PREFIX . 'mobile')
                    );
                } catch (\Exception $e) {
                    $this->_errors[] = $e->getMessage();
                }
            }

            //Display error messages if has error
            if (!empty($this->_errors)) {
                $this->_html = $this->displayError(implode('<br>', $this->_errors));
            } else {

                //save configuration form fields
                foreach ($this->config['configuration'] as $config)
                    if (Tools::getValue(BECOPAY_PREFIX . $config['name']) != NULL)
                        Configuration::updateValue(BECOPAY_PREFIX . $config['name'], trim(Tools::getValue(BECOPAY_PREFIX . $config['name'])));


                //display confirmation message
                $this->_html = $this->displayConfirmation($this->l('Settings updated'));
            }
        }
    }

    /**
     *
     */
    private function __createBecopayTable()
    {
        $db = Db::getInstance();

        $query = "CREATE TABLE `" . _DB_PREFIX_ . "order_becopay` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `cart_id` int(11) NOT NULL,
                `order_id` int(11) default NULL,
                `becopay_order_id` varchar(255) NOT NULL UNIQUE,
                `becopay_invoice_id` varchar(255) NOT NULL UNIQUE,
                `status` enum('waiting','success','failed') default 'waiting',
                `create_at` TIMESTAMP NOT NULL DEFAULT NOW(),
                `update_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE NOW(),
                PRIMARY KEY(id),
                UNIQUE KEY (`cart_id`,`order_id`,`becopay_order_id`)
                ) ENGINE=" . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        $db->Execute($query);
    }

    /**
     *
     */
    private function __dropBecopayTable()
    {
        $db = Db::getInstance();

        $query = "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "order_becopay`";

        $db->Execute($query);
    }
}


?>
