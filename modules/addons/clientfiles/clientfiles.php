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
        'version' => '2.1.2',
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
                'Description' => 'Default storage limit for new clients (0 = unlimited). Manage per-client limits from the addon page.',
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

function getDefaultMaxStorage()
{
    $val = Capsule::table('tbladdonmodules')
        ->where('module', 'clientfiles')
        ->where('setting', 'default_max_storage')
        ->value('value');
    return $val !== null ? (int)$val : 500;
}

function clientfiles_output($vars)
{
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
    $clientId = isset($_REQUEST['client_id']) ? (int)$_REQUEST['client_id'] : 0;
    
    if ($action === 'create' && $clientId > 0) {
        createClientFileArea($clientId, $vars);
        header('Location: clientssummary.php?userid=' . $clientId . '&cfmsg=created');
        exit;
    }
    
    if ($action === 'delete' && $clientId > 0) {
        deleteClientFileArea($clientId, $vars);
        header('Location: clientssummary.php?userid=' . $clientId . '&cfmsg=deleted');
        exit;
    }
    
    if ($action === 'viewfiles' && $clientId > 0) {
        outputAdminFileBrowser($clientId, $vars);
        return;
    }
    
    // Handle update storage limit for individual client
    if ($action === 'updatelimit' && $clientId > 0) {
        $newLimit = isset($_REQUEST['max_storage_mb']) ? trim($_REQUEST['max_storage_mb']) : '';
        if ($newLimit === '') {
            $newLimit = null; // Use global default
        } else {
            $newLimit = (int)$newLimit;
        }
        Capsule::table('mod_clientfiles_access')
            ->where('client_id', $clientId)
            ->update(['max_storage_mb' => $newLimit]);
        header('Location: addonmodules.php?module=clientfiles&updated=' . $clientId);
        exit;
    }
    
    // Handle update global default
    if ($action === 'updateglobal') {
        $newDefault = isset($_REQUEST['default_max_storage']) ? (int)$_REQUEST['default_max_storage'] : 500;
        
        // Update the addon setting
        Capsule::table('tbladdonmodules')
            ->where('module', 'clientfiles')
            ->where('setting', 'default_max_storage')
            ->update(['value' => $newDefault]);
        
        header('Location: addonmodules.php?module=clientfiles&globalupdated=1');
        exit;
    }
    
    outputManagementPage($vars);
}

