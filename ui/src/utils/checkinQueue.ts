/**
 * Fronta skenov, ktoré sa nepodarilo odoslať.
 *
 * Check-in beží v sále, v pivnici klubu alebo na lúke — teda tam, kde signál
 * vypadáva. Doteraz bol každý sken jeden HTTP request a pri výpadku sa pri
 * vchode jednoducho stálo. Fronta to mení na „naskenuj teraz, odošli, keď to
 * pôjde".
 *
 * Prehratie je bezpečné, lebo check-in na serveri je idempotentný: druhý sken
 * tej istej vstupenky nič neprepíše a vráti `already_checked_in`
 * ([EloquentTicketRepository::checkIn]). Preto sa záznam z fronty maže až po
 * úspešnej odpovedi — radšej pošleme dvakrát než ani raz.
 *
 * IndexedDB, nie localStorage: fronta musí prežiť aj zavretie karty a pád
 * prehliadača, a zápis do localStorage je synchrónny — pri sérii skenov by
 * blokoval vlákno, ktoré práve dekóduje obraz z kamery.
 */

const DB_NAME = 'event-checkin'
const DB_VERSION = 1
const STORE = 'queue'

export interface QueuedScan {
  /** `${eventId}:${token}` — dvakrát naskenovaná tá istá vstupenka je jeden záznam. */
  id: string
  eventId: number
  token: string
  /** Kedy sa naozaj skenovalo. Server to zapíše ako čas príchodu. */
  scannedAt: string
}

function open(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION)

    request.onupgradeneeded = () => {
      const db = request.result
      if (!db.objectStoreNames.contains(STORE)) {
        db.createObjectStore(STORE, { keyPath: 'id' })
      }
    }

    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
  })
}

function transact<T>(mode: IDBTransactionMode, run: (store: IDBObjectStore) => IDBRequest<T>): Promise<T> {
  return open().then((db) => new Promise<T>((resolve, reject) => {
    const tx = db.transaction(STORE, mode)
    const request = run(tx.objectStore(STORE))

    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
    tx.oncomplete = () => db.close()
  }))
}

/**
 * Zaradí sken do fronty. Ten istý token na tom istom podujatí prepíše
 * existujúci záznam — kto priloží kód dvakrát, nemá naplniť frontu dvakrát.
 */
export async function enqueue(eventId: number, token: string): Promise<void> {
  const scan: QueuedScan = {
    id: `${eventId}:${token}`,
    eventId,
    token,
    scannedAt: new Date().toISOString(),
  }

  await transact('readwrite', (store) => store.put(scan))
}

/** Skeny čakajúce na odoslanie pre dané podujatie, v poradí, v akom vznikli. */
export async function pending(eventId: number): Promise<QueuedScan[]> {
  const all = await transact<QueuedScan[]>('readonly', (store) => store.getAll() as IDBRequest<QueuedScan[]>)

  return all
    .filter((scan) => scan.eventId === eventId)
    .sort((a, b) => a.scannedAt.localeCompare(b.scannedAt))
}

export async function remove(id: string): Promise<void> {
  await transact('readwrite', (store) => store.delete(id))
}

/** Je IndexedDB k dispozícii? V privátnom režime niektorých prehliadačov nie. */
export function isSupported(): boolean {
  try {
    return typeof indexedDB !== 'undefined'
  } catch {
    return false
  }
}
