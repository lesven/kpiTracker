---
applyTo: '**'
---
# ⚙️ Nicht-funktionale Anforderungen (NFRs)

Diese Anwendung zur KPI-Erfassung und -Verwaltung muss die folgenden technischen und qualitativen Anforderungen erfüllen:

---

## 🔧 Technologie & Architektur

- Die Anwendung wird mit der **aktuellen LTS-Version des Symfony Frameworks** (PHP) entwickelt.
- Als Datenbank wird **MariaDB** verwendet.
- Die Konfiguration erfolgt über **.env-Dateien** (z. B. für Umgebungsvariablen).
- Die Anwendung soll **containerisiert (Docker-ready)** entwickelt werden.
- Bereitstellung per **docker-compose.yml** oder Installationsanleitung (README / Makefile o. ä.).

---

## 🧑‍💻 Codequalität & Struktur

- Der Code folgt den **Clean Code-Prinzipien** (nach Robert C. Martin).
- **Unit-Tests** mit aussagekräftiger Abdeckung (> 70 % für Kernlogik) sind verpflichtend.
- **Kommentare im Code sind auf Deutsch** zu verfassen.
- **Variablen-, Methoden- und Klassennamen sind auf Englisch** zu halten.
- Es wird ein **modularer, wartbarer und nachvollziehbarer Aufbau** erwartet.
- Die Verwendung eines **Coding Style Guide** (PSR-12) wird genutzt.

---

## 🔒 Sicherheit & Datenschutz

- Passwörter werden ausschließlich **sicher gehasht** (z. B. mit bcrypt oder Argon2).
- Die Anwendung schützt gegen **XSS, CSRF und SQL-Injections** (Symfony-Standards).
- Benutzerbezogene Daten sollen auf Anforderung **löschbar (DSGVO-konform)** sein.
- Eingaben sind **serverseitig zu validieren**, zusätzlich wenn möglich clientseitig.

---

## 🧪 Tests & CI/CD

- Es müssen **automatisierte Tests** für zentrale Use Cases bereitgestellt werden.
- Die Tests sollen **lokal und im CI** ausführbar sein (z. B. über `phpunit`).
- Optional: Einrichtung einer **CI/CD-Pipeline** (z. B. GitHub Actions, GitLab CI).

---

## 🖥️ Benutzeroberfläche & Usability

- Es wird ein **responsive Design auf Basis von Bootstrap** verwendet.
- Das System muss auf **Desktop und Tablet** nutzbar sein.
- Formulareingaben sind **benutzerfreundlich validiert**.
- Fehlermeldungen sind **verständlich und hilfreich** formuliert.

---

## 📚 Dokumentation

- Eine **technische Dokumentation** (README) wird mitgeliefert:
  - Setup (Lokal & Prod)
  - Datenbankmigration
  - Tests ausführen
  - Architekturüberblick
- Optional: Eine **Mini-Anleitung für Admins und Endnutzer** (z. B. als Markdown).


