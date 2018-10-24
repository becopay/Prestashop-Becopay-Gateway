<form method="post" action="<?php echo htmlentities($_SERVER['REQUEST_URI']) ?>">
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-cog"></i>
            setting
        </div>
        <div class="panel-body">
            <?php echo $formFields ?>

            <p><b>CallBack url: </b><?php
                    if (_PS_VERSION_ <= '1.5')
                        echo (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://') . htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8') . __PS_BASE_URI__ . 'order-confirmation.php?id_cart=' . $cart->id . '&id_module=' . $this->id . '&id_order=';
                    else
                        echo Context::getContext()->link->getModuleLink('becopay', 'validation').'?orderId=';
                ?></p>
            <p><b>Important Note</b>: The minimum price of the product should be 10000 IRR.</p>
        </div>
        <div class="panel-footer">
            <button type="submit" value="submit" name="<?php echo BECOPAY_PREFIX . 'submit' ?>"
                    class="btn btn-default pull-right btn-lg">
                <?php echo $this->l('Save settings') ?>
            </button>
        </div>
    </div>
</form>