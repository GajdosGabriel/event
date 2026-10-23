# Otvorené body

Hotové z pôvodného plánu: výbery so serverovým vyhľadávaním, varovanie
„Podobné už existuje“ + zlučovanie z menu, nástenka s ďalšími krokmi,
zoznamy bez blikania a s rozlíšeným prázdnym stavom, centrum notifikácií.

## Pred spustením do prevádzky
- **Údaje prevádzkovateľa v obchodných podmienkach** — v
  `ui/src/content/legal/index.ts` sú stále `[DOPLNIŤ: …]`.

## Na zváženie
- **Neuložené zmeny:** upozornenie pri odchode z rozpracovaného formulára
  (EventEdit, CanalEdit, VenueEdit, OrganizationEdit).
- **Vlastné potvrdzovacie okno namiesto `confirm()`** (cca 20 súborov), pri
  mazaní toast s „Späť“ — záznamy sa mažú len soft-delete.
- **Skeleton pri načítaní** editačných stránok Canal, Venue, Organization, Poster.
- Otestovať check-in skener a zoznam účastníkov na mobile.
- `ui/src/components/TagPicker.vue` sa nikde nepoužíva.
