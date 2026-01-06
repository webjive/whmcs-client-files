<?php
/**
 * elFinder Connector for WHMCS Admin - Client Files
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

$whmcsRoot = realpath(__DIR__ . '/../../../');

require_once $whmcsRoot . '/init.php';

use WHMCS\Database\Capsule;

// Check admin authentication
if (!isset($_SESSION['adminid']) || !$_SESSION['adminid']) {
    header('Content-Type: application/json');
    exit(json_encode(['error' => 'Admin not authenticated']));
}

$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

if (!$clientId) {
    header('Content-Type: application/json');
    exit(json_encode(['error' => 'No client specified']));
}

$access = Capsule::table('mod_clientfiles_access')
    ->where('client_id', $clientId)
    ->where('enabled', 1)
    ->first();

if (!$access) {
    header('Content-Type: application/json');
    exit(json_encode(['error' => 'Client has no file area']));
}

$settings = Capsule::table('tbladdonmodules')
    ->where('module', 'clientfiles')
    ->pluck('value', 'setting');

$storagePath = isset($settings['storage_path']) ? $settings['storage_path'] : 'client_files';
$maxFileSize = isset($settings['max_file_size']) ? (int)$settings['max_file_size'] : 50;
$defaultMaxStorage = isset($settings['default_max_storage']) ? (int)$settings['default_max_storage'] : 500;

// Determine effective storage limit for this client
$maxStorageMB = $access->max_storage_mb !== null ? $access->max_storage_mb : $defaultMaxStorage;

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
            'alias' => 'Client Files',
            'mimeDetect' => 'internal',
            'tmbPath' => '.tmb',
            'uploadMaxSize' => $maxFileSize . 'M',
            'attributes' => [
                [
                    'pattern' => '/\.tmb$/',
                    'hidden' => true,
                ],
            ],
        ],
    ],
];

$connector = new elFinderConnector(new elFinder($opts));
$connector->run();
