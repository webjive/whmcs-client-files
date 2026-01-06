<?php
/**
 * Client Files Addon for WHMCS
 * Uses elFinder file manager (GPL)
 * Version 2.1.0
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

function clientfiles_config()
{
    return [
        'name' => 'Client Files',
        'description' => 'Allow clients to upload and manage files using elFinder file manager.',
        'version' => '2.1.0',
        'author' => 'WebJIVE',
        'language' => 'english',
        'fields' => [
            'storage_path' => [
                'FriendlyName' => 'Storage Path',
                'Type' => 'text',
                'Size' => '60',
                'Default' => 'client_files',
                'Description' => 'Folder name within WHMCS root for file storage',
            ],
            'max_file_size' => [
                'FriendlyName' => 'Max File Size (MB)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '50',
                'Description' => 'Maximum upload size per file in megabytes',
            ],
            'allowed_extensions' => [
                'FriendlyName' => 'Allowed Extensions',
                'Type' => 'textarea',
                'Rows' => '3',
                'Default' => 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png,gif,zip,rar',
                'Description' => 'Comma-separated list of allowed file extensions',
            ],
            'default_max_storage' => [
                'FriendlyName' => 'Default Max Storage (MB)',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '500',
                'Description' => 'Default maximum storage per client in MB (0 = unlimited). Can be overridden per client.',
            ],
        ],
    ];
}

function clientfiles_activate()
{
    try {
        if (!Capsule::schema()->hasTable('mod_clientfiles_access')) {
            Capsule::schema()->create('mod_clientfiles_access', function ($table) {
                $table->increments('id');
                $table->integer('client_id')->unique();
                $table->tinyInteger('enabled')->default(1);
                $table->integer('max_storage_mb')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        } else {
            // Add max_storage_mb column if it doesn't exist
            if (!Capsule::schema()->hasColumn('mod_clientfiles_access', 'max_storage_mb')) {
                Capsule::schema()->table('mod_clientfiles_access', function ($table) {
                    $table->integer('max_storage_mb')->nullable()->after('enabled');
                });
            }
        }

        $storagePath = ROOTDIR . '/client_files';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        
        $htaccess = $storagePath . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }

        return ['status' => 'success', 'description' => 'Client Files addon activated successfully.'];
    } catch (\Exception $e) {
        return ['status' => 'error', 'description' => 'Error: ' . $e->getMessage()];
    }
}

function clientfiles_deactivate()
{
    return ['status' => 'success', 'description' => 'Addon deactivated. Database tables preserved.'];
}

function clientfiles_upgrade($vars)
{
    $version = $vars['version'];
    
    // Upgrade to 2.1.0 - add max_storage_mb column
    if (version_compare($version, '2.1.0', '<')) {
        if (!Capsule::schema()->hasColumn('mod_clientfiles_access', 'max_storage_mb')) {
            Capsule::schema()->table('mod_clientfiles_access', function ($table) {
                $table->integer('max_storage_mb')->nullable()->after('enabled');
            });
        }
    }
}

function clientfiles_output($vars)
{
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
    $clientId = isset($_REQUEST['client_id']) ? (int)$_REQUEST['client_id'] : 0;
    
    // Handle create action
    if ($action === 'create' && $clientId > 0) {
        createClientFileArea($clientId, $vars);
        header('Location: clientssummary.php?userid=' . $clientId . '&cfmsg=created');
        exit;
    }
    
    // Handle delete action
    if ($action === 'delete' && $clientId > 0) {
        deleteClientFileArea($clientId, $vars);
        header('Location: clientssummary.php?userid=' . $clientId . '&cfmsg=deleted');
        exit;
    }
    
    // Handle view files action - show admin file browser
    if ($action === 'viewfiles' && $clientId > 0) {
        outputAdminFileBrowser($clientId, $vars);
        return;
    }
    
    // Handle update storage limit
    if ($action === 'updatelimit' && $clientId > 0) {
        $newLimit = isset($_REQUEST['max_storage_mb']) ? (int)$_REQUEST['max_storage_mb'] : null;
        if ($newLimit === 0) $newLimit = null; // Use global default
        Capsule::table('mod_clientfiles_access')
            ->where('client_id', $clientId)
            ->update(['max_storage_mb' => $newLimit]);
        header('Location: addonmodules.php?module=clientfiles&updated=' . $clientId);
        exit;
    }
    
    // Default: show management interface
    outputManagementPage($vars);
}

function outputAdminFileBrowser($clientId, $vars)
{
    // Get client info
    $client = Capsule::table('tblclients')
        ->where('id', $clientId)
        ->first();
    
    if (!$client) {
        echo '<div class="alert alert-danger">Client not found.</div>';
        return;
    }
    
    $clientName = htmlspecialchars($client->firstname . ' ' . $client->lastname);
    
    echo '<h2><i class="fas fa-folder-open"></i> Files for ' . $clientName . ' (#' . $clientId . ')</h2>';
    echo '<p><a href="clientssummary.php?userid=' . $clientId . '" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back to Client Summary</a></p>';
    
    // elFinder CSS
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/smoothness/jquery-ui.min.css">';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.64/css/elfinder.min.css">';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.64/css/theme.css">';
    
    echo '<style>#elfinder-admin { margin: 20px 0; border: 1px solid #ddd; border-radius: 4px; }</style>';
    
    echo '<div id="elfinder-admin"></div>';
    
    // elFinder JS
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>';
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.64/js/elfinder.min.js"></script>';
    
    echo '<script>
    jQuery(function() {
        jQuery("#elfinder-admin").elfinder({
            url: "/modules/addons/clientfiles/connector_admin.php?client_id=' . $clientId . '",
            lang: "en",
            height: 500,
            defaultView: "list",
            sound: false,
            ui: ["toolbar", "tree", "path", "stat"],
            uiOptions: {
                toolbar: [
                    ["home", "back", "forward", "up", "reload"],
                    ["download"],
                    ["mkdir", "upload"],
                    ["copy", "cut", "paste", "rm"],
                    ["rename"],
                    ["view", "sort"],
                    ["search"],
                    ["info"]
                ]
            }
        });
    });
    </script>';
}

function outputManagementPage($vars)
{
    $defaultMaxStorage = isset($vars['default_max_storage']) ? (int)$vars['default_max_storage'] : 500;
    
    // Show success message if limit was updated
    if (isset($_GET['updated'])) {
        echo '<div class="alert alert-success"><i class="fas fa-check"></i> Storage limit updated for client #' . (int)$_GET['updated'] . '</div>';
    }
    
    echo '<h2>Client Files Management</h2>';
    echo '<p>Manage client file areas and storage limits. Default storage limit: <strong>' . ($defaultMaxStorage > 0 ? $defaultMaxStorage . ' MB' : 'Unlimited') . '</strong></p>';
    
    $clients = Capsule::table('mod_clientfiles_access')
        ->join('tblclients', 'mod_clientfiles_access.client_id', '=', 'tblclients.id')
        ->where('mod_clientfiles_access.enabled', 1)
        ->select('tblclients.id', 'tblclients.firstname', 'tblclients.lastname', 'tblclients.email', 'mod_clientfiles_access.created_at', 'mod_clientfiles_access.max_storage_mb')
        ->orderBy('tblclients.lastname')
        ->get();
    
    echo '<table class="table table-striped">';
    echo '<thead><tr><th>Client</th><th>Email</th><th>Storage Used</th><th>Storage Limit (MB)</th><th>Actions</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($clients as $client) {
        $storagePath = ROOTDIR . '/' . $vars['storage_path'] . '/' . $client->id;
        $sizeBytes = getDirectorySize($storagePath);
        $sizeMB = round($sizeBytes / 1024 / 1024, 2);
        
        // Determine effective limit
        $effectiveLimit = $client->max_storage_mb !== null ? $client->max_storage_mb : $defaultMaxStorage;
        $limitDisplay = $client->max_storage_mb !== null ? $client->max_storage_mb : '';
        
        // Calculate usage percentage
        $usageClass = '';
        if ($effectiveLimit > 0) {
            $usagePercent = ($sizeMB / $effectiveLimit) * 100;
            if ($usagePercent >= 90) {
                $usageClass = 'danger';
            } elseif ($usagePercent >= 75) {
                $usageClass = 'warning';
            }
        }
        
        echo '<tr' . ($usageClass ? ' class="' . $usageClass . '"' : '') . '>';
        echo '<td><a href="clientssummary.php?userid=' . $client->id . '">' . htmlspecialchars($client->firstname . ' ' . $client->lastname) . '</a></td>';
        echo '<td>' . htmlspecialchars($client->email) . '</td>';
        echo '<td>' . formatBytes($sizeBytes) . '</td>';
        echo '<td>';
        echo '<form method="post" action="addonmodules.php?module=clientfiles&action=updatelimit&client_id=' . $client->id . '" class="form-inline" style="display:inline;">';
        echo '<input type="number" name="max_storage_mb" value="' . $limitDisplay . '" class="form-control" style="width:80px;" min="0" placeholder="' . $defaultMaxStorage . '">';
        echo ' <button type="submit" class="btn btn-xs btn-default" title="Save"><i class="fas fa-save"></i></button>';
        echo '</form>';
        if ($effectiveLimit > 0) {
            echo ' <small class="text-muted">(' . round($usagePercent, 1) . '% used)</small>';
        } else {
            echo ' <small class="text-muted">(unlimited)</small>';
        }
        echo '</td>';
        echo '<td>';
        echo '<a href="addonmodules.php?module=clientfiles&action=viewfiles&client_id=' . $client->id . '" class="btn btn-xs btn-primary" title="View Files"><i class="fas fa-folder-open"></i></a> ';
        echo '<a href="clientssummary.php?userid=' . $client->id . '" class="btn btn-xs btn-default" title="Client Summary"><i class="fas fa-user"></i></a>';
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    
    if (count($clients) === 0) {
        echo '<p class="text-muted">No clients have file areas yet.</p>';
    }
    
    echo '<hr><h3>Look Up Client</h3>';
    echo '<form method="get" action="clientssummary.php" class="form-inline">';
    echo '<div class="form-group"><label>Client ID: <input type="text" name="userid" class="form-control" style="width:100px;margin:0 10px;"></label>';
    echo '<button type="submit" class="btn btn-info">View Client Summary</button></div>';
    echo '</form>';
}

function createClientFileArea($clientId, $vars)
{
    $storagePath = ROOTDIR . '/' . $vars['storage_path'];
    $clientPath = $storagePath . '/' . $clientId;
    
    if (!is_dir($storagePath)) {
        mkdir($storagePath, 0755, true);
    }
    if (!is_dir($clientPath)) {
        mkdir($clientPath, 0755, true);
    }
    
    $htaccess = $storagePath . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n");
    }
    
    $existing = Capsule::table('mod_clientfiles_access')->where('client_id', $clientId)->first();
    if ($existing) {
        Capsule::table('mod_clientfiles_access')->where('client_id', $clientId)->update(['enabled' => 1]);
    } else {
        Capsule::table('mod_clientfiles_access')->insert(['client_id' => $clientId, 'enabled' => 1]);
    }
}

function deleteClientFileArea($clientId, $vars)
{
    $clientPath = ROOTDIR . '/' . $vars['storage_path'] . '/' . $clientId;
    
    if (is_dir($clientPath)) {
        deleteDirectory($clientPath);
    }
    
    Capsule::table('mod_clientfiles_access')->where('client_id', $clientId)->update(['enabled' => 0]);
}

function deleteDirectory($dir)
{
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

function getDirectorySize($dir)
{
    $size = 0;
    if (!is_dir($dir)) return 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

function clientfiles_clientarea($vars)
{
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
        return ['pagetitle' => 'My Files', 'requirelogin' => true];
    }
    
    $access = Capsule::table('mod_clientfiles_access')
        ->where('client_id', $clientId)
        ->where('enabled', 1)
        ->first();
    
    if (!$access) {
        return [
            'pagetitle' => 'My Files',
            'breadcrumb' => ['index.php?m=clientfiles' => 'My Files'],
            'templatefile' => 'templates/noaccess',
            'requirelogin' => true,
            'vars' => [],
        ];
    }
    
    $storagePath = ROOTDIR . '/' . $vars['storage_path'] . '/' . $clientId;
    if (!is_dir($storagePath)) {
        mkdir($storagePath, 0755, true);
    }
    
    return [
        'pagetitle' => 'My Files',
        'breadcrumb' => ['index.php?m=clientfiles' => 'My Files'],
        'templatefile' => 'templates/clientarea',
        'requirelogin' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'client_id' => $clientId,
        ],
    ];
}
