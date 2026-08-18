/**
 * Trvalá anonymná identita prehliadača pre nástenku otázok.
 *
 * Používa sa na dve veci:
 *
 * - **hlasovanie** — server podľa nej pozná, že tento prehliadač už za otázku
 *   hlasoval, takže sa dá hlas odobrať. Zámerne nie IP: hlas musí prežiť
 *   prepnutie wifi na mobilné dáta.
 * - **„moje otázky"** — pri zapnutom moderovaní vlastná otázka na verejnom
 *   zozname nie je, a človek by inak po obnovení stránky nemal ako zistiť, že
 *   ju vôbec poslal.
 *
 * Nie je to prihlásenie a ani sa tak netvári — vyčistením úložiska sa stratí.
 */

const ID_KEY = 'questions_anon_id'
const VOTES_KEY = 'questions_votes'
const MINE_KEY = 'questions_mine'

function read(key: string): string[] {
  try {
    const raw = JSON.parse(localStorage.getItem(key) ?? '[]')

    return Array.isArray(raw) ? raw.map(String) : []
  } catch {
    return []
  }
}

function write(key: string, values: string[]): void {
  try {
    // Zoznam sa orezáva — po roku používania by inak rástol donekonečna.
    localStorage.setItem(key, JSON.stringify(values.slice(-500)))
  } catch {
    // Súkromné okno so zakázaným úložiskom nesmie zhodiť odosielanie otázky.
  }
}

export function useAnonId() {
  function anonId(): string {
    let id = localStorage.getItem(ID_KEY)

    if (!id) {
      id = crypto.randomUUID().replace(/-/g, '')

      try {
        localStorage.setItem(ID_KEY, id)
      } catch {
        // Bez úložiska hlasovanie funguje, len sa po obnovení stránky
        // nepamätá — server duplicitu aj tak nepustí.
      }
    }

    return id
  }

  const key = (token: string, id: number) => `${token}:${id}`

  return {
    anonId,

    hasVoted: (token: string, id: number) => read(VOTES_KEY).includes(key(token, id)),
    rememberVote: (token: string, id: number) => write(VOTES_KEY, [...read(VOTES_KEY), key(token, id)]),
    forgetVote: (token: string, id: number) =>
      write(VOTES_KEY, read(VOTES_KEY).filter((v) => v !== key(token, id))),

    isMine: (token: string, id: number) => read(MINE_KEY).includes(key(token, id)),
    rememberMine: (token: string, id: number) => write(MINE_KEY, [...read(MINE_KEY), key(token, id)]),
    myIds: (token: string) =>
      read(MINE_KEY)
        .filter((v) => v.startsWith(`${token}:`))
        .map((v) => Number(v.split(':')[1])),
  }
}
