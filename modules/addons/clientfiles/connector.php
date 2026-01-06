<?php
/**
 * elFinder Connector for WHMCS Client Files
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

$whmcsRoot = realpath(__DIR__ . '/../../../');

require_once $whmcsRoot . '/init.php';

use WHMCS\Database\Capsule;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$clientId = 0;
if (isset($_SESSION['uid']) && $_SESSION['uid']) {
    $clientId = (int)$_SESSION['uid'];
}

if (!$clientId && class_exists('WHMCS\Authentication\CurrentUser')) {
    $currentUser = new \WHMCS\Authentication\CurrentUser();
    $client = $currentUser->client();
    if ($client) {
        $clientId = $client->id;
    }
}

if (!$clientId) {
    header('Content-Type: application/json');
    exit(json_encode(['error' => 'Not authenticated']));
}

$access = Capsule::table('mod_clientfiles_access')
    ->where('client_id', $clientId)
    ->where('enabled', 1)
    ->first();

if (!$access) {
    header('Content-Type: application/json');
    exit(json_encode(['error' => 'Access denied']));
}

$settings = Capsule::table('tbladdonmodules')
    ->where('module', 'clientfiles')
    ->pluck('value', 'setting');

$storagePath = isset($settings['storage_path']) ? $settings['storage_path'] : 'client_files';
$maxFileSize = isset($settings['max_file_size']) ? (int)$settings['max_file_size'] : 50;

$clientPath = $whmcsRoot . '/' . $storagePath . '/' . $clientId;

if (!is_dir($clientPath)) {
    mkdir($clientPath, 0755, true);
}

require_once __DIR__ . '/elfinder/php/autoload.php';

$opts = [
    'roots' => [
        [
            'driver' => 'LocalFileSystem',
            'path' => $clientPath,
            'URL' => '',
            'alias' => 'My Files',
            'mimeDetect' => 'internal',
            'tmbPath' => '.tmb',
            'uploadMaxSize' => $maxFileSize . 'M',
        ],
    ],
];

$connector = new elFinderConnector(new elFinder($opts));
$connector->run();
