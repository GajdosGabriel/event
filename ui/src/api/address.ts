import http from './index'
import type { AddressModel, CoordinatesSource } from '@/types'

type Scope = 'dashboard' | 'admin'

export interface AddressGeocodeResult {
  latitude: number | null
  longitude: number | null
  /** 'address' = presná adresa, 'municipality' = stred obce, null = nenájdené. */
  source: CoordinatesSource | null
  city: string | null
  postcode: string | null
}

/**
 * Poloha z rozpísanej adresy. Mapa v editore skočí na obec hneď po jej výbere
 * a spresní sa, keď pribudne ulica s číslom — spoločné pre miesto aj kanál.
 */
export async function geocodeAddress(
  scope: Scope,
  payload: { municipality_id: number; street?: string | null; postcode?: string | null; country?: string | null },
): Promise<AddressGeocodeResult> {
  const { data } = await http.post(`/${scope}/geocode`, payload)
  return data as AddressGeocodeResult
}

/** Prázdna adresa — východiskový stav formulára. */
export function emptyAddress(): AddressModel {
  return {
    municipalityId: null,
    street: '',
    postcode: '',
    country: '',
    latitude: null,
    longitude: null,
    coordinatesSource: null,
  }
}

/**
 * Adresa z odpovede API. Obec chodí ako `villageId` (miesto) alebo
 * `municipalityId` (kanál) — do formulára ide vždy pod jedným menom.
 */
export function addressFrom(record: {
  villageId?: number | null
  municipalityId?: number | null
  street?: string | null
  postcode?: string | null
  country?: string | null
  latitude?: number | null
  longitude?: number | null
  coordinatesSource?: CoordinatesSource | null
}): AddressModel {
  return {
    municipalityId: record.villageId ?? record.municipalityId ?? null,
    street: record.street ?? '',
    postcode: record.postcode ?? '',
    country: record.country ?? '',
    latitude: record.latitude ?? null,
    longitude: record.longitude ?? null,
    coordinatesSource: record.coordinatesSource ?? null,
  }
}

/**
 * Adresa do tela požiadavky. `municipalityKey` je názov stĺpca obce v cieľovej
 * tabuľke — `village_id` pri mieste, `municipality_id` pri kanáli.
 */
export function toAddressPayload(
  address: AddressModel,
  municipalityKey: 'village_id' | 'municipality_id',
): Record<string, unknown> {
  return {
    [municipalityKey]: address.municipalityId,
    street: address.street,
    postcode: address.postcode,
    country: address.country,
    latitude: address.latitude,
    longitude: address.longitude,
    coordinates_source: address.coordinatesSource,
  }
}
