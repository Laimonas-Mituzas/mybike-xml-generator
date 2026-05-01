<?php
/**
 * Staging DB → PrestaShop products import
 * Reads ps_mybike_product, creates/updates PS Product + Combination + StockAvailable
 */
$psRoot = dirname(__FILE__, 3);
if (!file_exists($psRoot . '/config/config.inc.php')) {
    http_response_code(500);
    exit('PrestaShop root not found');
}
require_once $psRoot . '/config/config.inc.php';

require_once dirname(__FILE__) . '/config/config.php';
require_once dirname(__FILE__) . '/classes/MyBikeLogger.php';
require_once dirname(__FILE__) . '/classes/MyBikePriceCalc.php';
require_once dirname(__FILE__) . '/classes/MyBikeManufacturerMap.php';
require_once dirname(__FILE__) . '/classes/MyBikeAttributeMap.php';
require_once dirname(__FILE__) . '/classes/MyBikePsImport.php';

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

set_time_limit(1800);

$logger = new MyBikeLogger(MYBIKE_IMPORT_LOG);
$import = new MyBikePsImport($logger);

try {
    $result = $import->run();

    mybike_set_config('MYBIKE_LAST_IMPORT_RUN',      date('Y-m-d H:i:s'));
    mybike_set_config('MYBIKE_LAST_IMPORT_IMPORTED',  (string)$result['imported']);
    mybike_set_config('MYBIKE_LAST_IMPORT_UPDATED',   (string)$result['updated']);
    mybike_set_config('MYBIKE_LAST_IMPORT_SKIPPED',   (string)$result['skipped']);
    mybike_set_config('MYBIKE_LAST_IMPORT_DURATION',  (string)$result['duration']);
    mybike_set_config('MYBIKE_LAST_IMPORT_STATUS',    'ok');

    echo 'OK: imported=' . $result['imported']
        . ' updated=' . $result['updated']
        . ' skipped=' . $result['skipped']
        . ' ' . $result['duration'] . 's';

    if (!empty($result['warnings'])) {
        echo "\nWARNINGS:\n" . implode("\n", $result['warnings']);
    }

    // Regenerate combinations XML — ps_id_product now filled in staging
    $xmlLogger  = new MyBikeLogger(MYBIKE_XML_LOG);
    $combXml    = new MyBikeProductsXml(MYBIKE_FULL_XML, MYBIKE_COMBINATIONS_XML, $xmlLogger);
    $combResult = $combXml->buildCombinationsOnly();

    mybike_set_config('MYBIKE_LAST_COMB_RUN',      date('Y-m-d H:i:s'));
    mybike_set_config('MYBIKE_LAST_COMB_COUNT',    (string)$combResult['combinations']);
    mybike_set_config('MYBIKE_LAST_COMB_DURATION', (string)$combResult['duration']);
    mybike_set_config('MYBIKE_LAST_COMB_STATUS',   'ok');

    echo "\nCombinations XML: " . $combResult['combinations'] . ' rows, ' . $combResult['duration'] . 's';

} catch (Exception $e) {
    $logger->error($e->getMessage());
    mybike_set_config('MYBIKE_LAST_IMPORT_STATUS', 'error: ' . $e->getMessage());
    http_response_code(500);
    echo 'ERROR: ' . $e->getMessage();
}

function mybike_set_config($name, $value)
{
    try {
        $pdo   = new PDO(
            'mysql:host=' . _DB_SERVER_ . ';dbname=' . _DB_NAME_ . ';charset=utf8mb4',
            _DB_USER_,
            _DB_PASSWD_
        );
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
