<?php
/**
 * Generates products_full.xml AND products_combinations.xml in one pass.
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
require_once dirname(__FILE__) . '/classes/MyBikeProductsXml.php';

$token      = isset($_GET['token']) ? $_GET['token'] : '';
$savedToken = Configuration::get('MYBIKE_CRON_TOKEN');

if (!$savedToken || !hash_equals($savedToken, $token)) {
    http_response_code(403);
    exit('Forbidden');
}

if (!Module::isEnabled('mybike_xml_generator')) {
    http_response_code(503);
    exit('Module disabled');
}

set_time_limit(300);

$logger  = new MyBikeLogger(MYBIKE_XML_LOG);
$builder = new MyBikeProductsXml(MYBIKE_FULL_XML, MYBIKE_COMBINATIONS_XML, $logger);

try {
    $result   = $builder->build();
    $duration = $result['duration'];

    mybike_set_config('MYBIKE_LAST_FULL_RUN',      date('Y-m-d H:i:s'));
    mybike_set_config('MYBIKE_LAST_FULL_COUNT',    (string)$result['full']);
    mybike_set_config('MYBIKE_LAST_FULL_DURATION', (string)$duration);
    mybike_set_config('MYBIKE_LAST_FULL_STATUS',   'ok');

    mybike_set_config('MYBIKE_LAST_COMB_RUN',      date('Y-m-d H:i:s'));
    mybike_set_config('MYBIKE_LAST_COMB_COUNT',    (string)$result['combinations']);
    mybike_set_config('MYBIKE_LAST_COMB_DURATION', (string)$duration);
    mybike_set_config('MYBIKE_LAST_COMB_STATUS',   'ok');

    echo 'OK: full=' . $result['full'] . ' combinations=' . $result['combinations'] . ' ' . $duration . 's';
} catch (Exception $e) {
    $logger->error($e->getMessage());
    mybike_set_config('MYBIKE_LAST_FULL_STATUS', 'error: ' . $e->getMessage());
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
