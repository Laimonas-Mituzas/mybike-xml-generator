<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/config/config.php';

class Mybike_xml_generator extends Module
{
    public function __construct()
    {
        $this->name          = 'mybike_xml_generator';
        $this->tab           = 'administration';
        $this->version       = '1.0.0';
        $this->author        = 'Augu su Presta';
        $this->need_instance = 0;
        $this->bootstrap     = true;

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

        foreach ([MYBIKE_OUTPUT_DIR, MYBIKE_LOGS_DIR] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        return $this->installTab();
    }

    public function uninstall()
    {
        $keys = [
            'MYBIKE_API_KEY', 'MYBIKE_CRON_TOKEN',
            'MYBIKE_LAST_FULL_RUN', 'MYBIKE_LAST_FULL_COUNT',
            'MYBIKE_LAST_FULL_DURATION', 'MYBIKE_LAST_FULL_STATUS',
            'MYBIKE_LAST_STOCK_RUN', 'MYBIKE_LAST_STOCK_COUNT',
            'MYBIKE_LAST_STOCK_DURATION', 'MYBIKE_LAST_STOCK_STATUS',
        ];
        foreach ($keys as $key) {
            Configuration::deleteByName($key);
        }

        $this->uninstallTab();

        return parent::uninstall();
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMyBikeXmlGenerator'));
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
