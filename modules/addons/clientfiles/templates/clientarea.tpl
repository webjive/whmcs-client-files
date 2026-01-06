<!-- elFinder CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/smoothness/jquery-ui.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.64/css/elfinder.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/elfinder/2.1.64/css/theme.css">

<style>
#elfinder { margin: 20px 0; border: 1px solid #ddd; border-radius: 4px; }
.file-manager-header { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
.file-manager-header h2 { margin: 0 0 10px 0; color: #333; }
.file-manager-header p { margin: 0; color: #666; }
</style>

<div class="file-manager-header">
    <h2><i class="fas fa-folder-open"></i> My Files</h2>
    <p>Upload, organize, and manage your files. Drag and drop files to upload, or use the toolbar buttons.</p>
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
