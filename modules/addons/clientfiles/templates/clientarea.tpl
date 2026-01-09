<!-- elFinder CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/smoothness/jquery-ui.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.64/css/elfinder.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.64/css/theme.css">

<style>
#elfinder { margin: 20px 0; border: 1px solid #ddd; border-radius: 4px; }
.file-manager-header { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
.file-manager-header h2 { margin: 0 0 10px 0; color: #333; }
.file-manager-header p { margin: 0; color: #666; }
.storage-info { 
    background: #f8f9fa; 
    border: 1px solid #e9ecef; 
    border-radius: 6px; 
    padding: 15px 20px; 
    margin-bottom: 20px;
}
.storage-info .progress {
    height: 20px;
    margin-bottom: 8px;
    background-color: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}
.storage-info .progress-bar {
    line-height: 20px;
    font-size: 12px;
    font-weight: 600;
}
.storage-info .storage-text {
    font-size: 14px;
    color: #495057;
}
.storage-info .storage-text strong {
    color: #212529;
}
</style>

<div class="file-manager-header">
    <h2><i class="fas fa-folder-open"></i> My Files</h2>
    <p>Upload, organize, and manage your files. Drag and drop files to upload, or use the toolbar buttons.</p>
</div>

<div class="storage-info">
    <div class="row">
        <div class="col-sm-8">
            {if $max_storage_mb > 0}
                <div class="progress">
                    <div class="progress-bar {if $usage_percent >= 90}bg-danger{elseif $usage_percent >= 75}bg-warning{else}bg-success{/if}" 
                         role="progressbar" 
                         style="width: {if $usage_percent > 100}100{else}{$usage_percent}{/if}%"
                         aria-valuenow="{$usage_percent}" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                        {if $usage_percent >= 10}{$usage_percent}%{/if}
                    </div>
                </div>
            {/if}
            <div class="storage-text">
                <i class="fas fa-hdd"></i> 
                <strong>{$used_mb} MB</strong> used
                {if $max_storage_mb > 0}
                    of <strong>{$max_storage_mb} MB</strong>
                    {if $usage_percent >= 90}
                        <span class="text-danger"> - Storage almost full!</span>
                    {elseif $usage_percent >= 75}
                        <span class="text-warning"> - Running low on space</span>
                    {/if}
                {else}
                    <span class="text-muted">(Unlimited storage)</span>
                {/if}
            </div>
        </div>
    </div>
</div>

<div id="elfinder"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.64/js/elfinder.min.js"></script>

<script>
jQuery(function() {
    jQuery('#elfinder').elfinder({
        url: 'modules/addons/clientfiles/connector.php',
        lang: 'en',
        height: 500,
        defaultView: 'icons',
        sound: false,
        ui: ['toolbar', 'tree', 'path', 'stat'],
        uiOptions: {
            toolbar: [
                ['home', 'back', 'forward', 'up', 'reload'],
                ['mkdir', 'upload'],
                ['copy', 'cut', 'paste', 'rm'],
                ['rename'],
                ['view', 'sort'],
                ['search'],
                ['info']
            ]
        }
    });
});
</script>
