# Event — pokyny pre Claude

## Frontend (`ui/`)

- Po každej zmene frontendu (`ui/src`, `ui/public`, konfigurácia Vite/Tailwind)
  spusti produkčný build: `npm run build` v `ui/`. Výsledok v `ui/dist` patrí
  do toho istého commitu ako zmena zdrojákov.
  Dôvod: produkcia sa nasadzuje `git pull`-om a `ui/dist` je verzovaný v gite —
  bez buildu sa nasadí starý frontend.
