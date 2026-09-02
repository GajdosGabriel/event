<template>
  <div class="edit-shell">
    <div class="edit-card">
      <RouterLink :to="detailRoute" class="text-sm text-blue-700 no-underline">{{ t('admin.user.backToDetail') }}</RouterLink>

      <p v-if="loading" class="mt-4 text-slate-600">{{ t('admin.user.loading') }}</p>
      <p v-else-if="loadError" class="mt-4 text-red-600">{{ loadError }}</p>

      <template v-else-if="user">
        <div class="mb-1 mt-2 flex flex-wrap items-center gap-3">
          <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-sm font-semibold text-white"
            :class="user.deleted_at ? 'bg-slate-400' : avatarColor(name)">
            {{ initials(name) }}
          </span>
          <h1 class="text-3xl font-semibold tracking-tight text-slate-900">{{ name }}</h1>
          <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
            :class="statusOf(user).cls">
            <span class="h-1.5 w-1.5 rounded-full" :class="statusOf(user).dot"></span>
            {{ statusOf(user).label }}
          </span>
        </div>
        <p class="text-sm text-slate-500">{{ t('admin.user.editLead') }}</p>

        <p v-if="serverError" ref="errorBanner" class="mt-2 text-red-600">{{ serverError }}</p>

        <!-- Vlastný účet backend odmietne (users.errors.self_update), tak sa
             formulár ani neponúka — profil je na to inde. -->
        <div v-if="isSelf" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
          {{ t('admin.user.selfEdit') }}
        </div>
        <div v-else-if="user.deleted_at" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
          {{ t('admin.user.deletedNote') }}
        </div>

        <form class="mt-4 grid gap-4" @submit.prevent="submit">
          <FormSection :title="t('admin.user.account')" :note="form.email" default-open
            :force-open="!!errors['email'] || !!errors['status'] || !!errors['password']">
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
              <FormField
                v-model="form.email"
                type="email"
                required
                :label="t('admin.user.email')"
                :error="errors['email']"
                :hint="t('admin.user.emailHint')"
                :disabled="readonlyForm"
                class="lg:col-span-2"
              />

              <FormField
                v-model="form.email_verified"
                type="checkbox"
                :label="t('admin.user.emailVerified')"
                :hint="verifiedHint"
                :disabled="readonlyForm"
                class="lg:col-span-2"
              />

              <FormField v-model="form.status" type="select" :label="t('admin.user.status')"
                :error="errors['status']" :disabled="readonlyForm">
                <option v-for="option in allowedStatuses" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </FormField>

              <FormField v-model="form.canal_id" type="select" :label="t('admin.user.personalCanal')"
                :error="errors['canal_id']" :hint="t('admin.user.personalCanalHint')" :disabled="readonlyForm">
                <option :value="null">{{ t('admin.user.personalCanalNone') }}</option>
                <option v-for="c in canals" :key="c.id" :value="c.id">{{ c.name }}</option>
              </FormField>

              <FormField
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                :label="t('admin.user.password')"
                :error="errors['password']"
                :hint="t('admin.user.passwordHint')"
                :disabled="readonlyForm"
                class="lg:col-span-2"
              />
            </div>
          </FormSection>

          <FormSection :title="t('admin.user.roles')" :note="rolesNote">
            <div class="grid gap-2 sm:grid-cols-2">
              <label v-for="role in assignableRoles" :key="role.name" class="form-check">
                <input v-model="selectedRoles" type="checkbox" class="form-checkbox" :value="role.name"
                  :disabled="readonlyForm" />
                <span>{{ role.label ?? roleLabel(role.name, roles) }}</span>
              </label>
            </div>
            <!-- Bez roly by účet stratil aj prístup k vlastnému dashboardu,
                 preto to backend odmieta — povedzme to skôr, než sa uloží
                 zvyšok formulára. -->
            <p v-if="rolesEmpty" class="field-error mt-2">{{ t('admin.user.rolesRequired') }}</p>
          </FormSection>

          <FormSection :title="t('admin.user.blocking')" :note="blockNote"
            :force-open="!!errors['blocked_until'] || !!errors['blocked_reason']">
            <FormField v-model="form.blocked" type="checkbox" :label="t('admin.user.block')" :disabled="readonlyForm" />

            <div v-if="form.blocked" class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
              <FormField v-model="form.blocked_reason" type="textarea" rows="2"
                :placeholder="t('admin.user.blockReasonPlaceholder')" :error="errors['blocked_reason']"
                :disabled="readonlyForm">
                <template #label>
                  {{ t('admin.user.blockReason') }}
                  <span class="font-normal text-slate-400">{{ t('admin.user.optional') }}</span>
                </template>
              </FormField>
              <FormField v-model="form.blocked_until" type="datetime" allow-past
                :hint="t('admin.user.blockUntilHint')" :error="errors['blocked_until']" :disabled="readonlyForm">
                <template #label>
                  {{ t('admin.user.blockUntil') }}
                  <span class="font-normal text-slate-400">{{ t('admin.user.optional') }}</span>
                </template>
              </FormField>
            </div>
          </FormSection>

          <!-- Audit: kedy sa účet založil, prihlásil, odsúhlasil podmienky.
               Zapisuje to systém, preto sa tu len ukazuje. -->
          <FormSection :title="t('admin.user.readonly')" :note="t('admin.user.readonlyHint')">
            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
              <div v-for="row in auditRows" :key="row.label">
                <dt class="text-xs text-slate-400">{{ row.label }}</dt>
                <dd class="break-words text-sm text-slate-800" :title="row.title">{{ row.value }}</dd>
              </div>
            </dl>
          </FormSection>

          <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="btn btn-primary btn-lg" :disabled="saving || readonlyForm">
              {{ saving ? t('admin.user.saving') : t('admin.user.save') }}
            </button>
            <RouterLink :to="detailRoute" class="btn btn-secondary">{{ t('admin.user.cancel') }}</RouterLink>
          </div>
        </form>

        <FormSection :title="t('admin.user.danger')" class="mt-6">
          <button v-if="user.deleted_at" type="button" class="btn btn-secondary" :disabled="savingDelete"
            @click="restore">
            {{ savingDelete ? t('admin.user.saving') : t('admin.user.restore') }}
          </button>
          <template v-else>
            <p v-if="isSelf" class="text-sm text-slate-400">{{ t('admin.user.deleteSelf') }}</p>
            <button v-else type="button" class="btn btn-danger" :disabled="savingDelete" @click="remove">
              {{ savingDelete ? t('admin.user.removing') : t('admin.user.remove') }}
            </button>
          </template>
        </FormSection>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  showUser, getRoles, updateUser, updateUserRoles, deleteUser, restoreUser,
  type UpdateUserPayload,
} from '@/api/access-control'
import type { AccessRole } from '@/types'
import { t } from '@/i18n'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { provideFormValidation } from '@/composables/useFormValidation'
import { scrollToError } from '@/utils/scrollToError'
import { fmtDate } from '@/utils/dateFormat'
import FormField from '@/components/FormField.vue'
import FormSection from '@/components/FormSection.vue'
import {
  displayName, initials, avatarColor, roleLabel, statusOf, providerMeta, relTime, fullDate,
} from '@/utils/userDisplay'

