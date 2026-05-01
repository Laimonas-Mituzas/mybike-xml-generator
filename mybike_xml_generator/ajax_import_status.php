<?php
/**
 * Lightweight AJAX endpoint — returns import progress JSON.
 * Does NOT start a session; safe to call while admin import is running.
 */
$psRoot = dirname(__FILE__, 3);
if (!file_exists($psRoot . '/config/config.inc.php')) {
    http_response_code(500);
    header('Content-Type: application/json');
    exit(json_encode(['error' => 'PS root not found']));
}
require_once $psRoot . '/config/config.inc.php';
require_once dirname(__FILE__) . '/config/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$token      = isset($_GET['token']) ? (string)$_GET['token'] : '';
$savedToken = (string)Configuration::get('MYBIKE_CRON_TOKEN');

if (!$savedToken || !hash_equals($savedToken, $token)) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

if (!file_exists(MYBIKE_IMPORT_PROGRESS)) {
    exit(json_encode(['status' => 'idle', 'step' => 'Nepaleista', 'current' => 0, 'total' => 0, 'percent' => 0]));
}

$content = file_get_contents(MYBIKE_IMPORT_PROGRESS);
echo $content !== false ? $content : json_encode(['status' => 'idle', 'step' => '', 'current' => 0, 'total' => 0, 'percent' => 0]);
