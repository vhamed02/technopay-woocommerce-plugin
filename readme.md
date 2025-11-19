# WordPress ReadMe

## Installation

1. Unzip the package and upload the files.
2. Open `wp-admin/install.php` in your browser.
3. If needed, edit `wp-config-sample.php`, add DB details, save it as `wp-config.php`, then retry the installer.
4. After installation, log in using the username and password you set (or the generated one).

## Updating

### Automatic Update
- Go to `wp-admin/update-core.php` and follow the instructions.

### Manual Update
1. Backup custom files.
2. Delete old WordPress files.
3. Upload new ones.
4. Visit `/wp-admin/upgrade.php`.

## Requirements

- PHP **7.2.24+**
- MySQL **5.5.5+**

## Recommended

- PHP **7.4+**
- MySQL **8.0+** or MariaDB **10.5+**
- Apache `mod_rewrite`
- HTTPS support

## Resources

- Documentation: https://wordpress.org/documentation/
- Support Forums: https://wordpress.org/support/forums/

## License

Released under the GPL v2 or later. See `license.txt`.
