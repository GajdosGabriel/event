import { fmtDate } from '@/utils/dateFormat'
import type { AccessRole } from '@/types'

export type UserLike = Record<string, unknown>

export function displayName(user: UserLike): string {
  return (user.display_name as string) || (user.email as string) || 'Neznámy'
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

export const ROLE_LABELS: Record<string, string> = {
  'super-admin': 'Super admin',
  'admin': 'Administrátor',
  'canal-owner': 'Vlastník kanálu',
  'editor': 'Editor',
  'moderator': 'Moderátor',
  'user': 'Používateľ',
}
export function roleLabel(role: string, rolesMeta: AccessRole[] = []): string {
  return rolesMeta.find(r => r.name === role)?.label ?? ROLE_LABELS[role] ?? role
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

export const STATUS_META: Record<StatusKey, { label: string; cls: string; dot: string }> = {
  deleted:    { label: 'Zmazaný',   cls: 'bg-slate-100 text-slate-500 ring-slate-200',      dot: 'bg-slate-400' },
  blocked:    { label: 'Blokovaný', cls: 'bg-red-50 text-red-700 ring-red-200',             dot: 'bg-red-500' },
  unverified: { label: 'Neoverený', cls: 'bg-amber-50 text-amber-700 ring-amber-200',       dot: 'bg-amber-500' },
  active:     { label: 'Aktívny',   cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' },
}

export function statusOf(user: UserLike): { label: string; cls: string; dot: string } {
  return STATUS_META[statusKey(user)]
}

export function providerMeta(via?: string): { icon: string; label: string } {
  switch (via) {
    case 'google':   return { icon: '🟢', label: 'Google' }
    case 'facebook': return { icon: '🔵', label: 'Facebook' }
    case 'email':    return { icon: '✉️', label: 'Email' }
    default:         return { icon: '👤', label: via || 'Priama' }
  }
}

export function relTime(value: unknown): string {
  if (!value) return 'nikdy'
  const then = new Date(value as string).getTime()
  if (Number.isNaN(then)) return '—'
  const diff = Date.now() - then
  const min = Math.round(diff / 60000)
  if (min < 1) return 'práve teraz'
  if (min < 60) return `pred ${min} min`
  const hrs = Math.round(min / 60)
  if (hrs < 24) return `pred ${hrs} h`
  const days = Math.round(hrs / 24)
  if (days < 30) return `pred ${days} d`
  return fmtDate(value as string)
}

export function fullDate(value: unknown): string {
  if (!value) return ''
  return new Date(value as string).toLocaleString('sk-SK')
}

export function pluralUsers(n: number): string {
  if (n === 1) return 'používateľ'
  if (n >= 2 && n <= 4) return 'používatelia'
  return 'používateľov'
}
