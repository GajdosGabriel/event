// Referenčný slovník. Ostatné jazyky sú typované proti nemu (Messages),
// takže chýbajúci alebo preklepnutý kľúč spadne už na typecheck.
const sk = {
  // Názvy jazykov sú zámerne v danom jazyku (endonymá) — v prepínači tak
  // svoj jazyk nájde aj ten, kto práve pozerá na cudzí.
  lang: {
    label: 'Jazyk',
    sk: 'Slovenčina',
    cs: 'Čeština',
    de: 'Deutsch',
    en: 'English',
  },
  nav: {
    // verejná časť
    login: 'Prihlásenie',
    register: 'Registrácia',
    logout: 'Odhlásiť sa',
    public: 'Verejná časť',
    dashboard: 'Dashboard',
    admin: 'Admin',
    superAdmin: 'Super admin',

    // dashboard + admin bočný panel
    events: 'Eventy',
    canals: 'Kanály',
    venues: 'Miesta',
    organizations: 'Organizácie',
    messages: 'Správy',
    municipalities: 'Obce',
    announcements: 'Oznamy',
    users: 'Používatelia',
    files: 'Súbory',
    tools: 'Nástroje',
    settings: 'Nastavenia',

    collapse: 'Zbaliť',
    expand: 'Rozbaliť',
  },
  dashboard: {
    title: 'Dashboard',
    greeting: 'Vitajte, {name}.',
    fallbackName: 'používateľ',
  },
  // Prehľadová štatistika. Čo počíta server (popisky metrík, „vyžaduje
  // pozornosť"), sa prekladá na serveri — tu je len to, čo píše front.
  stats: {
    loading: 'Načítavam štatistiku…',
    loadFailed: 'Štatistiku sa nepodarilo načítať.',
    updated: 'Aktualizované {time}',
    refresh: 'obnoviť',
    noComparison: 'bez porovnania',
    previous: 'predtým {value}',
    meterOf: 'z',
    now: {
      activeEvents: 'Aktívne podujatia',
      running: 'Práve prebieha',
      today: 'Dnes v programe',
      next7d: 'Najbližších 7 dní',
    },
    attention: {
      title: 'Vyžaduje pozornosť',
      info: 'info',
      warning: 'sledovať',
      serious: 'doriešiť',
      critical: 'súrne',
    },
    periods: {
      title: 'Prírastky',
      group: 'Obdobie',
      comparison: 'Porovnanie s rovnako dlhým predchádzajúcim obdobím.',
      fromStart: 'Súhrn od začiatku.',
    },
    activity: {
      title: 'Denná aktivita',
      subtitle: 'Posledných {days} dní · {metric}',
      group: 'Metrika grafu',
      empty: 'Za posledných {days} dní tu zatiaľ nič nepribudlo.',
      byDays: '{metric} po dňoch',
      metrics: {
        views: 'Zobrazenia',
        events: 'Podujatia',
        tickets: 'Objednávky',
        admissions: 'Vstupenky',
        checkins: 'Príchody',
      },
      units: {
        views: 'zobrazení',
        events: 'podujatí',
        tickets: 'objednávok',
        admissions: 'vstupeniek',
        checkins: 'príchodov',
      },
    },
    views: {
      title: 'Návštevnosť',
      subtitle: 'Zobrazenia verejných detailov · jeden návštevník sa za deň ráta raz.',
      events: 'Podujatia',
      venues: 'Miesta',
      canals: 'Kanály',
      averagePrefix: 'Priemerne',
      averageSuffix: 'zobrazení na zverejnené podujatie.',
      conversion: 'Z pozretia na registráciu',
      conversionUnit: 'zobrazení podujatí',
      conversionNote: '{count} vstupeniek',
      empty: 'Zatiaľ žiadne zobrazenia — počítadlo sa napĺňa z verejných stránok.',
    },
    tickets: {
      title: 'Vstupenky',
      subtitle: '{orders} objednávok · {seats} platných vstupeniek',
      occupancy: 'Obsadenosť nadchádzajúcich podujatí',
      occupancyUnit: 'miest',
      unlimitedNote: '{count} typov bez limitu sa neráta',
      attendance: 'Príchody na už prebehnuté podujatia',
      attendanceUnit: 'vstupeniek',
      paid: 'Zaplatené',
      awaitingPayment: 'Čaká na platbu',
      awaitingConfirmation: 'Čaká na potvrdenie',
      cancelled: 'Zrušené vstupenky',
    },
    composition: {
      title: 'Skladba podujatí',
      subtitle: '{events} celkovo · {venues} miest · {canals} kanálov',
      imported: 'Podiel importovaného obsahu',
      importedUnit: 'podujatí',
      ownNote: '{count} vlastných',
      topCanals: 'Najaktívnejšie kanály',
      topCanalsHint: 'za 30 dní / celkovo',
    },
    upcoming: {
      title: 'Najbližší program',
      empty: 'Žiadne naplánované podujatia.',
      noVenue: 'bez miesta',
      today: 'dnes {time}',
      seats: '{count} vstupeniek',
    },
    mostViewed: {
      title: 'Najviac zobrazené',
      empty: 'Zatiaľ žiadne zobrazenia verejných detailov.',
    },
    mostInterest: {
      title: 'Najväčší záujem',
      empty: 'Zatiaľ nikto nie je prihlásený na nadchádzajúce podujatia.',
    },
    users: {
      total: 'Používatelia',
      verified: 'Overené účty',
      active30d: 'Aktívni za 30 dní',
      blocked: 'Blokované účty',
    },
  },
  poster: {
    hero: {
      // Nadpis je rozdelený, lebo druhá časť je farebne zvýraznená — vrátane
      // interpunkcie, ktorá sa medzi jazykmi líši (čiarka vs. pomlčka).
      title: 'Nahrajte plagát',
      titleAccent: ', o všetko ostatné sa postaráme',
      badge: 'Nové',
      stepUpload: 'Nahráte plagát',
      stepReview: 'Ukážeme, čo sme našli',
      stepSave: 'Uložíte podujatie',
      how: 'Ako to funguje?',
      details:
        'Nahrajte PDF, Word, fotku plagátu alebo len text pozvánky. Prečítame z neho termín, miesto aj organizátora, napojíme podujatie na existujúce miesto či kanál a ukážeme vám, čo sme našli a čo treba doplniť. Podujatie hneď zverejníme.',
      cta: 'Nahrať plagát',
      formats: 'PDF, Word, obrázok alebo text',
    },
  },
}

export type Messages = typeof sk

export default sk
