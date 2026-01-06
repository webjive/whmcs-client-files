# Client Files for WHMCS

A professional file sharing addon for WHMCS that allows administrators to share files with clients through a secure, easy-to-use file manager interface.

## Features

### Client Area
- **Full-featured file manager** powered by elFinder (GPL licensed)
- **Drag and drop uploads** - simply drag files into the browser window
- **Folder management** - create, rename, and organize folders
- **File operations** - upload, download, rename, copy, move, and delete files
- **Thumbnail previews** for images
- **Responsive interface** works on desktop and mobile devices

### Admin Area
- **Per-client file areas** - each client gets their own private storage space
- **Admin file browser** - view, upload, and download client files directly from admin
- **Storage tracking** - see file counts and storage usage per client
- **One-click setup** - create file areas for clients with a single click
- **Client Summary integration** - panel appears on the client summary page
- **Automatic cleanup** - files are removed when a client is deleted

### Security
- **Protected storage** - files stored outside web root with .htaccess protection
- **Session-based authentication** - both client and admin connectors verify authentication
- **Per-client isolation** - clients can only access their own files
- **Configurable file types** - restrict uploads to specific extensions
- **File size limits** - set maximum upload sizes

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
   - **Max Storage Per Client**: Storage limit per client in MB (0 = unlimited)

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

### Panel not appearing on client summary
- Ensure the addon is activated
- Check for JavaScript errors in browser console
- Verify hooks.php is present in the addon folder

## License

This addon is proprietary software by WebJIVE.

The elFinder file manager library is licensed under the 3-clause BSD license.

## Support

For support, please contact WebJIVE at https://web-jive.com

## Changelog

### Version 2.0.0
- Complete rebuild using elFinder file manager
- Added admin file browser
- Improved WHMCS 8.x compatibility
- Added client summary panel integration
- Enhanced security with session-based authentication

### Version 1.0.0
- Initial release with FilePond-based interface
