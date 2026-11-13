import type { OrganizersPageContent } from './types'

const cs: OrganizersPageContent = {
  metaTitle: 'Pro pořadatele — zveřejněte akci zdarma',
  metaDescription:
    'Zveřejněte akci zdarma z plakátu, přijímejte přihlášky a prodávejte vstupenky, kontrolujte je QR kódem u vstupu a vložte své akce na vlastní web.',
  hero: {
    eyebrow: 'Pro pořadatele',
    title: 'Vaše akce lidem,',
    titleAccent: ' bez starostí a zdarma',
    lead: 'Zveřejněte akci za minutu z plakátu, sbírejte přihlášky, prodávejte vstupenky a u vstupu je načtěte mobilem. Vše na jednom místě.',
    primary: 'Nahrát plakát',
    secondary: 'Založit účet',
  },
  promises: ['Zveřejnění zdarma', 'Peníze za vstupné jdou na váš účet', 'Bez instalace, stačí mobil'],
  featuresTitle: 'Co všechno zvládnete',
  featuresLead: 'Od pozvánky až po kontrolu u vchodu. Použijte jen to, co právě potřebujete.',
  features: [
    { icon: 'upload', title: 'Akce z plakátu', text: 'Nahrajte plakát nebo vložte text pozvánky. Název, termín, místo i popis vyplníme za vás, vy je jen zkontrolujete.' },
    { icon: 'ticket', title: 'Přihlášky a vstupenky', text: 'Bezplatné rezervace i placené vstupenky s kapacitou a více druhy. Kupující dostane pokyny k úhradě na váš účet.' },
    { icon: 'checkin', title: 'Kontrola QR kódem', text: 'Vstupenky načtete fotoaparátem mobilu. Funguje i bez signálu, skeny se odešlou, až se spojení vrátí.' },
    { icon: 'users', title: 'Seznam účastníků', text: 'Kdo se přihlásil, kdo zaplatil, kdo přišel. Seznam si stáhnete a všem přihlášeným pošlete e-mail jedním klikem.' },
    { icon: 'clipboard', title: 'Dotazník k přihlášce', text: 'Ptejte se na stravu, ubytování nebo velikost trička. Odpovědi uvidíte shrnuté na jednom místě.' },
    { icon: 'question', title: 'Otázky z publika', text: 'Lidé v sále posílají otázky z mobilu, vy je schvalujete a promítáte na plátno.' },
    { icon: 'calendar', title: 'Připomínky a opakované termíny', text: 'Účastníkům připomeneme termín a při změně je upozorníme. Opakovanou akci přidáte jako sérii termínů.' },
    { icon: 'externalLink', title: 'Akce na vašem webu', text: 'Vložte seznam svých akcí na vlastní stránku. Aktualizuje se sám.' },
    { icon: 'settings', title: 'Tým a statistiky', text: 'Pozvěte kolegy jako editory nebo obsluhu vstupu. Uvidíte, kolik lidí si vaše akce prohlédlo a kolik se přihlásilo.' },
  ],
  startTitle: 'Jak začít',
  steps: [
    { title: 'Nahrajte plakát', text: 'Bez registrace uvidíte, co jsme z plakátu vyčetli. Účet si založíte až při uložení.', cta: 'Nahrát plakát', action: 'poster' },
    { title: 'Založte si účet', text: 'Účet i profil pořadatele jsou zdarma. Akce pak přidáváte a spravujete v přehledu.', cta: 'Založit účet', action: 'register' },
    { title: 'Najděte svůj profil', text: 'Mnoho akcí už na portálu je, převzali jsme je z veřejných zdrojů. Najděte svou akci a převezměte profil pořadatele.', cta: 'Hledat svou akci', action: 'events' },
  ],
  claimNote:
    'Profil převezmete tlačítkem „Spravujete tento kanál?“ na stránce pořadatele. Ověříme to přes kontaktní e-mail profilu, nebo žádost posoudíme ručně.',
  faqTitle: 'Časté otázky',
  faq: [
    { q: 'Kolik to stojí?', a: 'Zveřejnění akcí, profil pořadatele, přihlášky i kontrola vstupenek jsou zdarma.' },
    { q: 'Jak dostanu peníze za vstupenky?', a: 'Kupující platí převodem přímo na bankovní účet vaší organizace. Úhradu pak v seznamu označíte a vstupenka se mu zpřístupní.' },
    { q: 'Moje akce už na portálu je. Kdo ji přidal?', a: 'Akce přebíráme z veřejných zdrojů, aby je lidé našli na jednom místě. Když profil převezmete, můžete údaje opravit, doplnit a dostávat přihlášky i otázky přímo vy.' },
    { q: 'Potřebuji na kontrolu vstupenek speciální zařízení?', a: 'Ne. Stačí mobil s fotoaparátem a prohlížečem. Ke vstupu můžete pozvat i pomocníky, kteří uvidí jen kontrolu vstupenek.' },
    { q: 'Mohu akce zobrazit i na svém webu?', a: 'Ano. V nastavení profilu najdete kód, který vložíte na svou stránku, a seznam akcí se tam bude zobrazovat sám.' },
  ],
  cta: {
    title: 'Připraveni zveřejnit další akci?',
    lead: 'První akce je venku za pár minut.',
    primary: 'Nahrát plakát',
    secondary: 'Založit účet',
  },
}

export default cs
