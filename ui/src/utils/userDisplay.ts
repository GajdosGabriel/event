import { fmtDate } from '@/utils/dateFormat'
import { t, plural, localeTag, type MessageKey } from '@/i18n'
import type { AccessRole } from '@/types'

export type UserLike = Record<string, unknown>

export function displayName(user: UserLike): string {
  return (user.display_name as string) || (user.email as string) || t('users.unknown')
}

/**
 * „Gabriel Gajdoš" → „Gajdoš Gabriel". V zoznamoch sa hľadá podľa priezviska,
 * tak nech je vpredu. Jednoslovné meno (prezývka, e-mailový login) ostáva tak,
 * ako je.
 */
export function surnameFirst(name: string | null | undefined): string {
  const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean)

  if (parts.length < 2) return parts[0] ?? ''

  return [parts[parts.length - 1], ...parts.slice(0, -1)].join(' ')
}

export function initials(name: string): string {
  const parts = name.replace(/[^\p{L}\p{N} ]/gu, '').trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return '?'
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

const AVATAR_COLORS = [
  'bg-rose-500', 'bg-orange-500', 'bg-amber-500', 'bg-emerald-500',
  'bg-teal-500', 'bg-sky-500', 'bg-indigo-500', 'bg-violet-500', 'bg-fuchsia-500',
]
export function avatarColor(seed: string): string {
  let h = 0
  for (let i = 0; i < seed.length; i++) h = (h * 31 + seed.charCodeAt(i)) >>> 0
  return AVATAR_COLORS[h % AVATAR_COLORS.length]
}

// Názvy rolí posiela aj API (v `rolesMeta`); slovník je záloha pre miesta,
// kde zoznam rolí nemáme po ruke, a pre role, ktoré API nepozná.
const ROLE_KEYS: Record<string, MessageKey> = {
  'super-admin': 'users.roles.superAdmin',
  'admin': 'users.roles.admin',
  'canal-owner': 'users.roles.canalOwner',
  'editor': 'users.roles.editor',
  'moderator': 'users.roles.moderator',
  'user': 'users.roles.user',
}

export function roleLabel(role: string, rolesMeta: AccessRole[] = []): string {
  const fromApi = rolesMeta.find(r => r.name === role)?.label
  if (fromApi) return fromApi

  const key = ROLE_KEYS[role]
  return key ? t(key) : role
}

export function roleClass(role: string): string {
  switch (role) {
    case 'super-admin': return 'bg-purple-50 text-purple-700 ring-purple-200'
    case 'admin':       return 'bg-red-50 text-red-700 ring-red-200'
    case 'canal-owner': return 'bg-amber-50 text-amber-700 ring-amber-200'
    case 'editor':
    case 'moderator':   return 'bg-sky-50 text-sky-700 ring-sky-200'
    default:            return 'bg-slate-100 text-slate-600 ring-slate-200'
  }
}

export type StatusKey = 'deleted' | 'blocked' | 'unverified' | 'active'

export function statusKey(user: UserLike): StatusKey {
  if (user.deleted_at) return 'deleted'
  if (user.is_blocked) return 'blocked'
  if (user.email_verified === false) return 'unverified'
  return 'active'
}

const STATUS_STYLES: Record<StatusKey, { cls: string; dot: string }> = {
  deleted:    { cls: 'bg-slate-100 text-slate-500 ring-slate-200',      dot: 'bg-slate-400' },
  blocked:    { cls: 'bg-red-50 text-red-700 ring-red-200',             dot: 'bg-red-500' },
  unverified: { cls: 'bg-amber-50 text-amber-700 ring-amber-200',       dot: 'bg-amber-500' },
  active:     { cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' },
}

export function statusOf(user: UserLike): { label: string; cls: string; dot: string } {
  const key = statusKey(user)
  return { label: t(`users.statuses.${key}` as MessageKey), ...STATUS_STYLES[key] }
}

// Ikony k hodnotám `users.registered_via`. Popisky sú v slovníku pod
// `users.providers.*` — hodnoty, ktoré tu nie sú, ostávajú tak, ako prišli.
const PROVIDER_ICONS: Record<string, string> = {
  local: '✉️',
  email: '✉️',
  google: '🟢',
  facebook: '🔵',
  ticket: '🎟️',
  message: '💬',
}

export function providerMeta(via?: string): { icon: string; label: string } {
  if (!via) return { icon: '👤', label: t('users.providerDirect') }

  const key = `users.providers.${via}` as MessageKey
  const label = t(key)

  return {
    icon: PROVIDER_ICONS[via] ?? '👤',
    // `t()` vracia pri neznámom kľúči samotný kľúč — vtedy radšej holú hodnotu.
    label: label === key ? via : label,
  }
}

export function relTime(value: unknown): string {
  if (!value) return t('common.rel.never')
  const then = new Date(value as string).getTime()
  if (Number.isNaN(then)) return '—'
  const diff = Date.now() - then
  const min = Math.round(diff / 60000)
  if (min < 1) return t('common.rel.justNow')
  if (min < 60) return t('common.rel.minutes', { n: min })
  const hrs = Math.round(min / 60)
  if (hrs < 24) return t('common.rel.hours', { n: hrs })
  const days = Math.round(hrs / 24)
  if (days < 30) return t('common.rel.days', { n: days })
  return fmtDate(value as string)
}

export function fullDate(value: unknown): string {
  if (!value) return ''
  return new Date(value as string).toLocaleString(localeTag())
}

export function pluralUsers(n: number): string {
  return plural('users.counts.users', n)
}
