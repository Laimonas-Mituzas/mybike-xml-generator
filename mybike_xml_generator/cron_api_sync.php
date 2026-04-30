<?php
/**
 * API → DB staging sync
 * ?mode=full  (default) — full sync with lazy detail fetch
 * ?mode=stock            — stock-only sync (price + availability, no detail fetch)
 */
$psRoot = dirname(__FILE__, 3);
if (!file_exists($psRoot . '/config/config.inc.php')) {
    http_response_code(500);
    exit('PrestaShop root not found');
}
require_once $psRoot . '/config/config.inc.php';

require_once dirname(__FILE__) . '/config/config.php';
require_once dirname(__FILE__) . '/classes/MyBikeLogger.php';
require_once dirname(__FILE__) . '/classes/MyBikeApiClient.php';
require_once dirname(__FILE__) . '/classes/MyBikeCategoryManager.php';
require_once dirname(__FILE__) . '/classes/MyBikeApiSync.php';

$token      = isset($_GET['token']) ? $_GET['token'] : '';
$savedToken = Configuration::get('MYBIKE_CRON_TOKEN');

if (!$savedToken || !hash_equals($savedToken, $token)) {
    http_response_code(403);
    exit('Forbidden');
}

$mode = (isset($_GET['mode']) && $_GET['mode'] === 'stock') ? 'stock' : 'full';

set_time_limit(900);

// Read config before long operation to avoid PS DB issues
$apiKey = Configuration::get('MYBIKE_API_KEY');
$config = [
    'only_in_stock' => (bool)Configuration::get('MYBIKE_ONLY_IN_STOCK'),
    'enabled_ids'   => MyBikeCategoryManager::isEmpty() ? [] : MyBikeCategoryManager::getEnabledIds(),
];

$logger = new MyBikeLogger(MYBIKE_API_SYNC_LOG);
$api    = new MyBikeApiClient($apiKey);

try {
    $pdo = new PDO(
        'mysql:host=' . _DB_SERVER_ . ';dbname=' . _DB_NAME_ . ';charset=utf8mb4',
        _DB_USER_,
        _DB_PASSWD_,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    $logger->error('DB connect failed: ' . $e->getMessage());
    http_response_code(500);
    exit('DB error');
}

$sync = new MyBikeApiSync($api, $logger, $pdo, $config);
unset($config);

$statusKey   = 'MYBIKE_LAST_API_SYNC_STATUS';
$runKey      = 'MYBIKE_LAST_API_SYNC_RUN';
$countKey    = 'MYBIKE_LAST_API_SYNC_COUNT';
$durationKey = 'MYBIKE_LAST_API_SYNC_DURATION';

try {
    if ($mode === 'stock') {
        $result = $sync->runStockOnly();
    } else {
        $result = $sync->runFull();
    }

    mybike_set_config($runKey,      date('Y-m-d H:i:s'));
    mybike_set_config($countKey,    (string)$result['count']);
    mybike_set_config($durationKey, (string)$result['duration']);
    mybike_set_config($statusKey,   'ok:' . $mode);
    echo 'OK [' . $mode . ']: ' . $result['count'] . ' products, ' . $result['duration'] . 's';
} catch (Exception $e) {
    $logger->error($e->getMessage());
    mybike_set_config($statusKey, 'error: ' . $e->getMessage());
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
        // non-fatal — sync result was OK, only status not recorded
    }
}
