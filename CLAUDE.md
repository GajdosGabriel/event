# Event — pokyny pre Claude

## Frontend (`ui/`)

- Produkčný build (`npm run build` v `ui/`) spúšťa git hook
  `.githooks/pre-commit` pri commite so zmenou v `ui/` a `ui/dist` pridá do
  toho istého commitu. Po úprave frontendu build nespúšťaj sám.
  Dôvod: produkcia sa nasadzuje `git pull`-om a `ui/dist` je verzovaný v gite —
  bez buildu sa nasadí starý frontend.
  Hook treba na každom počítači raz zapnúť: `git config core.hooksPath .githooks`.
