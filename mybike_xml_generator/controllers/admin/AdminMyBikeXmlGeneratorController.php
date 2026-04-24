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
require_once dirname(__FILE__) . '/../../classes/MyBikeCategoryManager.php';

class AdminMyBikeXmlGeneratorController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap  = true;
        $this->meta_title = 'MyBike XML Generator';
        parent::__construct();
    }

    // PS kviečia postProcess() prieš initContent() — čia tvarkome form actions
    public function postProcess()
    {
        if (Tools::isSubmit('save_config')) {
            $this->saveConfig();
        } elseif (Tools::isSubmit('regen_token')) {
            $this->regenToken();
        } elseif (Tools::isSubmit('run_full')) {
            $this->runFullSync();
        } elseif (Tools::isSubmit('run_stock')) {
            $this->runStockSync();
        } elseif (Tools::isSubmit('refresh_categories')) {
            $this->refreshCategories();
        } elseif (Tools::isSubmit('save_categories')) {
            $this->saveCategories();
        }
    }

    public function initContent()
    {
        parent::initContent();

        // Flash pranešimai po redirect (sync rezultatai)
        if (!empty($_SESSION['mybike_success'])) {
            $this->confirmations[] = $_SESSION['mybike_success'];
            unset($_SESSION['mybike_success']);
        }
        if (!empty($_SESSION['mybike_error'])) {
            $this->errors[] = $_SESSION['mybike_error'];
            unset($_SESSION['mybike_error']);
        }

        $token   = Configuration::get('MYBIKE_CRON_TOKEN');
        $baseUrl = rtrim(Tools::getShopDomainSsl(true, true), '/') . '/modules/mybike_xml_generator';

        $this->context->smarty->assign([
            'api_key'             => Configuration::get('MYBIKE_API_KEY'),
            'cron_full_url'       => $baseUrl . '/cron_full.php?token=' . $token,
            'cron_stock_url'      => $baseUrl . '/cron_stock.php?token=' . $token,
            'full_xml'            => $this->fileInfo(MYBIKE_FULL_XML),
            'stock_xml'           => $this->fileInfo(MYBIKE_STOCK_XML),
            'last_full'           => $this->lastRunData('FULL'),
            'last_stock'          => $this->lastRunData('STOCK'),
            'action_url'          => $this->context->link->getAdminLink('AdminMyBikeXmlGenerator'),
            'confirmations'       => $this->confirmations,
            'errors'              => $this->errors,
            'only_in_stock'          => (bool)Configuration::get('MYBIKE_ONLY_IN_STOCK'),
            'categories_grouped'     => MyBikeCategoryManager::getAllGrouped(),
            'categories_empty'       => MyBikeCategoryManager::isEmpty(),
            'categories_enabled_cnt' => count(MyBikeCategoryManager::getEnabledIds()),
            'categories_total_cnt'   => array_sum(array_map('count', MyBikeCategoryManager::getAllGrouped())),
        ]);

        // fetch() su absoliučiu keliu — nepriklausomai nuo admin katalogo pavadinimo
        $this->content = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'mybike_xml_generator/views/templates/admin/configure.tpl'
        );

        // parent::initContent() jau priskyrė tuščią $this->content Smarty — atnaujiname
        $this->context->smarty->assign('content', $this->content);
    }

    private function saveConfig()
    {
        $apiKey = trim(Tools::getValue('api_key'));
        if ($apiKey) {
            Configuration::updateValue('MYBIKE_API_KEY', pSQL($apiKey));
        }
        Configuration::updateValue('MYBIKE_ONLY_IN_STOCK', Tools::getValue('only_in_stock') ? '1' : '0');
        $this->confirmations[] = $this->l('Konfigūracija išsaugota.');
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
        $config = [
            'only_in_stock' => (bool)Configuration::get('MYBIKE_ONLY_IN_STOCK'),
            'enabled_ids'   => MyBikeCategoryManager::isEmpty() ? [] : MyBikeCategoryManager::getEnabledIds(),
        ];
        $logger = new MyBikeLogger(MYBIKE_FULL_LOG);
        $sync   = new MyBikeFullSync(new MyBikeApiClient($apiKey), $logger, $config);
        unset($config);

        try {
            $result = $sync->run();
            $this->setConfig('MYBIKE_LAST_FULL_RUN',      date('Y-m-d H:i:s'));
            $this->setConfig('MYBIKE_LAST_FULL_COUNT',    (string)$result['count']);
            $this->setConfig('MYBIKE_LAST_FULL_DURATION', (string)$result['duration']);
            $this->setConfig('MYBIKE_LAST_FULL_STATUS',   'ok');
            $_SESSION['mybike_success'] = 'Full sync atliktas: ' . $result['count'] . ' produktų, ' . $result['duration'] . 's';
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            $this->setConfig('MYBIKE_LAST_FULL_STATUS', 'error: ' . $e->getMessage());
            $_SESSION['mybike_error'] = $e->getMessage();
        }

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMyBikeXmlGenerator'));
    }

    private function runStockSync()
    {
        $apiKey = Configuration::get('MYBIKE_API_KEY');
        if (!$apiKey) {
            $this->errors[] = $this->l('API raktas nenurodytas.');
            return;
        }

        set_time_limit(300);
        $config = [
            'only_in_stock' => (bool)Configuration::get('MYBIKE_ONLY_IN_STOCK'),
            'enabled_ids'   => MyBikeCategoryManager::isEmpty() ? [] : MyBikeCategoryManager::getEnabledIds(),
        ];
        $logger = new MyBikeLogger(MYBIKE_STOCK_LOG);
        $sync   = new MyBikeStockSync(new MyBikeApiClient($apiKey), $logger, $config);
        unset($config);

        try {
            $result = $sync->run();
            $this->setConfig('MYBIKE_LAST_STOCK_RUN',      date('Y-m-d H:i:s'));
            $this->setConfig('MYBIKE_LAST_STOCK_COUNT',    (string)$result['count']);
            $this->setConfig('MYBIKE_LAST_STOCK_DURATION', (string)$result['duration']);
            $this->setConfig('MYBIKE_LAST_STOCK_STATUS',   'ok');
            $_SESSION['mybike_success'] = 'Stock sync atliktas: ' . $result['count'] . ' produktų, ' . $result['duration'] . 's';
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            $this->setConfig('MYBIKE_LAST_STOCK_STATUS', 'error: ' . $e->getMessage());
            $_SESSION['mybike_error'] = $e->getMessage();
        }

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMyBikeXmlGenerator'));
    }

    private function setConfig($name, $value)
    {
        try {
            $pdo   = new PDO('mysql:host=' . _DB_SERVER_ . ';dbname=' . _DB_NAME_ . ';charset=utf8mb4', _DB_USER_, _DB_PASSWD_);
            $table = _DB_PREFIX_ . 'configuration';
            $count = $pdo->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE `name` = ?');
            $count->execute([$name]);
            if ($count->fetchColumn()) {
                $pdo->prepare('UPDATE `' . $table . '` SET `value` = ?, `date_upd` = NOW() WHERE `name` = ?')
                    ->execute([$value, $name]);
            } else {
                $pdo->prepare('INSERT INTO `' . $table . '` (`name`,`value`,`date_add`,`date_upd`) VALUES (?,?,NOW(),NOW())')
                    ->execute([$name, $value]);
            }
        } catch (Exception $e) {
            // DB write failed — sync result was OK, only status not recorded
        }
    }

    private function refreshCategories()
    {
        $apiKey = Configuration::get('MYBIKE_API_KEY');
        if (!$apiKey) {
            $this->errors[] = $this->l('API raktas nenurodytas.');
            return;
        }

        try {
            $client   = new MyBikeApiClient($apiKey);
            $response = $client->getCategories();
            $cats     = $response['data'] ?? [];
            MyBikeCategoryManager::populate($cats);
            $this->confirmations[] = $this->l('Kategorijų sąrašas atnaujintas: ') . count($cats) . ' kategorijų.';
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
        }
    }

    private function saveCategories()
    {
        $raw = Tools::getValue('enabled_categories');
        $ids = is_array($raw) ? $raw : [];
        MyBikeCategoryManager::saveSelection($ids);
        $this->confirmations[] = $this->l('Kategorijų pasirinkimas išsaugotas.');
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
