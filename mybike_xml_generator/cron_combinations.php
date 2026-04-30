<?php
/**
 * Generates products_combinations.xml from ps_mybike_product staging table.
 * Bikes/E-Bikes grouped by brand+model+color with <variants>.
 * Parts/Accessories as flat standard products.
 * Run after cron_api_sync.php (full mode).
 */
$psRoot = dirname(__FILE__, 3);
if (!file_exists($psRoot . '/config/config.inc.php')) {
    http_response_code(500);
    exit('PrestaShop root not found');
}
require_once $psRoot . '/config/config.inc.php';

require_once dirname(__FILE__) . '/config/config.php';
require_once dirname(__FILE__) . '/classes/MyBikeLogger.php';
require_once dirname(__FILE__) . '/classes/MyBikeCombinationsXml.php';

$token      = isset($_GET['token']) ? $_GET['token'] : '';
$savedToken = Configuration::get('MYBIKE_CRON_TOKEN');

if (!$savedToken || !hash_equals($savedToken, $token)) {
    http_response_code(403);
    exit('Forbidden');
}

set_time_limit(300);

$logger  = new MyBikeLogger(MYBIKE_XML_LOG);
$builder = new MyBikeCombinationsXml(MYBIKE_COMBINATIONS_XML, $logger);

try {
    $start    = microtime(true);
    $count    = $builder->build();
    $duration = (int)round(microtime(true) - $start);

    mybike_set_config('MYBIKE_LAST_COMB_RUN',      date('Y-m-d H:i:s'));
    mybike_set_config('MYBIKE_LAST_COMB_COUNT',    (string)$count);
    mybike_set_config('MYBIKE_LAST_COMB_DURATION', (string)$duration);
    mybike_set_config('MYBIKE_LAST_COMB_STATUS',   'ok');

    echo 'OK: ' . $count . ' products, ' . $duration . 's';
} catch (Exception $e) {
    $logger->error($e->getMessage());
    mybike_set_config('MYBIKE_LAST_COMB_STATUS', 'error: ' . $e->getMessage());
    http_response_code(500);
    echo 'ERROR: ' . $e->getMessage();
}

function mybike_set_config($name, $value)
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
        // non-fatal
    }
}
