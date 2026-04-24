<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../config/config.php';
require_once dirname(__FILE__) . '/../../classes/MyBikeLogger.php';
require_once dirname(__FILE__) . '/../../classes/MyBikeApiClient.php';
require_once dirname(__FILE__) . '/../../classes/MyBikeFullXmlBuilder.php';
require_once dirname(__FILE__) . '/../../classes/MyBikeStockXmlBuilder.php';
require_once dirname(__FILE__) . '/../../classes/MyBikeFullSync.php';
require_once dirname(__FILE__) . '/../../classes/MyBikeStockSync.php';

class AdminMyBikeXmlGeneratorController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap  = true;
        $this->meta_title = 'MyBike XML Generator';
        parent::__construct();
    }

    public function initContent()
    {
        parent::initContent();

        if (Tools::isSubmit('save_config')) {
            $this->saveConfig();
        } elseif (Tools::isSubmit('regen_token')) {
            $this->regenToken();
        } elseif (Tools::isSubmit('run_full')) {
            $this->runFullSync();
        } elseif (Tools::isSubmit('run_stock')) {
            $this->runStockSync();
        }

        $this->renderPage();
    }

    private function saveConfig()
    {
        $apiKey = trim(Tools::getValue('api_key'));
        if ($apiKey) {
            Configuration::updateValue('MYBIKE_API_KEY', pSQL($apiKey));
            $this->confirmations[] = $this->l('Konfigūracija išsaugota.');
        }
    }

    private function regenToken()
    {
        Configuration::updateValue('MYBIKE_CRON_TOKEN', bin2hex(random_bytes(16)));
        $this->confirmations[] = $this->l('Cron token sugeneruotas iš naujo.');
    }

    private function runFullSync()
    {
        $apiKey = Configuration::get('MYBIKE_API_KEY');
        if (!$apiKey) {
            $this->errors[] = $this->l('API raktas nenurodytas.');
            return;
        }

        set_time_limit(600);
        $logger = new MyBikeLogger(MYBIKE_FULL_LOG);
        $sync   = new MyBikeFullSync(new MyBikeApiClient($apiKey), $logger);

        try {
            $result = $sync->run();
            Configuration::updateValue('MYBIKE_LAST_FULL_RUN',      date('Y-m-d H:i:s'));
            Configuration::updateValue('MYBIKE_LAST_FULL_COUNT',    $result['count']);
            Configuration::updateValue('MYBIKE_LAST_FULL_DURATION', $result['duration']);
            Configuration::updateValue('MYBIKE_LAST_FULL_STATUS',   'ok');
            $this->confirmations[] = 'Full sync atliktas: ' . $result['count'] . ' produktų, ' . $result['duration'] . 's';
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            Configuration::updateValue('MYBIKE_LAST_FULL_STATUS', 'error: ' . $e->getMessage());
            $this->errors[] = $e->getMessage();
        }
    }

    private function runStockSync()
    {
        $apiKey = Configuration::get('MYBIKE_API_KEY');
        if (!$apiKey) {
            $this->errors[] = $this->l('API raktas nenurodytas.');
            return;
        }

        set_time_limit(300);
        $logger = new MyBikeLogger(MYBIKE_STOCK_LOG);
        $sync   = new MyBikeStockSync(new MyBikeApiClient($apiKey), $logger);

        try {
            $result = $sync->run();
            Configuration::updateValue('MYBIKE_LAST_STOCK_RUN',      date('Y-m-d H:i:s'));
            Configuration::updateValue('MYBIKE_LAST_STOCK_COUNT',    $result['count']);
            Configuration::updateValue('MYBIKE_LAST_STOCK_DURATION', $result['duration']);
            Configuration::updateValue('MYBIKE_LAST_STOCK_STATUS',   'ok');
            $this->confirmations[] = 'Stock sync atliktas: ' . $result['count'] . ' produktų, ' . $result['duration'] . 's';
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            Configuration::updateValue('MYBIKE_LAST_STOCK_STATUS', 'error: ' . $e->getMessage());
            $this->errors[] = $e->getMessage();
        }
    }

    private function renderPage()
    {
        $token   = Configuration::get('MYBIKE_CRON_TOKEN');
        $baseUrl = rtrim(Tools::getShopDomainSsl(true, true), '/') . '/modules/mybike_xml_generator';

        $this->context->smarty->assign([
            'api_key'         => Configuration::get('MYBIKE_API_KEY'),
            'cron_full_url'   => $baseUrl . '/cron_full.php?token=' . $token,
            'cron_stock_url'  => $baseUrl . '/cron_stock.php?token=' . $token,
            'full_xml'        => $this->fileInfo(MYBIKE_FULL_XML),
            'stock_xml'       => $this->fileInfo(MYBIKE_STOCK_XML),
            'last_full'       => $this->lastRunData('FULL'),
            'last_stock'      => $this->lastRunData('STOCK'),
            'action_url'      => $this->context->link->getAdminLink('AdminMyBikeXmlGenerator'),
        ]);

        $this->setTemplate('module:mybike_xml_generator/views/templates/admin/configure.tpl');
    }

    private function lastRunData($type)
    {
        return [
            'run'      => Configuration::get('MYBIKE_LAST_' . $type . '_RUN')      ?: '—',
            'count'    => Configuration::get('MYBIKE_LAST_' . $type . '_COUNT')    ?: '—',
            'duration' => Configuration::get('MYBIKE_LAST_' . $type . '_DURATION') ?: '—',
            'status'   => Configuration::get('MYBIKE_LAST_' . $type . '_STATUS')   ?: '—',
        ];
    }

    private function fileInfo($path)
    {
        if (!file_exists($path)) {
            return ['exists' => false, 'size' => '—', 'modified' => '—'];
        }
        $bytes = filesize($path);
        $size  = $bytes >= 1048576
            ? round($bytes / 1048576, 1) . ' MB'
            : round($bytes / 1024, 1) . ' KB';
        return [
            'exists'   => true,
            'size'     => $size,
            'modified' => date('Y-m-d H:i:s', filemtime($path)),
        ];
    }
}
