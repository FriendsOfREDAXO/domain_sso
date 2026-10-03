# Domain SSO

Einmal im Backend anmelden – und auf **allen Domains** einer REDAXO-Installation angemeldet sein.

Betreibt eine Installation mehrere Websites über YRewrite (z. B. `hotel-a.de`, `hotel-b.de`, `gruppe.de`), hält der Browser die Anmeldung je Domain getrennt: Wer sich auf `hotel-a.de/redaxo` anmeldet, ist auf `hotel-b.de` nicht angemeldet. Vorschau von Offline-Seiten, Bearbeiten-Links im Frontend oder Werkzeuge, die eine Backend-Sitzung voraussetzen, funktionieren dann nur auf der einen Domain.

Domain SSO teilt die Anmeldung automatisch – ohne Drittanbieter-Cookies, ohne Änderungen am Core.

## So funktioniert es

1. Sie melden sich wie gewohnt an, auf einer beliebigen Domain.
2. Beim nächsten Seitenaufruf im Backend erzeugt Domain SSO für jede weitere Domain ein **Einmal-Ticket** und leitet den Browser kurz über diese Domains und wieder zurück. Das dauert in der Regel unter einer Sekunde.
3. Jede Domain löst ihr Ticket ein und legt eine eigene, reguläre Backend-Sitzung an – sie erscheint auch im Profil unter „Sitzungen“.
4. **Abmelden** auf einer Domain beendet die Sitzungen auf allen Domains.
5. Läuft die Sitzung auf einer Domain durch Inaktivität ab, wird sie beim nächsten Backend-Aufruf still neu angelegt.

Weil nur Top-Level-Weiterleitungen genutzt werden (keine iframes, keine Cookies für fremde Domains), funktioniert das auch in Safari und mit blockierten Drittanbieter-Cookies.

## Sicherheit

- Tickets bestehen aus 256 Bit Zufall, werden nur als SHA-256-Hash gespeichert, gelten standardmäßig 60 Sekunden und nur **einmal**.
- Jedes Ticket ist an Benutzer und Ziel-Domain gebunden; das nächste Ziel der Kette steht serverseitig im Ticket (keine offene Weiterleitung).
- Nur YRewrite-Domains dieser Installation und nur **HTTPS** (für lokale Entwicklung abschaltbar).
- Beim Einlösen wird die Sitzungs-ID erneuert (Schutz vor Session Fixation); Rechte und Passwortrichtlinien des Benutzers bleiben unverändert.
- Inaktive Benutzer werden nicht angemeldet, beim „Benutzer wechseln“ (Impersonate) wird nichts geteilt.

## Einstellungen

Unter **Domain SSO → Einstellungen** (nur Admins):

- **Anmeldung auf allen Domains teilen** – ein/aus
- **Teilnehmende Domains** – keine Auswahl = alle YRewrite-Domains
- **Gültigkeit der Tickets** – 10 bis 600 Sekunden
- **Auch ohne HTTPS erlauben** – nur für lokale Entwicklung

Darunter zeigt **Ihre Sitzungen**, auf welchen Domains Sie gerade angemeldet sind.

## Info Center

Ist das AddOn [info_center](https://github.com/klxm/info_center) installiert, bringt Domain SSO dort ein Widget **Domains** mit – im Backend und im Frontend (für angemeldete Redakteure):

- alle teilnehmenden Domains mit Link zur **Website** und zum **Backend** der jeweiligen Domain
- grüner Punkt = dort bereits angemeldet; die aktuelle Domain ist markiert
- Ein- und Ausblenden sowie die Reihenfolge wie bei jedem Widget in den Info-Center-Einstellungen

Ohne info_center wird nichts davon geladen.

## Voraussetzungen

- REDAXO ab 5.15, YRewrite ab 2.10, PHP ab 8.1
- Alle Domains in **einer** REDAXO-Installation (YRewrite), mit gültigem HTTPS-Zertifikat
- Frontend und Backend teilen sich die PHP-Sitzung (REDAXO-Standard)
- Hinter einem Proxy (z. B. Nginx vor Apache) muss HTTPS erkannt werden (`X-Forwarded-Proto` auswerten), sonst gilt die Verbindung als unverschlüsselt

## Hinweise

- Die Kette läuft höchstens einmal pro Minute und nie bei Formularen, Ajax-, PJAX- oder API-Anfragen.
- Sie startet nur, wenn das Backend über eine teilnehmende Domain aufgerufen wird.
- Ist eine Domain nicht erreichbar (z. B. fehlendes Zertifikat), nehmen Sie sie in den Einstellungen aus.

## Lizenz

MIT – siehe [LICENSE](LICENSE)
