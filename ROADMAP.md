# Otvorené body

Hotové z pôvodného plánu: výbery so serverovým vyhľadávaním, varovanie
„Podobné už existuje“ + zlučovanie z menu, nástenka s ďalšími krokmi,
zoznamy bez blikania a s rozlíšeným prázdnym stavom, centrum notifikácií.

## Na zváženie
- **Neuložené zmeny:** upozornenie pri odchode z rozpracovaného formulára
  (EventEdit, CanalEdit, VenueEdit, OrganizationEdit).
- **Vlastné potvrdzovacie okno namiesto `confirm()`** (cca 20 súborov), pri
  mazaní toast s „Späť“ — záznamy sa mažú len soft-delete.
- **Skeleton pri načítaní** editačných stránok Canal, Venue, Organization, Poster.
- **Check-in skener na mobile:** rozloženie pri 375 px, ručné zadanie kódu,
  opakovaný sken a odkaz pre dobrovoľníkov sú overené v emulácii. Kameru
  (povolenie, zaostrenie, sken z displeja) treba vyskúšať na skutočnom
  telefóne — iOS Safari aj Android Chrome — pred prvou akciou.
- `ui/src/components/TagPicker.vue` sa nikde nepoužíva.
