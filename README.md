
# WIM-app

**WIM (Wachen-Info-Monitor)**
Ursprünglich für eine Rettungswache konzipiert, stellt diese Anwendung Aufgaben, Mitteilungen und Termine dynamisch als Ergänzung zum Schwarzen Brett dar.

[![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com/)

[![Vue.js](https://img.shields.io/badge/Vue.js-35495E?style=for-the-badge&logo=vue.js&logoColor=4FC08)](https://vuejs.org/)

[![Inertia.js](https://img.shields.io/badge/Inertia.js-9057e9?style=for-the-badge&logoColor=white)](https://inertiajs.com/)


## Einrichtung

Das System ist als eigenständige Monitorlösung konzipiert. Ein einzelner PC – beispielsweise ein Raspberry Pi – übernimmt dabei doppelte Rollen: Er hostet das PHP-Backend und startet beim Booten automatisch einen Vollbild-Browser (z.B. Chromium), der die Monitorseite (/monitor) auf einem angeschlossenen Bildschirm anzeigt.

Der PC muss also folgende Voraussetzungen erfüllen:
- **Webserver** (z.B. nginx)
- **PHP** (>= v8.2)

In der Dokumentation von Laravel finden sich weitere Hinweise:

https://laravel.com/docs/11.x/deployment#main-content

----

#### Projekt vorbereiten

- PHP & Composer
```
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.4)"
```

- NodeJs, NPM
```
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.2/install.sh | bash
```

- Repo laden
```
cd /var/www/
git clone https://github.com/steiiin/wim-app
```

#### Env-Datei
Im Projektordner muss eine ".env"-Datei erstellt werden, die die Basiskonfiguration enthält:

```
APP_TIMEZONE=Europe/Berlin
APP_URL=http://localhost
APP_LOCALE=de
ADMIN_PASSPHRASE=password
#########################
APP_NAME=WIM
VITE_APP_NAME="${APP_NAME}"
```

Danach muss im Projektordner durch Laravel der App-Schlüssel erstellt werden:
```
cd /var/www/wim-app/
sudo chown www-data:www-data .env
sudo -u www-data php artisan key:generate
```

#### Datenbank
Vor dem ersten Start muss die Datenbank erstellt werden:
```
cd /var/www/wim-app/
touch database/database.sqlite
sudo chown www-data:www-data database/database.sqlite
sudo -u www-data php artisan migrate:fresh
```

#### Projekt erstellen
Zum Schluss müssen die Abhängigkeiten installiert und das Projekt erstellt werden:
```
cd /var/www/wim-app/
composer install
npm update
```

## Features

**Seiten**
- Monitor (*/monitor*)
- Verwaltung (*/admin*)

|Screenshots|
|---|
| ![screenshot-admin](https://github.com/steiiin/wim-app/blob/main/docs/wim-admin.png?raw=true) |
| ![screenshot-monitor](https://github.com/steiiin/wim-app/blob/main/docs/wim-monitor.png?raw=true) |
| ![screenshot-monitor-dark](https://github.com/steiiin/wim-app/blob/main/docs/wim-monitor-dark.png?raw=true) |

**Einträge**
- *Info:* Mitteilungen
- *Event:* Termine, Ereignisse
- *Task:* Einzelne Aufgaben
- *Recurring:* Wiederkehrende Aufgaben

**Automatische Module**
- *Abfallkalender:* Parst ein Online-iCal-Abo nach Abholterminen und fügt Aufgaben hinzu.
- *Sharepoint-Liste:* Synchronisiert einen Sharepointkalender mit der Terminagenda des WIM.

### CronJobs
Um die Module automatisch abzurufen und Hintergrundaufgaben regelmäßig durchzuführen, sollten folgende CronJobs erstellt werden:
```
0 0   * * 1 curl -k "https://localhost/api/trash"
0 */6 * * * curl -k "https://localhost/api/sharepoint"
0 20  * * * curl -k "https://localhost/api/do-jobs"
```

### Zwischenspeicher löschen
Nachdem Änderungen am Projekt durchgeführt wurden (z.B. env-Datei), muss der Zwischenspeicher gelöscht werden:
```
chown -R www-data:www-data /var/www/wim-app/
sudo -u www-data php artisan config:clear 2>&1
sudo -u www-data php artisan cache:clear 2>&1
sudo -u www-data php artisan route:clear 2>&1
sudo -u www-data php artisan view:clear 2>&1
sudo -u www-data php artisan config:cache 2>&1
sudo -u www-data php artisan route:cache 2>&1
sudo -u www-data php artisan view:cache 2>&1
```