const SCOPE = 'admin' as const

const route = useRoute()
const router = useRouter()
const toast = useToast()
const auth = useAuthStore()
const validation = provideFormValidation()
const errorBanner = ref<HTMLElement | null>(null)

const userId = computed(() => Number(route.params['id']))
const detailRoute = computed(() => `/admin/users/${userId.value}`)

const user = ref<Record<string, unknown> | null>(null)
const roles = ref<AccessRole[]>([])
const selectedRoles = ref<string[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)
const serverError = ref<string | null>(null)
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const savingDelete = ref(false)

/** Prázdny formulár do prvého načítania — polia sa naň viažu hneď od začiatku. */
const form = ref({
  email: '',
  email_verified: false,
  status: 'published',
  canal_id: null as number | null,
  password: '',
  blocked: false,
  blocked_reason: '',
  blocked_until: '',
})

const isSelf = computed(() => !!user.value && Number(user.value.id) === Number(auth.identity?.id))
// Vlastný účet aj zmazaný účet backend na úpravu nepustí — formulár sa tak
// aspoň nesnaží uložiť niečo, čo skončí chybou.
const readonlyForm = computed(() => isSelf.value || !!user.value?.deleted_at)

const name = computed(() => (user.value ? displayName(user.value) : ''))
const canals = computed(() => (user.value?.canals as { id: number; name: string }[]) ?? [])
const roleNames = computed(() => (user.value?.roles as string[]) ?? [])

