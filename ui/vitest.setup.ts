/**
 * Nové Node (25+) má vlastný experimentálny `localStorage`, ktorý bez
 * `--localstorage-file` vracia `undefined` a prekrýva ten z jsdom. Testy potom
 * padajú už pri importe (`i18n/index.ts` číta `localStorage` hneď).
 * Ak Storage nie je použiteľný, dosadíme jednoduchú pamäťovú náhradu.
 */
function memoryStorage(): Storage {
  const data = new Map<string, string>()
  return {
    get length() {
      return data.size
    },
    clear: () => data.clear(),
    getItem: (key) => data.get(key) ?? null,
    key: (index) => Array.from(data.keys())[index] ?? null,
    removeItem: (key) => void data.delete(key),
    setItem: (key, value) => void data.set(key, String(value)),
  }
}

for (const name of ['localStorage', 'sessionStorage'] as const) {
  let usable = false
  try {
    usable = typeof globalThis[name]?.getItem === 'function'
  } catch {
    usable = false
  }
  if (!usable) {
    Object.defineProperty(globalThis, name, {
      value: memoryStorage(),
      configurable: true,
      writable: true,
    })
  }
}
