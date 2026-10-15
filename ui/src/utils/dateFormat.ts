import { localeTag } from '@/i18n'

/**
 * Názvy dní a mesiacov berieme z Intl, nie zo slovníka — sú to jediné reťazce,
 * ktoré vie prehliadač preložiť sám a v každom zo štyroch jazykov správne
 * skloniť. Do slovníka patrí to, čo sme napísali my.
 *
 * Intl vracia deň malým písmenom (sk, cs), preto prvé písmeno zväčšujeme —
 * v UI stoja názvy dní na začiatku riadku.
 */
function capitalize(s: string): string {
  return s ? s[0].toUpperCase() + s.slice(1) : s
}

export function dayName(d: string): string {
  const date = new Date(d)
  if (Number.isNaN(date.getTime())) return ''
  return capitalize(date.toLocaleDateString(localeTag(), { weekday: 'long' }))
}

/**
 * „Dnes" / „Zajtra" pre najbližšie dva dni, inak null. Z Intl, aby sa to
 * preložilo samo vo všetkých jazykoch rovnako ako názvy dní.
 */
export function relativeDayName(d: string, now: Date = new Date()): string | null {
  const date = new Date(d)
  if (Number.isNaN(date.getTime())) return null
  const startOf = (x: Date) => new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime()
  const diff = Math.round((startOf(date) - startOf(now)) / 86_400_000)
  if (diff !== 0 && diff !== 1) return null
  return capitalize(new Intl.RelativeTimeFormat(localeTag(), { numeric: 'auto' }).format(diff, 'day'))
}

/** Poradie zodpovedá kľúčom otváracích hodín; pondelok je prvý deň týždňa. */
export const WEEKDAY_KEYS = [
  'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
] as const

export type WeekdayKey = (typeof WEEKDAY_KEYS)[number]

/**
 * Názov dňa pre kľúč otváracích hodín („monday" → „Pondelok"). Referenčný
 * týždeň je ľubovoľný — 1. 6. 2026 je pondelok — a formátuje sa v UTC, aby
 * posun zóny neposunul aj deň.
 */
export function weekdayLabel(key: string): string {
  const idx = WEEKDAY_KEYS.indexOf(key as WeekdayKey)
  if (idx < 0) return key
  const ref = new Date(Date.UTC(2026, 5, 1 + idx))
  return capitalize(ref.toLocaleDateString(localeTag(), { weekday: 'long', timeZone: 'UTC' }))
}

export function fmtDate(d: string): string {
  return new Date(d).toLocaleDateString(localeTag(), { day: 'numeric', month: 'numeric', year: 'numeric' })
}

export function fmtTime(d: string): string {
  return new Date(d).toLocaleTimeString(localeTag(), { hour: '2-digit', minute: '2-digit' })
}

/**
 * Kalendárny lístok na karte a v hlavičke detailu: „29" nad „sep". Skratky sú
 * z Intl bez bodky, aby sa zmestili do úzkeho stĺpca.
 */
export function dateTile(d: string): { day: string; month: string; weekday: string } {
  const date = new Date(d)
  return {
    day: String(date.getDate()),
    month: date.toLocaleDateString(localeTag(), { month: 'short' }).replace('.', ''),
    weekday: date.toLocaleDateString(localeTag(), { weekday: 'short' }).replace('.', ''),
  }
}

/** „22. júla 2026" — dlhý tvar do textových viet (uzávierka registrácie). */
export function fmtDateLong(d: string): string {
  return new Date(d).toLocaleDateString(localeTag(), { day: 'numeric', month: 'long', year: 'numeric' })
}

/** Padnú oba časy do toho istého kalendárneho dňa? */
export function isSameDay(a: string, b: string): boolean {
  return new Date(a).toDateString() === new Date(b).toDateString()
}

/** „24. 10." — dátum bez roka, keď rok vyplýva zo súvislosti. */
export function fmtDayMonth(d: string): string {
  return new Date(d).toLocaleDateString(localeTag(), { day: 'numeric', month: 'numeric' })
}

/**
 * Celodenné podujatie. Importy ho zapisujú ako 00:00 – 23:59 (niekedy do
 * polnoci ďalšieho dňa) — časy by vo výpise len hovorili „od polnoci do polnoci".
 * Zrkadlí App\Support\EventDateRange::isAllDay().
 */
export function isAllDay(start: string, end: string | null): boolean {
  if (!end) return false
  const s = new Date(start)
  const e = new Date(end)
  if (Number.isNaN(s.getTime()) || Number.isNaN(e.getTime())) return false
  if (s.getHours() !== 0 || s.getMinutes() !== 0) return false
  if (e.getHours() === 23 && e.getMinutes() === 59) return true
  return e.getHours() === 0 && e.getMinutes() === 0 && e > s
}