type StatusOption = { value: string; label: string }
const allowedStatuses = computed<StatusOption[]>(() => {
  const fromApi = (user.value?.allowed_statuses as StatusOption[]) ?? []
  if (fromApi.length) return fromApi

  // Záloha pre prípad, že by číselník neprišiel — inak by select ostal prázdny
  // a stav by sa nedal nastaviť späť.
  const current = String(form.value.status)
  return [{ value: current, label: String(user.value?.status_label ?? current) }]
})

/**
 * Role, ktoré sa naozaj dajú prepínať.
 *
 * `canal-*` sem nepatria — tie vyplývajú z členstva v kanáli, spravuje ich
 * CanalMembership a UserResource ich medzi rolami účtu vôbec nehlási. Ako
 * políčka by teda ostali navždy odškrtnuté a nikto by netušil, že ich ten
 * účet má. (Backend si ich pri sync-u dopĺňa sám, takže sa neposielajú.)
 */
const assignableRoles = computed(() => roles.value.filter(r => !r.name.startsWith('canal-')))

const rolesNote = computed(() =>
  selectedRoles.value.length
    ? selectedRoles.value.map(r => roleLabel(r, roles.value)).join(', ')
    : '—',
)

const blockNote = computed(() => {
  if (!form.value.blocked) return t('users.statuses.active')
  return form.value.blocked_until
    ? t('admin.user.blockedUntil', { date: fullDate(form.value.blocked_until) })
    : t('admin.user.blockedForever')
})

const verifiedHint = computed(() => {
  const at = user.value?.email_verified_at
  return at ? `${t('admin.user.emailVerifiedHint')} (${fullDate(at)})` : t('admin.user.emailVerifiedHint')
})

const auditRows = computed(() => {
  const u = user.value ?? {}
  const terms = u.terms_accepted_at
    ? `${fmtDate(u.terms_accepted_at as string)} (${t('admin.user.termsVersion')} ${u.terms_version || '—'})`
    : '—'

  return [
    { label: t('admin.user.id'), value: String(u.id ?? '—'), title: '' },
    { label: t('admin.user.uuid'), value: String(u.uuid ?? '—'), title: '' },
    { label: t('admin.user.registeredVia'), value: providerMeta(u.registered_via as string).label, title: '' },
    { label: t('admin.user.createdAt'), value: u.created_at ? fmtDate(u.created_at as string) : '—', title: fullDate(u.created_at) },
    // Kvôli tomuto sa formulár otvára — kedy sa účet naposledy prihlásil.
    { label: t('admin.user.lastLogin'), value: relTime(u.last_login_at), title: fullDate(u.last_login_at) },
    { label: t('admin.user.lastActivity'), value: relTime(u.last_activity ?? u.last_login_at), title: fullDate(u.last_activity ?? u.last_login_at) },
    { label: t('admin.user.updatedAt'), value: u.updated_at ? fmtDate(u.updated_at as string) : '—', title: fullDate(u.updated_at) },
    { label: t('admin.user.blockedAt'), value: u.blocked_at ? fmtDate(u.blocked_at as string) : '—', title: fullDate(u.blocked_at) },
    { label: t('admin.user.termsAccepted'), value: terms, title: fullDate(u.terms_accepted_at) },
  ]
})

