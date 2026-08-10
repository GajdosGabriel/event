import http from './index'
import { mapAttributeIssues } from './attributeIssues'
import type {
  IcoLookupResult,
  OrganizationAccountData,
  OrganizationAccountForm,
  OrganizationCanal,
  OrganizationItem,
  PaginatedResponse,
} from '@/types'

type Scope = 'dashboard' | 'admin'

function base(scope: Scope) {
  return `/${scope}/organizations`
}

function mapOrg(raw: Record<string, unknown>): OrganizationItem {
  return {
    id: raw['id'] as number,
    title: (raw['title'] as string) ?? '',
    person: Boolean(raw['person']),
    slug: (raw['slug'] as string) ?? '',
    description: (raw['description'] as string) ?? null,
    website: (raw['website'] as string) ?? null,
    email: (raw['email'] as string) ?? null,
    attributeIssues: mapAttributeIssues(raw['attribute_issues']),
    phone: (raw['phone'] as string) ?? null,
    villageId: (raw['village_id'] as number) ?? null,
    status: (raw['status'] as OrganizationItem['status']) ?? 'draft',
    published: Boolean(raw['published']),
    // Väzba na Account. `account` je vyplnené len v detaile — v zozname by to
    // znamenalo jedno HTTP volanie na riadok.
    accountUuid: (raw['account_uuid'] as string) ?? null,
    accountSyncedAt: (raw['account_synced_at'] as string) ?? null,
    account: (raw['account'] as OrganizationAccountData) ?? null,
    // Kanály nesie len detail — výpis vracia iba počet, inak by to bol
    // dotaz na riadok.
    canalsCount: (raw['canals_count'] as number) ?? 0,
    canals: mapCanals(raw['canals']),
    deletedAt: (raw['deleted_at'] as string) ?? null,
    createdAt: (raw['created_at'] as string) ?? '',
    updatedAt: (raw['updated_at'] as string) ?? '',
  }
}

function mapCanals(raw: unknown): OrganizationCanal[] {
  if (!Array.isArray(raw)) return []

  return (raw as Record<string, unknown>[]).map(canal => ({
    id: canal['id'] as number,
    name: (canal['name'] as string) ?? '',
    status: (canal['status'] as OrganizationCanal['status']) ?? 'draft',
    identityMode: (canal['identity_mode'] as string) ?? null,
    members: Array.isArray(canal['members'])
      ? (canal['members'] as Record<string, unknown>[]).map(member => ({
          id: member['id'] as number,
          name: (member['name'] as string) ?? '',
          role: (member['role'] as string) ?? null,
          isOwner: Boolean(member['is_owner']),
        }))
      : [],
  }))
}

export async function listOrganizations(scope: Scope): Promise<PaginatedResponse<OrganizationItem>> {
  const { data } = await http.get(base(scope))
  const items = (data.data ?? data) as Record<string, unknown>[]
  return { data: items.map(mapOrg), meta: data.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: items.length } }
}

export async function showOrganization(scope: Scope, id: number): Promise<OrganizationItem> {
  const { data } = await http.get(`${base(scope)}/${id}`)
  return mapOrg((data.data ?? data) as Record<string, unknown>)
}

export async function createOrganization(scope: Scope, payload: Record<string, unknown>): Promise<OrganizationItem> {
  const { data } = await http.post(base(scope), payload)
  return mapOrg((data.data ?? data) as Record<string, unknown>)
}

export async function updateOrganization(scope: Scope, id: number, payload: Record<string, unknown>): Promise<OrganizationItem> {
  const { data } = await http.put(`${base(scope)}/${id}`, payload)
  return mapOrg((data.data ?? data) as Record<string, unknown>)
}

export async function deleteOrganization(scope: Scope, id: number): Promise<void> {
  await http.delete(`${base(scope)}/${id}`)
}

export async function restoreOrganization(scope: Scope, id: number): Promise<void> {
  await http.post(`${base(scope)}/${id}/restore`)
}

/**
 * Väzba firma ↔ kanál.
 *
 * Ľudia sa k firme nepriraďujú priamo — členom sa je v kanáli
 * (`canals/{id}/team`) a firma je len jeho fakturačná strecha. Preto sa tu
 * pripája a odpája kanál, nie používateľ.
 */
export async function attachCanalToOrganization(scope: Scope, id: number, canalId: number): Promise<void> {
  await http.post(`${base(scope)}/${id}/canals`, { canal_id: canalId })
}

export async function detachCanalFromOrganization(scope: Scope, id: number, canalId: number): Promise<void> {
  await http.delete(`${base(scope)}/${id}/canals/${canalId}`)
}

/**
 * Vyhľadanie firmy v obchodnom registri podľa IČO.
 * Register nevolá Event priamo — ide to cez Account, ktorý rieši
 * aj slovenské RPO, aj české ARES.
 */
export async function lookupIco(scope: Scope, ico: string, country = 'sk'): Promise<IcoLookupResult> {
  const { data } = await http.post(`${base(scope)}/lookup-ico`, { ico, country })
  return data as IcoLookupResult
}

/**
 * Fakturačné údaje z Accountu → do plochého formulára.
 * Account ich vracia zoskupené (identifiers, address, bank…), formulár
 * ich potrebuje po jednom.
 */
export function accountToForm(account: OrganizationAccountData | null): OrganizationAccountForm {
  return {
    legal_name: account?.legal_name ?? '',
    legal_form: account?.legal_form ?? '',
    ico: account?.identifiers?.ico ?? '',
    dic: account?.identifiers?.dic ?? '',
    ic_dph: account?.identifiers?.ic_dph ?? '',
    vat_mode: account?.identifiers?.vat_mode ?? '',
    register_court: account?.registration?.court ?? '',
    register_section: account?.registration?.section ?? '',
    register_insert: account?.registration?.insert ?? '',
    // Account vracia sídlo s číslom už spojeným do jedného riadka, preto má
    // aj formulár jedno pole „Ulica a číslo“ — inak by sa číslo po prvej
    // úprave duplikovalo do ulice.
    street: account?.address?.street ?? '',
    city: account?.address?.city ?? '',
    postal_code: account?.address?.postal_code ?? '',
    country: account?.address?.country ?? 'SK',
    email: account?.contact?.email ?? '',
    billing_email: account?.contact?.billing_email ?? '',
    phone: account?.contact?.phone ?? '',
    website: account?.contact?.website ?? '',
    bank_name: account?.bank?.name ?? '',
    iban: account?.bank?.iban ?? '',
    swift: account?.bank?.swift ?? '',
  }
}
