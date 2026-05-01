<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../config/config.php';
require_once dirname(__FILE__) . '/../../classes/MyBikeLogger.php';
require_once dirname(__FILE__) . '/../../classes/MyBikeApiClient.php';
require_once dirname(__FILE__) . '/../../classes/MyBikeCategoryManager.php';
require_once dirname(__FILE__) . '/../../classes/MyBikeProductsXml.php';
require_once dirname(__FILE__) . '/../../classes/MyBikeStockDbXml.php';
require_once dirname(__FILE__) . '/../../classes/MyBikePriceCalc.php';
require_once dirname(__FILE__) . '/../../classes/MyBikeManufacturerMap.php';
require_once dirname(__FILE__) . '/../../classes/MyBikeAttributeMap.php';
require_once dirname(__FILE__) . '/../../classes/MyBikeApiSync.php';
require_once dirname(__FILE__) . '/../../classes/MyBikePsImport.php';

class AdminMyBikeXmlGeneratorController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap  = true;
        $this->meta_title = 'MyBike XML Generator';
        parent::__construct();
    }

    public function postProcess()
    {
        if (Tools::isSubmit('save_config')) {
            $this->saveConfig();
        } elseif (Tools::isSubmit('regen_token')) {
            $this->regenToken();
        } elseif (Tools::isSubmit('refresh_categories')) {
            $this->refreshCategories();
        } elseif (Tools::isSubmit('save_categories')) {
            $this->saveCategories();
        } elseif (Tools::isSubmit('save_import_config')) {
            $this->saveImportConfig();
        } elseif (Tools::isSubmit('refresh_category_map')) {
            $this->refreshCategoryMap();
        } elseif (Tools::isSubmit('save_category_map')) {
            $this->saveCategoryMap();
        } elseif (Tools::isSubmit('run_api_sync_full')) {
            $this->runApiSyncFull();
        } elseif (Tools::isSubmit('run_api_sync_stock')) {
            $this->runApiSyncStock();
        } elseif (Tools::isSubmit('run_ps_import')) {
            $this->runPsImport();
        } elseif (Tools::isSubmit('clear_staging')) {
            $this->clearStaging();
        } elseif (Tools::isSubmit('run_ps_import_test')) {
            $this->runPsImportTest();
        } elseif (Tools::isSubmit('run_full')) {
            $this->runFullXml();
        } elseif (Tools::isSubmit('run_stock')) {
            $this->runStockXml();
        } elseif (Tools::isSubmit('clear_log')) {
            $this->clearLog();
        }
    }

    public function initContent()
    {
        parent::initContent();

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
            'api_key'                => Configuration::get('MYBIKE_API_KEY'),
            'cron_full_url'          => $baseUrl . '/cron_full.php?token=' . $token,
            'cron_stock_url'         => $baseUrl . '/cron_stock.php?token=' . $token,
            'full_xml'               => $this->fileInfo(MYBIKE_FULL_XML),
            'stock_xml'              => $this->fileInfo(MYBIKE_STOCK_XML),
            'combinations_xml'       => $this->fileInfo(MYBIKE_COMBINATIONS_XML),
            'last_full'              => $this->lastRunData('FULL'),
            'last_stock'             => $this->lastRunData('STOCK'),
            'last_combinations'      => $this->lastRunData('COMB'),
            'action_url'             => $this->context->link->getAdminLink('AdminMyBikeXmlGenerator'),
            'confirmations'          => $this->confirmations,
            'errors'                 => $this->errors,
            'only_in_stock'          => (bool)Configuration::get('MYBIKE_ONLY_IN_STOCK'),
            'categories_grouped'     => MyBikeCategoryManager::getAllGrouped(),
            'categories_empty'       => MyBikeCategoryManager::isEmpty(),
            'categories_enabled_cnt' => count(MyBikeCategoryManager::getEnabledIds()),
            'categories_total_cnt'   => array_sum(array_map('count', MyBikeCategoryManager::getAllGrouped())),
            // v2 — import config
            'import_price_key'       => Configuration::get('MYBIKE_PRICE_KEY') ?: 'price',
            'import_coefficient'     => Configuration::get('MYBIKE_PRICE_COEFFICIENT') ?: '1.00',
            'import_with_vat'        => (bool)(int)Configuration::get('MYBIKE_PRICE_WITH_VAT'),
            'import_tax_rules_id'    => (int)Configuration::get('MYBIKE_IMPORT_TAX_RULES_ID'),
            'tax_rules_groups'       => $this->getTaxRulesGroups(),
            // v2 — category map
            'category_map_grouped'   => $this->getCategoryMapGrouped(),
            'category_map_empty'     => $this->isCategoryMapEmpty(),
            'ps_categories'          => $this->getPsCategories(),
            // v2 — cron URLs
            'cron_api_sync_full_url'  => $baseUrl . '/cron_api_sync.php?token=' . $token . '&mode=full',
            'cron_api_sync_stock_url' => $baseUrl . '/cron_api_sync.php?token=' . $token . '&mode=stock',
            'cron_ps_import_url'      => $baseUrl . '/cron_ps_import.php?token=' . $token,
            // v2 — last run data
            'last_api_sync'          => $this->lastApiSyncData(),
            'last_import'            => $this->lastImportData(),
            'staging_count'          => $this->getStagingCount(),
            'last_test_import'       => $this->lastTestImportData(),
            // log tab
            'log_api_sync'           => $this->getLogContent(MYBIKE_API_SYNC_LOG),
            'log_ps_import'          => $this->getLogContent(MYBIKE_IMPORT_LOG),
            'log_xml'                => $this->getLogContent(MYBIKE_XML_LOG),
        ]);

        $this->content = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'mybike_xml_generator/views/templates/admin/configure.tpl'
        );
        $this->context->smarty->assign('content', $this->content);
    }

    // -------------------------------------------------------------------
    // v1 actions (unchanged)
    // -------------------------------------------------------------------

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

    private function runFullXml()
    {
        set_time_limit(300);
        $logger  = new MyBikeLogger(MYBIKE_XML_LOG);
        $builder = new MyBikeProductsXml(MYBIKE_FULL_XML, MYBIKE_COMBINATIONS_XML, $logger);
        try {
            $result = $builder->build();
            $dur    = $result['duration'];

            $this->setConfig('MYBIKE_LAST_FULL_RUN',      date('Y-m-d H:i:s'));
            $this->setConfig('MYBIKE_LAST_FULL_COUNT',    (string)$result['full']);
            $this->setConfig('MYBIKE_LAST_FULL_DURATION', (string)$dur);
            $this->setConfig('MYBIKE_LAST_FULL_STATUS',   'ok');

            $this->setConfig('MYBIKE_LAST_COMB_RUN',      date('Y-m-d H:i:s'));
            $this->setConfig('MYBIKE_LAST_COMB_COUNT',    (string)$result['combinations']);
            $this->setConfig('MYBIKE_LAST_COMB_DURATION', (string)$dur);
            $this->setConfig('MYBIKE_LAST_COMB_STATUS',   'ok');

            $_SESSION['mybike_success'] = 'XML sugeneruoti: full=' . $result['full']
                . ' combinations=' . $result['combinations'] . ', ' . $dur . 's';
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            $this->setConfig('MYBIKE_LAST_FULL_STATUS', 'error: ' . $e->getMessage());
            $this->setConfig('MYBIKE_LAST_COMB_STATUS', 'error: ' . $e->getMessage());
            $_SESSION['mybike_error'] = $e->getMessage();
        }
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMyBikeXmlGenerator'));
    }

    private function runStockXml()
    {
        set_time_limit(120);
        $logger  = new MyBikeLogger(MYBIKE_XML_LOG);
        $builder = new MyBikeStockDbXml(MYBIKE_STOCK_XML, $logger);
        try {
            $start = microtime(true);
            $count = $builder->build();
            $dur   = (int)round(microtime(true) - $start);
            $this->setConfig('MYBIKE_LAST_STOCK_RUN',      date('Y-m-d H:i:s'));
            $this->setConfig('MYBIKE_LAST_STOCK_COUNT',    (string)$count);
            $this->setConfig('MYBIKE_LAST_STOCK_DURATION', (string)$dur);
            $this->setConfig('MYBIKE_LAST_STOCK_STATUS',   'ok');
            $_SESSION['mybike_success'] = 'products_stock.xml sugeneruotas: ' . $count . ' produktų, ' . $dur . 's';
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            $this->setConfig('MYBIKE_LAST_STOCK_STATUS', 'error: ' . $e->getMessage());
            $_SESSION['mybike_error'] = $e->getMessage();
        }
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMyBikeXmlGenerator'));
    }

    private function refreshCategories()
    {
        $apiKey = Configuration::get('MYBIKE_API_KEY');
        if (!$apiKey) { $this->errors[] = $this->l('API raktas nenurodytas.'); return; }

        try {
            $client    = new MyBikeApiClient($apiKey);
            $response  = $client->getCategories();
            $cats      = $response['data'] ?? [];
            $firstTime = MyBikeCategoryManager::isEmpty();
            MyBikeCategoryManager::populate($cats);
            if ($firstTime) {
                MyBikeCategoryManager::enableAll();
            }
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

    // -------------------------------------------------------------------
    // v2 actions
    // -------------------------------------------------------------------

    private function saveImportConfig()
    {
        $priceKey   = Tools::getValue('import_price_key');
        $priceKey   = in_array($priceKey, ['price', 'base_price'], true) ? $priceKey : 'price';
        $coeff      = (float)str_replace(',', '.', Tools::getValue('import_coefficient'));
        $coeff      = max(0.01, $coeff);
        $withVat    = Tools::getValue('import_with_vat') ? '1' : '0';
        $taxRulesId = (int)Tools::getValue('import_tax_rules_id');

        Configuration::updateValue('MYBIKE_PRICE_KEY',            $priceKey);
        Configuration::updateValue('MYBIKE_PRICE_COEFFICIENT',    number_format($coeff, 4, '.', ''));
        Configuration::updateValue('MYBIKE_PRICE_WITH_VAT',       $withVat);
        Configuration::updateValue('MYBIKE_IMPORT_TAX_RULES_ID',  (string)$taxRulesId);

        $this->confirmations[] = $this->l('Importo konfigūracija išsaugota.');
    }

    private function refreshCategoryMap()
    {
        // Populate ps_mybike_category_map from ps_mybike_xml_category (preserves existing ps_id_category)
        Db::getInstance()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'mybike_category_map`
               (`mybike_category_id`, `mybike_section`, `mybike_category`, `mybike_product_count`, `date_upd`)
             SELECT `id_category`, `section`, `title`, `product_count`, NOW()
             FROM `' . _DB_PREFIX_ . 'mybike_xml_category`
             ON DUPLICATE KEY UPDATE
               `mybike_section`       = VALUES(`mybike_section`),
               `mybike_category`      = VALUES(`mybike_category`),
               `mybike_product_count` = VALUES(`mybike_product_count`),
               `date_upd`             = NOW()'
        );
        $this->confirmations[] = $this->l('Kategorijų susiejimo sąrašas atnaujintas.');
    }

    private function saveCategoryMap()
    {
        $mappings = Tools::getValue('category_map');
        if (!is_array($mappings)) {
            $this->confirmations[] = $this->l('Nieko neišsaugota.');
            return;
        }

        $saved = 0;
        foreach ($mappings as $mybikeCategoryId => $psCategoryId) {
            $mybikeCategoryId = (int)$mybikeCategoryId;
            $psId             = ($psCategoryId !== '' && $psCategoryId !== '0') ? (int)$psCategoryId : 'NULL';
            Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'mybike_category_map`
                 SET `ps_id_category` = ' . $psId . ', `date_upd` = NOW()
                 WHERE `mybike_category_id` = ' . $mybikeCategoryId
            );
            $saved++;
        }
        $this->confirmations[] = $this->l('Susiejimas išsaugotas: ') . $saved . ' kategorijų.';
    }

    private function runApiSyncFull()
    {
        $apiKey = Configuration::get('MYBIKE_API_KEY');
        if (!$apiKey) { $this->errors[] = $this->l('API raktas nenurodytas.'); return; }

        set_time_limit(900);
        $config = [
            'only_in_stock' => (bool)Configuration::get('MYBIKE_ONLY_IN_STOCK'),
            'enabled_ids'   => MyBikeCategoryManager::isEmpty() ? [] : MyBikeCategoryManager::getEnabledIds(),
        ];
        $logger = new MyBikeLogger(MYBIKE_API_SYNC_LOG);
        $api    = new MyBikeApiClient($apiKey);

        try {
            $pdo  = new PDO(
                'mysql:host=' . _DB_SERVER_ . ';dbname=' . _DB_NAME_ . ';charset=utf8mb4',
                _DB_USER_, _DB_PASSWD_,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $sync   = new MyBikeApiSync($api, $logger, $pdo, $config);
            unset($config);
            $result = $sync->runFull();

            $this->setConfig('MYBIKE_LAST_API_SYNC_RUN',      date('Y-m-d H:i:s'));
            $this->setConfig('MYBIKE_LAST_API_SYNC_COUNT',    (string)$result['count']);
            $this->setConfig('MYBIKE_LAST_API_SYNC_DURATION', (string)$result['duration']);
            $this->setConfig('MYBIKE_LAST_API_SYNC_STATUS',   'ok:full');
            $_SESSION['mybike_success'] = 'API sync (full) atliktas: ' . $result['count'] . ' produktų, '
                . $result['details_fetched'] . ' detail calls, ' . $result['duration'] . 's';
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            $this->setConfig('MYBIKE_LAST_API_SYNC_STATUS', 'error: ' . $e->getMessage());
            $_SESSION['mybike_error'] = $e->getMessage();
        }
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMyBikeXmlGenerator'));
    }

    private function runApiSyncStock()
    {
        $apiKey = Configuration::get('MYBIKE_API_KEY');
        if (!$apiKey) { $this->errors[] = $this->l('API raktas nenurodytas.'); return; }

        set_time_limit(300);
        $config = [
            'only_in_stock' => (bool)Configuration::get('MYBIKE_ONLY_IN_STOCK'),
            'enabled_ids'   => MyBikeCategoryManager::isEmpty() ? [] : MyBikeCategoryManager::getEnabledIds(),
        ];
        $logger = new MyBikeLogger(MYBIKE_API_SYNC_LOG);
        $api    = new MyBikeApiClient($apiKey);

        try {
            $pdo  = new PDO(
                'mysql:host=' . _DB_SERVER_ . ';dbname=' . _DB_NAME_ . ';charset=utf8mb4',
                _DB_USER_, _DB_PASSWD_,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $sync   = new MyBikeApiSync($api, $logger, $pdo, $config);
            unset($config);
            $result = $sync->runStockOnly();

            $this->setConfig('MYBIKE_LAST_API_SYNC_RUN',      date('Y-m-d H:i:s'));
            $this->setConfig('MYBIKE_LAST_API_SYNC_COUNT',    (string)$result['count']);
            $this->setConfig('MYBIKE_LAST_API_SYNC_DURATION', (string)$result['duration']);
            $this->setConfig('MYBIKE_LAST_API_SYNC_STATUS',   'ok:stock');
            $_SESSION['mybike_success'] = 'API sync (stock) atliktas: ' . $result['count'] . ' produktų, ' . $result['duration'] . 's';
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            $this->setConfig('MYBIKE_LAST_API_SYNC_STATUS', 'error: ' . $e->getMessage());
            $_SESSION['mybike_error'] = $e->getMessage();
        }
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMyBikeXmlGenerator'));
    }

    private function runPsImport()
    {
        set_time_limit(1800);
        $logger = new MyBikeLogger(MYBIKE_IMPORT_LOG);
        $import = new MyBikePsImport($logger);

        try {
            $result = $import->run();
            $this->setConfig('MYBIKE_LAST_IMPORT_RUN',      date('Y-m-d H:i:s'));
            $this->setConfig('MYBIKE_LAST_IMPORT_IMPORTED',  (string)$result['imported']);
            $this->setConfig('MYBIKE_LAST_IMPORT_UPDATED',   (string)$result['updated']);
            $this->setConfig('MYBIKE_LAST_IMPORT_SKIPPED',   (string)$result['skipped']);
            $this->setConfig('MYBIKE_LAST_IMPORT_DURATION',  (string)$result['duration']);
            $this->setConfig('MYBIKE_LAST_IMPORT_STATUS',    'ok');

            $msg = 'PS importas atliktas: nauji=' . $result['imported']
                . ' atnaujinta=' . $result['updated']
                . ' praleista=' . $result['skipped']
                . ' nuotraukos=' . ($result['images'] ?? 0)
                . ' ' . $result['duration'] . 's';
            if (!empty($result['warnings'])) {
                $msg .= ' | Įspėjimai: ' . implode('; ', $result['warnings']);
            }
            $_SESSION['mybike_success'] = $msg;
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            $this->setConfig('MYBIKE_LAST_IMPORT_STATUS', 'error: ' . $e->getMessage());
            $_SESSION['mybike_error'] = $e->getMessage();
        }
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMyBikeXmlGenerator'));
    }

    private function clearStaging()
    {
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'mybike_product`');
        $this->confirmations[] = $this->l('Staging lentelė išvalyta.');
    }

    private function runPsImportTest()
    {
        set_time_limit(120);
        $mybike_id = (int)Tools::getValue('test_mybike_id');
        $logger    = new MyBikeLogger(MYBIKE_IMPORT_LOG);
        $import    = new MyBikePsImport($logger);

        try {
            $result = $import->runSingle($mybike_id);

            $detail = 'mybike_id=' . $result['mybike_id']
                . ' section=' . $result['section']
                . ' "' . $result['name'] . '"'
                . ' group=' . ($result['group_size'] ?? 1)
                . ' ps_id=' . $result['ps_id_product']
                . ' imported=' . $result['imported']
                . ' updated=' . $result['updated']
                . ' skipped=' . $result['skipped']
                . ' ' . $result['duration'] . 's';

            $this->setConfig('MYBIKE_LAST_TEST_RUN',    date('Y-m-d H:i:s'));
            $this->setConfig('MYBIKE_LAST_TEST_STATUS', 'ok');
            $this->setConfig('MYBIKE_LAST_TEST_DETAIL', $detail);

            $outcome = $result['imported'] ? 'naujas' : ($result['updated'] ? 'atnaujintas' : 'praleistas');
            $_SESSION['mybike_success'] = 'Test importas: mybike_id=' . $result['mybike_id']
                . ' → ps_id_product=' . $result['ps_id_product']
                . ' (' . $outcome . ') ' . $result['duration'] . 's';
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            $this->setConfig('MYBIKE_LAST_TEST_RUN',    date('Y-m-d H:i:s'));
            $this->setConfig('MYBIKE_LAST_TEST_STATUS', 'error: ' . $e->getMessage());
            $this->setConfig('MYBIKE_LAST_TEST_DETAIL', '');
            $_SESSION['mybike_error'] = 'Test importas nepavyko: ' . $e->getMessage();
        }
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMyBikeXmlGenerator'));
    }

    private function clearLog()
    {
        $map = [
            'api_sync'  => MYBIKE_API_SYNC_LOG,
            'ps_import' => MYBIKE_IMPORT_LOG,
            'xml'       => MYBIKE_XML_LOG,
        ];
        $which = Tools::getValue('clear_log_which');
        if (!isset($map[$which])) {
            $this->errors[] = $this->l('Nežinomas log failas.');
            return;
        }
        file_put_contents($map[$which], '');
        $this->confirmations[] = $this->l('Log failas išvalytas.');
    }

    // -------------------------------------------------------------------
    // Data helpers
    // -------------------------------------------------------------------

    private function getTaxRulesGroups(): array
    {
        return Db::getInstance()->executeS(
            'SELECT `id_tax_rules_group`, `name`
             FROM `' . _DB_PREFIX_ . 'tax_rules_group`
             WHERE `active` = 1
             ORDER BY `name`'
        );
    }

    private function getCategoryMapGrouped(): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `mybike_category_id`, `mybike_section`, `mybike_category`,
                    `mybike_product_count`, `ps_id_category`
             FROM `' . _DB_PREFIX_ . 'mybike_category_map`
             ORDER BY `mybike_section`, `mybike_category`'
        );
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['mybike_section']][] = $row;
        }
        return $grouped;
    }

    private function isCategoryMapEmpty(): bool
    {
        return !(bool)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'mybike_category_map`'
        );
    }

    private function getPsCategories(): array
    {
        $idLang = (int)Configuration::get('PS_LANG_DEFAULT');
        return Db::getInstance()->executeS(
            'SELECT c.`id_category`, cl.`name`
             FROM `' . _DB_PREFIX_ . 'category` c
             JOIN `' . _DB_PREFIX_ . 'category_lang` cl
               ON c.`id_category` = cl.`id_category` AND cl.`id_lang` = ' . $idLang . '
             WHERE c.`active` = 1 AND c.`id_category` > 2
             ORDER BY cl.`name`'
        );
    }

    private function getStagingCount(): int
    {
        return (int)Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'mybike_product`'
        );
    }

    private function lastApiSyncData(): array
    {
        return [
            'run'      => Configuration::get('MYBIKE_LAST_API_SYNC_RUN')      ?: '—',
            'count'    => Configuration::get('MYBIKE_LAST_API_SYNC_COUNT')    ?: '—',
            'duration' => Configuration::get('MYBIKE_LAST_API_SYNC_DURATION') ?: '—',
            'status'   => Configuration::get('MYBIKE_LAST_API_SYNC_STATUS')   ?: '—',
        ];
    }

    private function lastImportData(): array
    {
        return [
            'run'      => Configuration::get('MYBIKE_LAST_IMPORT_RUN')      ?: '—',
            'imported' => Configuration::get('MYBIKE_LAST_IMPORT_IMPORTED') ?: '—',
            'updated'  => Configuration::get('MYBIKE_LAST_IMPORT_UPDATED')  ?: '—',
            'skipped'  => Configuration::get('MYBIKE_LAST_IMPORT_SKIPPED')  ?: '—',
            'duration' => Configuration::get('MYBIKE_LAST_IMPORT_DURATION') ?: '—',
            'status'   => Configuration::get('MYBIKE_LAST_IMPORT_STATUS')   ?: '—',
        ];
    }

    private function lastTestImportData(): array
    {
        return [
            'run'    => Configuration::get('MYBIKE_LAST_TEST_RUN')    ?: '',
            'status' => Configuration::get('MYBIKE_LAST_TEST_STATUS') ?: '',
            'detail' => Configuration::get('MYBIKE_LAST_TEST_DETAIL') ?: '',
        ];
    }

    private function lastRunData($type): array
    {
        return [
            'run'      => Configuration::get('MYBIKE_LAST_' . $type . '_RUN')      ?: '—',
            'count'    => Configuration::get('MYBIKE_LAST_' . $type . '_COUNT')    ?: '—',
            'duration' => Configuration::get('MYBIKE_LAST_' . $type . '_DURATION') ?: '—',
            'status'   => Configuration::get('MYBIKE_LAST_' . $type . '_STATUS')   ?: '—',
        ];
    }

    private function setConfig($name, $value)
    {
        try {
            $pdo   = new PDO('mysql:host=' . _DB_SERVER_ . ';dbname=' . _DB_NAME_ . ';charset=utf8mb4', _DB_USER_, _DB_PASSWD_);
            $table = _DB_PREFIX_ . 'configuration';
            $count = $pdo->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE `name` = ?');
            $count->execute([$name]);
            if ($count->fetchColumn()) {
                $pdo->prepare('UPDATE `' . $table . '` SET `value` = ?, `date_upd` = NOW() WHERE `name` = ?')->execute([$value, $name]);
            } else {
                $pdo->prepare('INSERT INTO `' . $table . '` (`name`,`value`,`date_add`,`date_upd`) VALUES (?,?,NOW(),NOW())')->execute([$name, $value]);
            }
        } catch (Exception $e) {
            // non-fatal
        }
    }

    private function getLogContent(string $path, int $maxLines = 500): array
    {
        if (!file_exists($path)) {
            return ['exists' => false, 'content' => '', 'size' => '—', 'modified' => '—', 'total_lines' => 0, 'truncated' => false];
        }
        $bytes    = filesize($path);
        $size     = $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024, 1) . ' KB';
        $modified = date('Y-m-d H:i:s', filemtime($path));
        $all      = file($path, FILE_IGNORE_NEW_LINES);
        if ($all === false) {
            return ['exists' => true, 'content' => 'Nepavyko perskaityti.', 'size' => $size, 'modified' => $modified, 'total_lines' => 0, 'truncated' => false];
        }
        $total     = count($all);
        $truncated = $total > $maxLines;
        $content   = implode("\n", array_slice($all, -$maxLines));
        return ['exists' => true, 'content' => $content, 'size' => $size, 'modified' => $modified, 'total_lines' => $total, 'truncated' => $truncated];
    }

    private function fileInfo($path): array
    {
        if (!file_exists($path)) {
            return ['exists' => false, 'size' => '—', 'modified' => '—'];
        }
        $bytes = filesize($path);
        $size  = $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024, 1) . ' KB';
        return ['exists' => true, 'size' => $size, 'modified' => date('Y-m-d H:i:s', filemtime($path))];
    }
}