/**
 * Posledný deň podujatia — koniec o polnoci patrí ešte predchádzajúcemu dňu,
 * inak by celodenná akcia „do 00:00" vyzerala ako dvojdňová.
 */
function lastDayOf(end: string): Date {
  const e = new Date(end)
  return e.getHours() === 0 && e.getMinutes() === 0 ? new Date(e.getTime() - 60_000) : e
}

/**
 * Plný termín, keď ho API neposlalo (detail podujatia): „15. 10. 2026, 17:00 – 19:00",
 * celodenné len „16. 10. 2026", viacdňové „16. 10. 2026 – 24. 10. 2026".
 */
export function fmtEventRange(start: string | null, end: string | null): string | null {
  if (!start) return null
  if (end && isAllDay(start, end)) {
    const last = lastDayOf(end).toISOString()
    return isSameDay(start, last) ? fmtDate(start) : `${fmtDate(start)} – ${fmtDate(last)}`
  }
  const label = `${fmtDate(start)}, ${fmtTime(start)}`
  if (!end || end === start) return label
  return isSameDay(start, end) ? `${label} – ${fmtTime(end)}` : `${label} – ${fmtDate(end)}, ${fmtTime(end)}`
}

/**
 * Čas do riadku agendy, kde dátum už nesie nadpis dňa: „17:00 – 19:00",
 * „Celý deň", pri viacdňovom „17:00 · do 24. 10.". Texty dodá volajúci (i18n).
 */
export function fmtAgendaTime(
  start: string,
  end: string | null,
  labels: { allDay: string; until: (date: string) => string },
): string {
  const allDay = isAllDay(start, end)
  const multiDay = Boolean(end) && !isSameDay(start, lastDayOf(end!).toISOString())
  const time = allDay ? labels.allDay : fmtTime(start)
  if (multiDay) return `${time} · ${labels.until(fmtDayMonth(lastDayOf(end!).toISOString()))}`
  if (allDay || !end || end === start) return time
  return `${time} – ${fmtTime(end)}`
}

/**
 * Celé dni do začiatku, zaokrúhlené nahor a nikdy záporné. Používa sa na
 * odpočet pri uzávierke registrácie; 0 znamená „dnes je posledný deň".
 */
export function daysUntil(d: string, now: Date = new Date()): number {
  const target = new Date(d)
  if (Number.isNaN(target.getTime())) return 0
  return Math.max(0, Math.ceil((target.getTime() - now.getTime()) / 86_400_000))
}

/**
 * „12. 8. 2026, 18:00 → 22:00" — kompaktný termín do riadku výpisu.
 * Pri viacdňovom evente sa na pravej strane zopakuje aj dátum.
 */
export function fmtRowDateRange(start: string | null, end: string | null): string | null {
  if (!start) return null
  const label = `${fmtDate(start)}, ${fmtTime(start)}`
  if (!end) return label
  return `${label} → ${isSameDay(start, end) ? fmtTime(end) : `${fmtDate(end)}, ${fmtTime(end)}`}`
}

/**
 * V akej fáze je podujatie voči „teraz". Odvodzuje sa z termínu, nie zo
 * `status` — ten hovorí len o publikovaní, takže dávno skončený a budúci
 * event vyzerajú vo výpise rovnako.
 *
 * Nadchádzajúci je predvolený stav a vracia `null` — vo výpise by bol šum.
 */
export function eventTimeState(
  start: string | null,
  end: string | null,
  now: Date = new Date(),
): 'ongoing' | 'past' | null {
  if (!start) return null
  const startsAt = new Date(start).getTime()
  const endsAt = end ? new Date(end).getTime() : startsAt
  if (Number.isNaN(startsAt) || Number.isNaN(endsAt)) return null
  const ms = now.getTime()
  if (ms > endsAt) return 'past'
  if (ms >= startsAt) return 'ongoing'
  return null
}

/** „Streda 1. 8. 2026 10:00–12:00" — termín workshopu. */
export function fmtDayTimeRange(start: string | null, end: string | null): string {
  if (!start) return ''
  const label = `${dayName(start)} ${fmtDate(start)} ${fmtTime(start)}`
  return end ? `${label}–${fmtTime(end)}` : label
}

/**
 * Hodnota pre `<input type="datetime-local">` z času z API. API posiela okamih
 * v UTC („2026-10-05T16:00:00.000000Z"), no organizátor zadával aj číta
 * slovenský nástenný čas — preto sa prepočíta, nie odreže (`slice`).
 * Hodnota bez zóny sa berie ako už lokálna.
 */
export function toLocalInput(iso: string | null | undefined, timeZone = 'Europe/Bratislava'): string {
  if (!iso) return ''
  if (!/(Z|[+-]\d{2}:?\d{2})$/i.test(iso)) return iso.slice(0, 16)
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat('en-CA', {
      timeZone, hourCycle: 'h23',
      year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit',
    }).formatToParts(date).map(p => [p.type, p.value]),
  )
  return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`
}
