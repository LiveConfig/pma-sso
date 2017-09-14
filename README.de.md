# phpMyAdmin Single Sign-On mit LiveConfig<sup>®</sup>
Anmeldung an [phpMyAdmin](https://www.phpmyadmin.net) direkt aus dem [LiveConfig<sup>®</sup> Control Panel](https://www.liveconfig.com) heraus, ohne die Datenbank-Zugangsdaten eingeben zu müssen.

*Read this in other languages: [English](README.md), [Deutsch](README.de.md).*

## Hintergrund
Um sich mit phpMyAdmin an einer MySQL-Datenbank anzumelden, sind zwingend deren Zugangsdaten erforderlich. Dieses Script (`lc-sso.php`) ermöglicht es, sich aus LiveConfig heraus direkt in phpMyAdmin anzumelden, ohne die Zugangsdaten eingeben zu müssen. Damit diese nicht unsicher über den Browser übermittelt werden, überträgt das Script die Daten serverseitig - der Benutzer hat so keine Möglichkeit, das hinterlegte Passwort auszulesen.

**Wichtig:** LiveConfig speichert aus Prinzip keine Passwörter wenn diese nicht mehr benötigt werden. Nach dem Anlegen einer MySQL-Datenbank oder einer Passwortänderung wird dieses also sofort aus LiveConfg heraus gelöscht. Wenn *Single Sign-On* in LiveConfig aktiviert wird, bleiben die betroffenen Passwörter gespeichert - in der internen Datenbank sind diese aber immer verschlüsselt. Wird *Single Sign-On* nachträglich für eine Datenbank aktiviert, muss deshalb auch das Passwort noch einmal eingeben werden (oder ein neues Passwort für die Datenbank eingerichtet werden).

## Voraussetzungen
* LiveConfig v2.5.0 (oder später)
* phpMyAdmin (bereits installiert), mit PHP cURL-Erweiterung

## Installation
1. kopieren Sie die Datei `lc-sso.php` in das Hauptverzeichnis der phpMyAdmin-Installation
2. bearbeiten Sie die Datei `config.inc.php` und fügen einen neuen "Server" hinzu:
   ```javascript
$i++;
$cfg['Servers'][$i]['auth_type'] = 'signon';
$cfg['Servers'][$i]['host'] = '';
$cfg['Servers'][$i]['SignonSession'] = 'SignonSession';
$cfg['Servers'][$i]['SignonURL'] = 'lc-sso.php';
```
   Merken Sie sich die Nummer (Wert von `$i`) dieses neuen Server-Eintrags!
3. bearbeiten Sie die Datei `lc-sso.php` und setzen `PMA_SIGNON_INDEX` auf die Server-ID (`$i`) des neuen Eintrags
4. wenn Sie den `SignonSession`-Namen in der Datei `config.inc.php` geändert haben, passen Sie die Einstellung `PMA_SIGNON_SESSIONNAME` entsprechend an
5. wenn Sie LiveConfig mit einem SSL-Zertifikat einer vertrauenswürdigen CA verwenden, sollten Sie die Einstellung `PMA_DISABLE_SSL_PEER_VALIDATION` auf `FALSE` setzen
6. melden Sie sich zum Schluß als *admin* an LiveConfig an, gehen auf *Server* -> *Serververwaltung* -> *Datenbanken*. Bearbeiten Sie dort die MySQL-Einstellungen und aktivieren die Checkbox *Single Sign-On erlauben*. Ein Link auf eine phpMyAdmin-Installation muss dort selbstverständlich auch hinterlegt sein.

Normale LiveConfig-Benutzer können dann unter *Hosting* -> *Datenbanken* für neue und bestehende Datenbanken Single Sign-On aktivieren. Bei bestehenden Datenbanken muss das Passwort hinterlegt werden.
Beim Link auf phpMyAdmin ist ein kleines Schlüssel-Icon hinterlegt, welches zeigt, dass beim Klick darauf die Anmeldung automatisch erfolgt.
