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
$defaultMaxStorage = isset($settings['default_max_storage']) ? (int)$settings['default_max_storage'] : 500;

// Determine effective storage limit for this client
$maxStorageMB = $access->max_storage_mb !== null ? $access->max_storage_mb : $defaultMaxStorage;

$clientPath = $whmcsRoot . '/' . $storagePath . '/' . $clientId;

if (!is_dir($clientPath)) {
    mkdir($clientPath, 0755, true);
}

// Calculate current usage
function getDirectorySize($dir) {
    $size = 0;
    if (!is_dir($dir)) return 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

$currentUsageBytes = getDirectorySize($clientPath);
$currentUsageMB = $currentUsageBytes / 1024 / 1024;

// Check if over quota (with 1MB buffer for rounding)
$isOverQuota = ($maxStorageMB > 0 && $currentUsageMB >= ($maxStorageMB - 1));

require_once __DIR__ . '/elfinder/php/autoload.php';

// Build upload options - if over quota, set maxSize to 0 to disable uploads
$uploadMaxSize = $isOverQuota ? 0 : ($maxFileSize . 'M');

$opts = [
    'roots' => [
        [
            'driver' => 'LocalFileSystem',
            'path' => $clientPath,
            'URL' => '',
            'alias' => 'My Files',
            'mimeDetect' => 'internal',
            'tmbPath' => '.tmb',
            'uploadMaxSize' => $uploadMaxSize,
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
