# Event UI

Single-page aplikácia portálu Event. Konzumuje REST API z [`../api`](../api).

**Stack:** Vue 3 (`<script setup>`, Composition API), TypeScript, Vite 6,
Tailwind CSS 4, Pinia, Vue Router, Tiptap (editor), Leaflet (mapy),
qr-scanner (check-in vstupeniek).

## Spustenie

```bash
npm install && npm run dev
```

Dev server beží na `http://localhost:5173` a proxuje `/api`, `/sanctum`,
`/storage` a `/images` na `http://event-api.local` — pozri
[vite.config.ts](vite.config.ts). Backend teda musí bežať súčasne a musí byť
dostupný na tejto doméne, inak SPA nedostane dáta.

## Príkazy

| Príkaz | Čo robí |
|---|---|
| `npm run dev` | dev server s HMR |
| `npm run build` | `vue-tsc -b` + produkčný build do `dist/` |
| `npm run typecheck` | kontrola typov bez buildu (rýchlejšie) |
| `npm run lint` | ESLint |
| `npm run lint:fix` | ESLint s automatickými opravami |
| `npm run preview` | lokálne prezretie produkčného buildu |

## Štruktúra

```
src/
  api/          obálky nad HTTP volaniami, jedna na doménu (events, tickets…)
  components/   znovupoužiteľné komponenty
  composables/  zdieľaná logika (useToast, useSettings, useWindowKeydown…)
  layouts/      rozloženia pre verejnú časť, dashboard a admin
  pages/        stránky mapované v routeri
  stores/       Pinia (auth)
  types/        zdieľané TypeScript typy
```

Centrálny axios klient je v [src/api/index.ts](src/api/index.ts) — rieši
XSRF hlavičku, Bearer token, odhlásenie pri 401 a hlásenie 429 (rate limit).

## Na čo si dať pozor

**`dist/` je verzovaný v gite**, lebo produkcia sa nasadzuje `git pull`-om.
Po zmene frontendu spusti `npm run build` a commitni aj `dist/`, inak sa
nasadí starý build.

Vue **nemá modifikátor `.window`** (to je syntax Alpine.js). Zápis ako
`@keydown.esc.window="close"` sa ticho ignoruje a skratka nefunguje — na
klávesové skratky viazané na okno používaj
[`useWindowKeydown`](src/composables/useWindowKeydown.ts).
