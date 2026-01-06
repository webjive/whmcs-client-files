<?php
if (!defined("WHMCS")) die("This file cannot be accessed directly");

use WHMCS\Database\Capsule;

add_hook('AdminAreaClientSummaryPage', 1, function($vars) {
    $clientId = (int)$vars['userid'];
    
    $settings = Capsule::table('tbladdonmodules')
        ->where('module', 'clientfiles')
        ->pluck('value', 'setting');
    
    $storagePath = isset($settings['storage_path']) ? $settings['storage_path'] : 'client_files';
    
    $access = null;
    try {
        if (Capsule::schema()->hasTable('mod_clientfiles_access')) {
            $access = Capsule::table('mod_clientfiles_access')
                ->where('client_id', $clientId)
                ->first();
        }
    } catch (\Exception $e) {
        return '';
    }
    
    $clientPath = ROOTDIR . '/' . $storagePath . '/' . $clientId;
    
    $html = '<div class="panel panel-default" id="clientFilesPanel" style="display:none;">';
    $html .= '<div class="panel-heading"><h3 class="panel-title"><i class="fas fa-folder"></i> Client Files</h3></div>';
    $html .= '<div class="panel-body">';
    
    if ($access && $access->enabled) {
        $fileCount = 0;
        $totalSize = 0;
        
        if (is_dir($clientPath)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($clientPath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($files as $file) {
                if ($file->isFile() && strpos($file->getFilename(), '.') !== 0) {
                    $fileCount++;
                    $totalSize += $file->getSize();
                }
            }
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($totalSize, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $sizeFormatted = round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
        
        $html .= '<p><span class="label label-success">Active</span></p>';
        $html .= '<p><strong>Files:</strong> ' . $fileCount . '<br>';
        $html .= '<strong>Storage:</strong> ' . $sizeFormatted . '</p>';
        $html .= '<a href="addonmodules.php?module=clientfiles&action=viewfiles&client_id=' . $clientId . '" class="btn btn-primary btn-sm"><i class="fas fa-folder-open"></i> View Files</a> ';
        $html .= '<a href="addonmodules.php?module=clientfiles&action=delete&client_id=' . $clientId . '" class="btn btn-danger btn-sm" onclick="return confirm(\'Delete all files and disable?\')"><i class="fas fa-trash"></i> Delete File Area</a>';
    } elseif ($access && !$access->enabled) {
        $html .= '<p><span class="label label-warning">Disabled</span></p>';
        $html .= '<a href="addonmodules.php?module=clientfiles&action=create&client_id=' . $clientId . '" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Re-enable</a>';
    } else {
        $html .= '<p><span class="label label-default">Not Created</span></p>';
        $html .= '<a href="addonmodules.php?module=clientfiles&action=create&client_id=' . $clientId . '" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Create File Area</a>';
    }
    
    $html .= '</div></div>';
    
    // JavaScript to move panel above the Products/Services table
    $html .= '<script>
    jQuery(document).ready(function() {
        var panel = jQuery("#clientFilesPanel");
        // Find the strong tag containing Products/Services, then get parent table
        jQuery("strong").filter(function() {
            return jQuery(this).text().trim() === "Products/Services";
        }).closest("table.form").each(function() {
            panel.detach().insertBefore(jQuery(this));
            panel.show();
            return false;
        });
        // Fallback
        if (panel.css("display") === "none") {
            panel.show();
        }
    });
    </script>';
    
    return $html;
});

add_hook('ClientDelete', 1, function($vars) {
    $clientId = (int)$vars['userid'];
    
    $settings = Capsule::table('tbladdonmodules')
        ->where('module', 'clientfiles')
        ->pluck('value', 'setting');
    
    $storagePath = isset($settings['storage_path']) ? $settings['storage_path'] : 'client_files';
    $clientPath = ROOTDIR . '/' . $storagePath . '/' . $clientId;
    
    if (is_dir($clientPath)) {
        $it = new RecursiveDirectoryIterator($clientPath, FilesystemIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($clientPath);
    }
    
    try {
        Capsule::table('mod_clientfiles_access')->where('client_id', $clientId)->delete();
    } catch (\Exception $e) {}
});
