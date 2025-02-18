
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

---

#### Datenbank
Treiber ist SQLite. Die DB wird im Ordner 'database' beim ersten Start erstellt.

---

#### Env-Datei
Im Projektordner muss eine ".env"-Datei erstellt werden, die die Basiskonfiguration enthält.

```
APP_TIMEZONE=Europe/Berlin
APP_URL=http://localhost
APP_LOCALE=de
ADMIN_PASSPHRASE=password
```
## Features

**Seiten**
- Monitor (*/monitor*)
- Verwaltung (*/admin*)

**Einträge**
- *Info:* Mitteilungen
- *Event:* Termine, Ereignisse
- *Task:* Einzelne Aufgaben
- *Recurring:* Wiederkehrende Aufgaben

**Automatische Module**
- *Abfallkalender:* Parst ein Online-iCal-Abo nach Abholterminen und fügt Aufgaben hinzu.
- *Maltesercloud:* Synchronisiert einen Sharepointkalender mit der Terminagenda des WIM.
- *NINA:* Blendet Bevölkerungswarnung des NINA-Portals im WIM ein.

Die Aktualisierung der Module wird über API-Endpunkte ermöglicht. Dadurch kann z.B. über CronJobs regelmäßig abgerufen werden:
```
curl 'http://localhost/api/module-trash'
curl 'http://localhost/api/module-maltesercloud'
curl 'http://localhost/api/module-nina'
```