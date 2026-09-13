/** Priemerný polomer Zeme v kilometroch — tá istá konštanta, akú používa SQL filter. */
const EARTH_RADIUS_KM = 6371

export interface Point {
  latitude: number
  longitude: number
}

/**
 * Súradnice miesta ako bod, alebo null, keď ich miesto nemá.
 *
 * Nestačí `Number(value)`: `Number(null)` je 0, takže miesto bez súradníc by
 * skončilo na 0,0 — v Guinejskom zálive.
 */
export function pointOf(
  place: { latitude?: number | string | null; longitude?: number | string | null } | null | undefined,
): Point | null {
  const latitude = toCoordinate(place?.latitude)
  const longitude = toCoordinate(place?.longitude)

  return latitude === null || longitude === null ? null : { latitude, longitude }
}

function toCoordinate(value: number | string | null | undefined): number | null {
  if (value === null || value === undefined || value === '') return null

  const number = Number(value)

  return Number.isFinite(number) ? number : null
}

/**
 * Vzdušná vzdialenosť dvoch bodov v kilometroch (haversine).
 *
 * Tá istá matematika beží aj v SQL pri filtri „v mojom okolí"
 * ([HasCommonFilters::byDistance]). Tu je preto, aby sa vzdialenosť dala
 * ukázať na karte bez toho, aby ju API muselo posielať pre každý riadok —
 * súradnice miesta už v odpovedi sú.
 */
export function distanceKm(from: Point, to: Point): number {
  const dLat = toRadians(to.latitude - from.latitude)
  const dLng = toRadians(to.longitude - from.longitude)

  const a = Math.sin(dLat / 2) ** 2
    + Math.cos(toRadians(from.latitude)) * Math.cos(toRadians(to.latitude)) * Math.sin(dLng / 2) ** 2

  return EARTH_RADIUS_KM * 2 * Math.asin(Math.min(1, Math.sqrt(a)))
}

/**
 * Vzdialenosť pre človeka: pod kilometer v stovkách metrov, do desiatich
 * kilometrov na desatinu, ďalej zaokrúhlene. Presnosť na metre by pri polohe
 * z prehliadača (a mieste zameranom na strede obce) bola predstieraná.
 */
export function formatDistance(km: number, locale: string): string {
  if (km < 1) {
    return `${Math.round(km * 1000 / 100) * 100} m`
  }

  const value = km < 10
    ? new Intl.NumberFormat(locale, { maximumFractionDigits: 1 }).format(km)
    : String(Math.round(km))

  return `${value} km`
}

function toRadians(degrees: number): number {
  return (degrees * Math.PI) / 180
}
