<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

define('MYBIKE_MODULE_DIR', dirname(__DIR__));
define('MYBIKE_OUTPUT_DIR', MYBIKE_MODULE_DIR . '/xml');
define('MYBIKE_LOGS_DIR', MYBIKE_MODULE_DIR . '/logs');
define('MYBIKE_FULL_XML', MYBIKE_OUTPUT_DIR . '/products_full.xml');
define('MYBIKE_STOCK_XML', MYBIKE_OUTPUT_DIR . '/products_stock.xml');
define('MYBIKE_FULL_LOG', MYBIKE_LOGS_DIR . '/full_sync.log');
define('MYBIKE_STOCK_LOG', MYBIKE_LOGS_DIR . '/stock_sync.log');

define('MYBIKE_API_BASE_URL', 'http://mybike.lt');
define('MYBIKE_API_LIMIT', 100);
define('MYBIKE_API_TIMEOUT', 30);
define('MYBIKE_API_RETRY', 3);
define('MYBIKE_LOG_MAX_SIZE', 1048576); // 1 MB