function fill(data: Record<string, unknown>) {
  user.value = data
  selectedRoles.value = [...((data.roles as string[]) ?? [])]
  form.value = {
    email: String(data.email ?? ''),
    email_verified: data.email_verified === true,
    status: String(data.status ?? 'published'),
    canal_id: data.canal_id == null ? null : Number(data.canal_id),
    password: '',
    blocked: data.is_blocked === true,
    blocked_reason: String(data.blocked_reason ?? ''),
    blocked_until: toLocalInput(data.blocked_until),
  }
}

/** ISO z API na tvar `<input type="datetime-local">` — ako inde vo formulároch. */
function toLocalInput(value: unknown): string {
  return value ? String(value).slice(0, 16) : ''
}

onMounted(async () => {
  try {
    const [data, roleList] = await Promise.all([showUser(userId.value, SCOPE), getRoles(SCOPE)])
    roles.value = roleList
    fill(data)
  } catch {
    loadError.value = t('admin.user.loadFailed')
  } finally {
    loading.value = false
  }
})

const rolesChanged = computed(() => {
  const a = [...selectedRoles.value].sort().join(',')
  const b = [...roleNames.value].sort().join(',')
  return a !== b
})

/**
 * Odškrtnuté všetko, čo účet mal — backend to odmietne (aspoň jedna rola).
 *
 * Účet, ktorý žiadnu priraditeľnú rolu nemá ani teraz (má len tie z členstva
 * v kanáli), sem nepatrí: role sa vtedy vôbec neposielajú a zvyšok formulára
 * sa uložiť dá.
 */
const rolesEmpty = computed(() => rolesChanged.value && selectedRoles.value.length === 0)

async function submit() {
  validation.markValidated()
  errors.value = {}
  serverError.value = null

  // Profil sa ukladá pred rolami, takže by prázdny zoznam rolí skončil
  // polovičnou zmenou — radšej vôbec neposielať.
  if (rolesEmpty.value) {
    serverError.value = t('admin.user.rolesRequired')
    await scrollToError(errorBanner)
    return
  }

  saving.value = true

  const payload: UpdateUserPayload = {
    email: form.value.email.trim(),
    status: form.value.status,
    email_verified: form.value.email_verified,
    canal_id: form.value.canal_id,
    blocked: form.value.blocked,
    blocked_reason: form.value.blocked ? (form.value.blocked_reason.trim() || null) : null,
    blocked_until: form.value.blocked ? (form.value.blocked_until || null) : null,
  }

  // Prázdne heslo sa neposiela vôbec — inak by ho validátor riešil ako pokus
  // nastaviť príliš krátke heslo.
  if (form.value.password) payload.password = form.value.password

  try {
    const updated = await updateUser(userId.value, payload, SCOPE)

    // Role idú vlastným endpointom (spatie), preto až po uložení účtu a len
    // keď sa naozaj zmenili.
    if (rolesChanged.value) {
      await updateUserRoles(userId.value, selectedRoles.value, SCOPE)
      updated.roles = [...selectedRoles.value]
    }

    fill(updated)
    toast.success(t('admin.user.saved'))
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    if (resp?.errors) errors.value = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    serverError.value = resp?.message ?? t('admin.user.saveFailed')
    await scrollToError(errorBanner)
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!user.value) return
  if (!confirm(t('admin.user.removeConfirm', { name: name.value }))) return

  savingDelete.value = true
  try {
    await deleteUser(userId.value, SCOPE)
    toast.success(t('admin.user.removed'))
    router.push('/admin/users')
  } catch { toast.error(t('admin.user.removeFailed')) }
  finally { savingDelete.value = false }
}

async function restore() {
  savingDelete.value = true
  try {
    await restoreUser(userId.value, SCOPE)
    if (user.value) user.value.deleted_at = null
    toast.success(t('admin.user.restored'))
  } catch { toast.error(t('admin.user.restoreFailed')) }
  finally { savingDelete.value = false }
}
</script>
