# 📌 User Stories für KPI-Erfassungssystem

---

## User Story 1 – Benutzer können sich einloggen

**Als** registrierter Benutzer  
**möchte ich** mich mit meiner E-Mail und meinem Passwort einloggen können  
**damit** ich Zugang zu meinen KPIs und Funktionen erhalte.

**Akzeptanzkriterien:**
- Es gibt ein Login-Formular mit E-Mail und Passwort.
- Nur registrierte Nutzer mit korrekten Zugangsdaten erhalten Zugriff.
- Es gibt eine Fehlermeldung bei falschen Zugangsdaten.

---

## User Story 2 – Administrator kann Benutzer anlegen

**Als** Administrator  
**möchte ich** neue Benutzer anlegen und ihnen Rollen zuweisen können  
**damit** ich festlegen kann, wer auf das System zugreifen darf und welche Rechte er hat.

**Akzeptanzkriterien:**
- Es gibt eine Maske zur Erstellung neuer Benutzer.
- Rollen: "Benutzer" oder "Administrator" können vergeben werden.
- Passwort wird initial gesetzt oder automatisch generiert.

---

## User Story 3 – Benutzer kann KPI anlegen

**Als** Benutzer  
**möchte ich** eigene KPIs anlegen können  
**damit** ich meine regelmäßigen Berichtspflichten strukturiert abbilden kann.

**Akzeptanzkriterien:**
- Formular mit Eingabe von KPI-Name und Intervall (wöchentlich, monatlich, quartalsweise).
- KPI wird dem eingeloggten Benutzer zugeordnet.
- Validierung von Eingaben.

---

## User Story 4 – Administrator kann KPIs für Benutzer anlegen

**Als** Administrator  
**möchte ich** KPIs für andere Benutzer anlegen können  
**damit** ich die Berichtspflichten der Organisation zentral verwalten kann.

**Akzeptanzkriterien:**
- Auswahl eines Benutzers zur KPI-Zuweisung.
- Felder wie in User Story 3.
- KPI erscheint im Konto des betreffenden Nutzers.

---

## User Story 5 – Benutzer kann KPI-Werte erfassen

**Als** Benutzer  
**möchte ich** zu jeder meiner KPIs regelmäßig Werte eintragen können  
**damit** ich meine Performance oder Fortschritte dokumentiere.

**Akzeptanzkriterien:**
- Eintrag enthält mindestens: Wert (Zahl), Zeitraumbezug.
- System speichert und verknüpft den Eintrag korrekt.

---

## User Story 6 – Reminder für fällige KPI-Einträge

**Als** System  
**möchte ich** Benutzer automatisch per E-Mail erinnern, wenn eine KPI-Eintragung fällig ist  
**damit** die Eintragungen zuverlässig erfolgen.

**Akzeptanzkriterien:**
- Erinnerungen: 3 Tage vor, 7 Tage nach, 14 Tage nach Fälligkeit.
- E-Mail enthält Direktlink zum Eingabeformular.
- Nur offene Einträge werden erinnert.

---

## User Story 7 – Eskalation bei fehlender Eintragung

**Als** System  
**möchte ich** Administratoren benachrichtigen, wenn eine KPI nach 21 Tagen noch fehlt  
**damit** sie manuell nachfassen können.

**Akzeptanzkriterien:**
- E-Mail an alle Administratoren mit Nutzername und KPI.
- Gilt nur für KPI-Zeiträume ohne Eintrag.

---

## User Story 8 – KPI-Wertliste und nachträgliche Bearbeitung

**Als** Benutzer  
**möchte ich** meine bisherigen KPI-Werte einsehen, bearbeiten oder löschen können  
**damit** ich Korrekturen vornehmen und meine Daten verwalten kann.

**Akzeptanzkriterien:**
- Übersicht aller Einträge zu einer KPI.
- Bearbeiten und Löschen möglich.
- Änderungen gelten sofort, ohne Protokollierung.

---

## User Story 9 – KPI-Dashboard mit Ampellogik

**Als** Benutzer  
**möchte ich** auf einen Blick sehen, welche meiner KPIs erledigt, bald fällig oder überfällig sind  
**damit** ich schnell reagieren kann.

**Akzeptanzkriterien:**
- Darstellung mit Ampelfarben:
  - Grün: Erledigt
  - Gelb: Fällig in 3 Tagen
  - Rot: Überfällig
- Liste ist filter- oder sortierbar.

---

## User Story 10 – CSV-Export für Benutzer

**Als** Benutzer  
**möchte ich** meine KPIs und zugehörigen Werte als CSV exportieren können  
**damit** ich sie für eigene Auswertungen weiterverwenden kann.

**Akzeptanzkriterien:**
- Export enthält: KPI-Name, Intervall, Zeiträume, Werte.
- Nur eigene Daten werden exportiert.
- Export als .csv-Datei möglich.

---

## User Story 11 – CSV-Export für Administratoren

**Als** Administrator  
**möchte ich** alle KPIs aller Benutzer exportieren können  
**damit** ich übergreifende Auswertungen durchführen kann.

**Akzeptanzkriterien:**
- Export enthält alle Daten aller Benutzer.
- Export als .csv-Datei möglich.
- Nutzerbezug muss erkennbar sein.

---

## User Story 12 – Kommentar bei KPI-Eintragung hinzufügen

**Als** Benutzer  
**möchte ich** bei der Erfassung eines KPI-Werts optional einen Kommentar hinterlegen können  
**damit** ich Kontext oder Erläuterungen mitgeben kann.

**Akzeptanzkriterien:**
- Freitextfeld im Erfassungsformular.
- Kommentar wird gespeichert und angezeigt.

---

## User Story 13 – Datei-Upload bei KPI-Eintragung

**Als** Benutzer  
**möchte ich** beim Eintragen eines KPI-Wertes eine Datei anhängen können  
**damit** ich Nachweise oder Screenshots direkt mitliefern kann.

**Akzeptanzkriterien:**
- Upload-Feld für Datei (PDF, Bild, Excel etc.).
- Datei wird gespeichert und mit Eintrag verknüpft.
- Datei kann heruntergeladen werden.

---

## User Story 14 – Passwort ändern

**Als** Benutzer  
**möchte ich** mein Passwort ändern können  
**damit** ich meinen Account sicher halten kann.

**Akzeptanzkriterien:**
- Möglichkeit zum Passwortwechsel im eingeloggten Zustand.
- Validierung der Eingaben.
- Bestätigung bei erfolgreicher Änderung.

---
## User story 15: - Benutzer per Shell anlegen

**Als** Administrator möcht eich neue Benutzer per Symfony Shell Command anlegen können und das Passwort festlegen können
**damit** ich einfach neue User anlegen kann unda uch einen ersten User erzeugen kann ohne mich anzumelden

**Akzeptanzkriterien:**
- Email Adresse wird validiert ob sie einer gängigen Email enspricht
- Passwort muss 16 Zeichen lang sein