<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/config/config.php';
require_once dirname(__FILE__) . '/classes/MyBikeCategoryManager.php';
require_once dirname(__FILE__) . '/classes/MyBikeSpecsVocab.php';
require_once dirname(__FILE__) . '/classes/MyBikePriceCalc.php';

class Mybike_xml_generator extends Module
{
    public function __construct()
    {
        $this->name          = 'mybike_xml_generator';
        $this->tab           = 'administration';
        $this->version       = '2.1.0';
        $this->author        = 'Augu su Presta';
        $this->need_instance = 0;
        $this->bootstrap     = true;
        $this->ps_versions_compliancy = ['min' => '1.7.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('MyBike XML Generator');
        $this->description = $this->l('MyBike tiekėjo prekių XML generavimas');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        Configuration::updateValue('MYBIKE_CRON_TOKEN', bin2hex(random_bytes(16)));
        Configuration::updateValue('MYBIKE_API_KEY', '');
        Configuration::updateValue('MYBIKE_ONLY_IN_STOCK', '0');
        Configuration::updateValue('MYBIKE_PRICE_KEY', 'price');
        Configuration::updateValue('MYBIKE_PRICE_COEFFICIENT', '1.00');
        Configuration::updateValue('MYBIKE_PRICE_WITH_VAT', '0');
        Configuration::updateValue('MYBIKE_IMPORT_TAX_RULES_ID', '0');

        foreach ([MYBIKE_OUTPUT_DIR, MYBIKE_LOGS_DIR] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        MyBikeCategoryManager::createTable();
        MyBikeSpecsVocab::createTable();
        MyBikeSpecsVocab::populate();
        $this->createStagingTables();

        return $this->installTab();
    }

    public function uninstall()
    {
        $keys = [
            'MYBIKE_API_KEY', 'MYBIKE_CRON_TOKEN', 'MYBIKE_ONLY_IN_STOCK',
            'MYBIKE_LAST_FULL_RUN', 'MYBIKE_LAST_FULL_COUNT',
            'MYBIKE_LAST_FULL_DURATION', 'MYBIKE_LAST_FULL_STATUS',
            'MYBIKE_LAST_STOCK_RUN', 'MYBIKE_LAST_STOCK_COUNT',
            'MYBIKE_LAST_STOCK_DURATION', 'MYBIKE_LAST_STOCK_STATUS',
            'MYBIKE_PRICE_KEY', 'MYBIKE_PRICE_COEFFICIENT',
            'MYBIKE_PRICE_WITH_VAT', 'MYBIKE_IMPORT_TAX_RULES_ID',
        ];
        foreach ($keys as $key) {
            Configuration::deleteByName($key);
        }

        MyBikeCategoryManager::dropTable();
        MyBikeSpecsVocab::dropTable();
        $this->dropStagingTables();
        $this->uninstallTab();

        return parent::uninstall();
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMyBikeXmlGenerator'));
    }

    private function createStagingTables()
    {
        $db     = Db::getInstance();
        $prefix = _DB_PREFIX_;

        $db->execute("
            CREATE TABLE IF NOT EXISTS `{$prefix}mybike_product` (
              `mybike_id`              INT UNSIGNED  NOT NULL,
              `standard_item_id`       VARCHAR(64)   NOT NULL DEFAULT '',
              `manufacturer_id`        VARCHAR(64)   NOT NULL DEFAULT '',
              `brand`                  VARCHAR(128)  NOT NULL DEFAULT '',
              `model`                  VARCHAR(255)  NOT NULL DEFAULT '',
              `type`                   VARCHAR(64)   NOT NULL DEFAULT '',
              `section`                VARCHAR(64)   NOT NULL DEFAULT '',
              `category`               VARCHAR(128)  NOT NULL DEFAULT '',
              `category_id`            INT UNSIGNED  NOT NULL DEFAULT 0,
              `sub_category`           VARCHAR(128)  NOT NULL DEFAULT '',
              `price`                  DECIMAL(10,4) NOT NULL DEFAULT 0,
              `base_price`             DECIMAL(10,4) NOT NULL DEFAULT 0,
              `color`                  VARCHAR(255)  NOT NULL DEFAULT '',
              `size`                   VARCHAR(64)   NOT NULL DEFAULT '',
              `color_comb_product_id`  VARCHAR(255)  NOT NULL DEFAULT '',
              `size_comb_product_id`   VARCHAR(64)   NOT NULL DEFAULT '',
              `featured`               TINYINT(1)    NOT NULL DEFAULT 0,
              `description`            TEXT,
              `specs`                  TEXT,
              `images`                 TEXT,
              `avail_status`           VARCHAR(64)   NOT NULL DEFAULT '',
              `avail_quantity`         INT           NOT NULL DEFAULT 0,
              `avail_date`             DATE          NULL,
              `ps_id_product`          INT UNSIGNED  NULL,
              `ps_id_product_attr`     INT UNSIGNED  NULL,
              `imported_at`            DATETIME      NULL,
              `date_upd`               DATETIME      NOT NULL,
              PRIMARY KEY (`mybike_id`),
              KEY `idx_standard_item` (`standard_item_id`),
              KEY `idx_section_cat`   (`section`, `category_id`),
              KEY `idx_ps_product`    (`ps_id_product`),
              KEY `idx_group`         (`brand`(64), `section`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $db->execute("
            CREATE TABLE IF NOT EXISTS `{$prefix}mybike_category_map` (
              `mybike_category_id`   INT UNSIGNED  NOT NULL,
              `mybike_section`       VARCHAR(64)   NOT NULL DEFAULT '',
              `mybike_category`      VARCHAR(128)  NOT NULL DEFAULT '',
              `mybike_product_count` INT UNSIGNED  NOT NULL DEFAULT 0,
              `ps_id_category`       INT UNSIGNED  NULL DEFAULT NULL,
              `date_upd`             DATETIME      NOT NULL,
              PRIMARY KEY (`mybike_category_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function dropStagingTables()
    {
        $prefix = _DB_PREFIX_;
        Db::getInstance()->execute("DROP TABLE IF EXISTS `{$prefix}mybike_product`");
        Db::getInstance()->execute("DROP TABLE IF EXISTS `{$prefix}mybike_category_map`");
    }

    private function installTab()
    {
        $tab = new Tab();
        $tab->active     = 1;
        $tab->class_name = 'AdminMyBikeXmlGenerator';
        $tab->module     = $this->name;
        $tab->id_parent  = (int)Tab::getIdFromClassName('AdminCatalog');
        $tab->name       = [];
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'MyBike XML Generator';
        }
        return $tab->add();
    }

    private function uninstallTab()
    {
        $tabId = (int)Tab::getIdFromClassName('AdminMyBikeXmlGenerator');
        if ($tabId) {
            $tab = new Tab($tabId);
            return $tab->delete();
        }
        return true;
    }
}