function outputAdminFileBrowser($clientId, $vars)
{
    $client = Capsule::table('tblclients')
        ->where('id', $clientId)
        ->first();
    
    if (!$client) {
        echo '<div class="alert alert-danger">Client not found.</div>';
        return;
    }
    
    // Get storage info
    $access = Capsule::table('mod_clientfiles_access')
        ->where('client_id', $clientId)
        ->where('enabled', 1)
        ->first();
    
    $defaultMaxStorage = getDefaultMaxStorage();
    $isCustom = ($access && $access->max_storage_mb !== null);
    $maxStorageMB = $isCustom ? (int)$access->max_storage_mb : $defaultMaxStorage;
    
    $storagePath = isset($vars['storage_path']) ? $vars['storage_path'] : 'client_files';
    $clientPath = ROOTDIR . '/' . $storagePath . '/' . $clientId;
    $usedBytes = getDirectorySize($clientPath);
    $usedMB = round($usedBytes / 1024 / 1024, 2);
    
    $usagePercent = 0;
    if ($maxStorageMB > 0) {
        $usagePercent = round(($usedMB / $maxStorageMB) * 100, 1);
    }
    
    $clientName = htmlspecialchars($client->firstname . ' ' . $client->lastname);
    
    echo '<h2><i class="fas fa-folder-open"></i> Files for ' . $clientName . ' (#' . $clientId . ')</h2>';
    
    // Storage usage panel
    echo '<div class="panel panel-default" style="margin-top:15px;">';
    echo '<div class="panel-heading"><h3 class="panel-title"><i class="fas fa-hdd"></i> Storage Usage</h3></div>';
    echo '<div class="panel-body">';
    
    // Progress bar
    $barClass = 'progress-bar-success';
    if ($usagePercent >= 90) {
        $barClass = 'progress-bar-danger';
    } elseif ($usagePercent >= 75) {
        $barClass = 'progress-bar-warning';
    }
    
    echo '<div class="row">';
    echo '<div class="col-md-8">';
    echo '<div class="progress" style="height:25px;margin-bottom:10px;">';
    echo '<div class="progress-bar ' . $barClass . '" role="progressbar" style="width:' . min($usagePercent, 100) . '%;line-height:25px;">';
    if ($usagePercent >= 10) {
        echo $usagePercent . '%';
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="col-md-4 text-right">';
    echo '<strong>' . $usedMB . ' MB</strong> of ';
    if ($maxStorageMB > 0) {
        echo '<strong>' . $maxStorageMB . ' MB</strong>';
    } else {
        echo '<strong>Unlimited</strong>';
    }
    echo '</div>';
    echo '</div>';
    
    // Limit info and link to settings
    echo '<p class="text-muted" style="margin:0;">';
    if ($isCustom) {
        echo 'Custom limit set for this client. ';
    } else {
        echo 'Using global default limit. ';
    }
    echo '<a href="addonmodules.php?module=clientfiles">Manage storage limits</a>';
    echo '</p>';
    
    echo '</div>';
    echo '</div>';
    
    echo '<p><a href="clientssummary.php?userid=' . $clientId . '" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back to Client Summary</a></p>';
    
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/smoothness/jquery-ui.min.css">';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.64/css/elfinder.min.css">';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.64/css/theme.css">';
    
    echo '<style>#elfinder-admin { margin: 20px 0; border: 1px solid #ddd; border-radius: 4px; }</style>';
    
    echo '<div id="elfinder-admin"></div>';
    
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
    // Always fetch fresh from database
    $defaultMaxStorage = getDefaultMaxStorage();
    $storagePath = isset($vars['storage_path']) ? $vars['storage_path'] : 'client_files';
    
    // Show success messages
    if (isset($_GET['updated'])) {
        echo '<div class="alert alert-success"><i class="fas fa-check"></i> Storage limit updated for client #' . (int)$_GET['updated'] . '</div>';
    }
    if (isset($_GET['globalupdated'])) {
        echo '<div class="alert alert-success"><i class="fas fa-check"></i> Global default storage limit updated to ' . $defaultMaxStorage . ' MB.</div>';
    }
    
    echo '<h2>Client Files Management</h2>';
    
    // Global default setting box
    echo '<div class="panel panel-default">';
    echo '<div class="panel-heading"><h3 class="panel-title"><i class="fas fa-cog"></i> Global Settings</h3></div>';
    echo '<div class="panel-body">';
    echo '<form method="post" action="addonmodules.php?module=clientfiles&action=updateglobal" class="form-inline">';
    echo '<label>Default Storage Limit (MB): ';
    echo '<input type="number" name="default_max_storage" value="' . $defaultMaxStorage . '" class="form-control" style="width:100px;margin:0 10px;" min="0">';
    echo '</label>';
    echo '<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Default</button>';
    echo ' <small class="text-muted" style="margin-left:15px;">This applies to all clients with blank limit below. Set to 0 for unlimited.</small>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
    
    // Count clients using default vs custom
    $clientsUsingDefault = Capsule::table('mod_clientfiles_access')
        ->where('enabled', 1)
        ->whereNull('max_storage_mb')
        ->count();
    $clientsWithCustom = Capsule::table('mod_clientfiles_access')
        ->where('enabled', 1)
        ->whereNotNull('max_storage_mb')
        ->count();
    
    echo '<p><strong>' . $clientsUsingDefault . '</strong> client(s) using default (' . $defaultMaxStorage . ' MB), <strong>' . $clientsWithCustom . '</strong> with custom limits.</p>';
    
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
        $clientStoragePath = ROOTDIR . '/' . $storagePath . '/' . $client->id;
        $sizeBytes = getDirectorySize($clientStoragePath);
        $sizeMB = round($sizeBytes / 1024 / 1024, 2);
        
        // Check if custom or using default - NULL means using default
        $isCustom = ($client->max_storage_mb !== null);
        $effectiveLimit = $isCustom ? (int)$client->max_storage_mb : $defaultMaxStorage;
        
        // Calculate usage percentage
        $usageClass = '';
        $usagePercent = 0;
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
        // Only show value if custom, otherwise leave blank with placeholder
        if ($isCustom) {
            echo '<input type="number" name="max_storage_mb" value="' . (int)$client->max_storage_mb . '" class="form-control" style="width:80px;" min="0">';
            echo ' <button type="submit" class="btn btn-xs btn-default" title="Save"><i class="fas fa-save"></i></button>';
            if ($effectiveLimit > 0) {
                echo ' <small class="text-muted">(' . round($usagePercent, 1) . '%, custom)</small>';
            } else {
                echo ' <small class="text-muted">(unlimited, custom)</small>';
            }
        } else {
            echo '<input type="number" name="max_storage_mb" value="" class="form-control" style="width:80px;background:#f5f5f5;" min="0" placeholder="default">';
            echo ' <button type="submit" class="btn btn-xs btn-default" title="Save"><i class="fas fa-save"></i></button>';
            echo ' <small class="text-muted">(' . round($usagePercent, 1) . '%, using ' . $defaultMaxStorage . ' MB default)</small>';
        }
        echo '</form>';
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
    
    echo '<p class="text-muted"><small>Leave blank to use the global default. Enter a number to set a custom limit for that client.</small></p>';
    
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
        // New clients get NULL - they use the global default
        Capsule::table('mod_clientfiles_access')->insert(['client_id' => $clientId, 'enabled' => 1, 'max_storage_mb' => null]);
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
    
    // Get storage info
    $storagePath = ROOTDIR . '/' . $vars['storage_path'] . '/' . $clientId;
    if (!is_dir($storagePath)) {
        mkdir($storagePath, 0755, true);
    }
    
    $defaultMaxStorage = getDefaultMaxStorage();
    $isCustom = ($access->max_storage_mb !== null);
    $maxStorageMB = $isCustom ? (int)$access->max_storage_mb : $defaultMaxStorage;
    
    $usedBytes = getDirectorySize($storagePath);
    $usedMB = round($usedBytes / 1024 / 1024, 2);
    
    $usagePercent = 0;
    if ($maxStorageMB > 0) {
        $usagePercent = round(($usedMB / $maxStorageMB) * 100, 1);
    }
    
    return [
        'pagetitle' => 'My Files',
        'breadcrumb' => ['index.php?m=clientfiles' => 'My Files'],
        'templatefile' => 'templates/clientarea',
        'requirelogin' => true,
        'vars' => [
            'modulelink' => $vars['modulelink'],
            'client_id' => $clientId,
            'used_mb' => $usedMB,
            'max_storage_mb' => $maxStorageMB,
            'usage_percent' => $usagePercent,
        ],
    ];
}
