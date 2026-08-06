# Untis Notify

Ein Benachrichtigungsdienst für persönliche Stundenplanänderungen in WebUntis.
Er vergleicht Stundenpläne regelmäßig und sendet bei Ausfall, Vertretung oder
Raumänderung eine E-Mail.

**Live:** [untis-notify.de](https://untis-notify.de)

## Kurzüberblick

Untis Notify ist während meines Schülerpraktikums bei Lindbaum entstanden und
wird seit 2025 als eigenes Projekt weiterentwickelt. Mehrere Mitschülerinnen
und Mitschüler nutzen den Dienst für ihre Stundenpläne.

**Was der Dienst macht:**

- fragt persönliche Stundenpläne regelmäßig ab und speichert den letzten Stand
- erkennt Ausfälle, Vertretungen, Raumänderungen und sonstige Änderungen
- versendet E-Mail-Benachrichtigungen für einen einstellbaren Zeitraum
- erlaubt eigene Fachkürzel für besser lesbare Benachrichtigungen

**Technik:** PHP, MySQL, Cron, WebUntis-Anbindung über HTTP und PHPMailer.

Das Projekt ist auf den tatsächlichen Einsatz an meiner Schule zugeschnitten;
dieses Repository zeigt den Quellcode und die Oberfläche, ist aber kein
generisches, fertig konfigurierbares Produkt.

## Screenshots

### Whitemode:
<div width="1000px">
  <img src="https://github.com/user-attachments/assets/55bd540b-c4f7-45d7-aee6-b9ee6ba45411" width="400px"/>
  <img src="https://github.com/user-attachments/assets/9aa67f9e-83f6-46af-95f1-f4854c78caa2" width="400px"/> 
</div>

### Darkmode:
<div width="1000px">
  <img src="https://github.com/user-attachments/assets/df95b704-dde2-47da-a47b-0acfd36798d4" width="400px"/>
  <img src="https://github.com/user-attachments/assets/454e6331-3f43-40f2-892a-2c628304659f" width="400px"/> 
</div>
