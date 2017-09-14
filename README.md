# phpMyAdmin Single Sign-On with LiveConfig<sup>®</sup>
Log in to [phpMyAdmin](https://www.phpMyAdmin.net) directly from [LiveConfig<sup>®</sup> Control Panel](https://www.liveconfig.com) without entering the database credentials.

*Read this in other languages: [English](README.md), [Deutsch](README.de.md).*

## Background
To connect with phpMyAdmin to a MySQL server, usually the database credentials are required. This script (`lc-sso.php`) allows logging in to phpMyAdmin directly from LiveConfig without entering the database credentials. To avoid unsafe submission through the users' browser, this script transmits them server-side. So the user also can't see the currently configured database password.

**Important:** LiveConfig never saves passwords that aren't required any more. When creating a new MySQL database or changing its password, LiveConfig deletes this password immediately after execution. If *single sign-on* is enabled in LiveConfig, these passwords remain in the LiveConfig database (encrypted, of course). Because of this, if *single sign-on* is enabled for an existing MySQL database its password must be entered (or a new password must be set).

## Prerequisites
* LiveConfig v2.5.0 (or later)
* phpMyAdmin (already installed), with PHP cURL extension

## Installation
1. copy the file `lc-sso.php` into the root directory of your phpMyAdmin installation
2. edit the file `config.inc.php` and add a new server:

```javascript
$i++;
$cfg['Servers'][$i]['auth_type'] = 'signon';
$cfg['Servers'][$i]['host'] = '';
$cfg['Servers'][$i]['SignonSession'] = 'SignonSession';
$cfg['Servers'][$i]['SignonURL'] = 'lc-sso.php';
```

   Remember the number (value of `$i`) of this new server entry!

3. edit the file `lc-sso.php` and set `PMA_SIGNON_INDEX` to the server id (`$i`) of the previously added entry
4. if you have changed the `SignonSession` name in `config.inc.php`, also adjust `PMA_SIGNON_SESSIONNAME` accordingly
5. if you're running LiveConfig with a SSL certificate of a trusted CA, you should set `PMA_DISABLE_SSL_PEER_VALIDATION` to `FALSE`
6. finally log in as *admin* to LiveConfig, go to *Servers* -> *Server Management* -> *Databases*. Edit the MySQL options and enable the *Single Sign-On* checkbox there. A link to your phpMyAdmin installation must also be configured of course.

Normal LiveConfig users then may enable single sign-on for new and existing databases at *Hosting* -> *Databases*. With existing databases, the password must be provided. The link to phpMyAdmin contains a small key icon to symbolize that SSO is enabled.
