<div class="form-group">
    <label for="<?php echo BECOPAY_PREFIX . $config['name'] ?>"><?php echo $this->l($config['title']) ?></label>
    <?php
    echo '<input class="form-control"' .
        ' aria-describedby="' . BECOPAY_PREFIX . $config['name'] . 'Help"' .
        ' type="' . $config['type'] . '"' .
        ' placeholder="' . $config['placeholder'] . '"' .
        ' id="' . BECOPAY_PREFIX . $config['name'] . '"' .
        ' name="' . BECOPAY_PREFIX . $config['name'] . '"' .
        ' value="' . htmlentities(Tools::getValue(BECOPAY_PREFIX . $config['name'], Configuration::get(BECOPAY_PREFIX . $config['name'])), ENT_COMPAT, 'UTF-8') . '"' .
        ($config['isRequired'] ? " required" : '') .
        '>';
    ?>
    <small id="<?php echo BECOPAY_PREFIX . $config['name']?>Help" class="form-text text-muted"><?php echo $config['description'] ?></small>
</div>