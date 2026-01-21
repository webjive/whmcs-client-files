# Client Files for WHMCS

A professional file sharing addon for WHMCS that allows administrators to share files with clients through a secure, easy-to-use file manager interface.

## Features

### Client Area
- **Full-featured file manager** powered by elFinder (GPL licensed)
- **Drag and drop uploads** - simply drag files into the browser window
- **Folder management** - create, rename, and organize folders
- **File operations** - upload, download, rename, copy, move, and delete files
- **Storage usage display** - visual progress bar showing used/total storage
- **Thumbnail previews** for images
- **Responsive interface** works on desktop and mobile devices

### Admin Area
- **Per-client file areas** - each client gets their own private storage space
- **Admin file browser** - view, upload, and download client files directly from admin
- **Storage usage panel** - see storage used with visual progress bar
- **Storage tracking** - see file counts and storage usage per client
- **Global storage limits** - set a default storage limit for all clients
- **Per-client storage limits** - override the default for individual clients
- **One-click setup** - create file areas for clients with a single click
- **Client Summary integration** - panel appears on the client summary page
- **Automatic cleanup** - files are removed when a client is deleted

### Security
- **Protected storage** - files stored outside web root with .htaccess protection
- **Session-based authentication** - both client and admin connectors verify authentication
- **Per-client isolation** - clients can only access their own files
- **Configurable file types** - restrict uploads to specific extensions
- **File size limits** - set maximum upload sizes
- **Storage quota enforcement** - uploads blocked when quota is reached

## Requirements

- WHMCS 8.0 or higher (tested with WHMCS 8.13)
- PHP 7.4 or higher
- Write permissions on WHMCS root directory

## Installation

1. **Download** the addon package

2. **Extract** the `modules` folder to your WHMCS root directory:
   ```
   /your-whmcs/modules/addons/clientfiles/
   ```

3. **Set permissions** on the addon folder:
   ```bash
   chown -R www-data:www-data modules/addons/clientfiles/
   chmod -R 755 modules/addons/clientfiles/
   ```

4. **Activate** the addon:
   - Go to **Configuration > System Settings > Addon Modules**
   - Find "Client Files" and click **Activate**
   - Configure the module settings
   - Set access control (Full Administrator recommended)

5. **Configure settings** (optional):
   - **Storage Path**: Folder name for file storage (default: `client_files`)
   - **Max File Size**: Maximum upload size in MB (default: 50)
   - **Allowed Extensions**: Comma-separated list of allowed file types
   - **Default Max Storage**: Default storage limit per client in MB (default: 500, 0 = unlimited)

## Usage

### Creating a File Area for a Client

1. Go to **Clients > View/Search Clients**
2. Select a client
3. On the **Summary** tab, find the "Client Files" panel
4. Click **Create File Area**

### Managing Client Files (Admin)

1. Go to the client's Summary page
2. In the "Client Files" panel, click **View Files**
3. Use the file manager to:
   - Upload files (drag & drop or use toolbar)
   - Create folders
   - Download files
   - Rename or delete items

### Managing Storage Limits

1. Go to **Addons > Client Files**
2. Use the **Global Settings** panel to set the default storage limit
3. In the client list, you can set custom limits per client:
   - Leave blank = uses global default
   - Enter a number = custom limit for that client
   - Enter 0 = unlimited storage for that client

### Client Access

Clients can access their files by:
- Navigating to `https://your-whmcs.com/index.php?m=clientfiles`
- Or through a menu link you add to your client area

### Adding a Menu Link (Optional)

To add a "My Files" link to your client area navigation, you can use WHMCS's built-in menu editor or a custom navigation addon.

## File Structure

```
modules/addons/clientfiles/
├── clientfiles.php       # Main addon file
├── connector.php         # Client area elFinder connector
├── connector_admin.php   # Admin area elFinder connector
├── hooks.php            # WHMCS hooks for admin integration
├── elfinder/            # elFinder 2.1.64 library (GPL)
└── templates/
    ├── clientarea.tpl   # Client file manager template
    └── noaccess.tpl     # Access denied template
```

## Database

The addon creates one table:

**mod_clientfiles_access**
| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| client_id | INT | WHMCS client ID |
| enabled | TINYINT | 1 = active, 0 = disabled |
| max_storage_mb | INT | Custom storage limit in MB (NULL = use default) |
| created_at | TIMESTAMP | Creation date |

## Storage

Files are stored in:
```
/your-whmcs/client_files/{client_id}/
```

The storage directory is protected by a `.htaccess` file that denies direct web access. All file access is handled through the authenticated connectors.

## Troubleshooting

### "File area not available" message for clients
- Ensure the admin has created a file area for the client
- Check that the file area is enabled (not disabled)

### 500 error in file manager
- Check PHP error logs for details
- Verify the `elfinder` folder has proper permissions
- Ensure WHMCS session is working correctly

### Files not uploading
- Check `max_file_size` setting in addon configuration
- Check PHP's `upload_max_filesize` and `post_max_size` settings
- Verify storage directory is writable
- Check if client has exceeded their storage quota

### Panel not appearing on client summary
- Ensure the addon is activated
- Check for JavaScript errors in browser console
- Verify hooks.php is present in the addon folder

## License

This project is licensed under the GNU General Public License v3.0 - see the [LICENSE](LICENSE) file for details.

The elFinder file manager library is licensed under the 3-clause BSD license.

## Support

For support, please contact WebJIVE at https://web-jive.com

## Changelog

### Version 2.1.2
- Added storage usage display to admin file browser with visual progress bar
- Added storage usage display to client My Files page
- Warning indicators when storage reaches 75% (yellow) and 90% (red)
- Link to storage settings from admin file browser

### Version 2.0.0
- Complete rebuild using elFinder file manager
- Added admin file browser
- Added global default storage limit setting
- Added per-client storage limits (custom override or use default)
- Storage quota enforcement - uploads blocked when limit reached
- Visual indicators for storage usage and limit type
- Improved WHMCS 8.x compatibility
- Added client summary panel integration
- Enhanced security with session-based authentication

### Version 1.0.0
- Initial release with FilePond-based interface
