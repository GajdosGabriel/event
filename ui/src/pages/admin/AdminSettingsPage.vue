<template>
  <div class="grid gap-6">
    <h1 class="text-2xl font-semibold text-slate-900">{{ t('admin.settings.title') }}</h1>

    <!-- Stránkovanie -->
    <section class="panel-card grid gap-4">
      <h2 class="text-base font-semibold text-slate-700 border-b border-slate-100 pb-2">{{ t('admin.settings.paging') }}</h2>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <FormField v-model="draft.eventsPerPage" type="select" :label="t('admin.settings.eventsAdmin')" :options="perPageOptions" />
        <FormField v-model="draft.venuesPerPage" type="select" :label="t('admin.settings.venuesAdmin')" :options="perPageOptions" />
        <FormField v-model="draft.canalsPerPage" type="select" :label="t('admin.settings.canalsAdmin')" :options="perPageOptions" />
        <FormField v-model="draft.publicEventsPerPage" type="select" :label="t('admin.settings.eventsPublic')" :options="perPageOptions" />
      </div>
      <div class="flex items-center gap-3">
        <button class="btn btn-primary" @click="saveSettings">{{ t('admin.settings.save') }}</button>
        <button class="btn btn-secondary" @click="resetSettings">{{ t('admin.settings.reset') }}</button>
        <span v-if="saved" class="text-sm text-green-600">{{ t('admin.settings.saved') }}</span>
      </div>
    </section>

    <!-- Organizácie -->
    <section>
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-base font-semibold text-slate-700">{{ t('admin.settings.orgsTitle') }}</h2>
        <RouterLink to="/admin/organizations" class="text-sm text-blue-700 no-underline">{{ t('admin.settings.orgsManage') }}</RouterLink>
      </div>
      <p v-if="loading" class="text-slate-600">{{ t('admin.settings.loading') }}</p>
      <div v-else class="panel-card">
        <ul class="grid gap-2">
          <li v-for="org in orgs" :key="org.id" class="flex items-center gap-3 rounded-lg border border-slate-200 p-3">
            <span class="flex-1 font-medium text-slate-900">{{ org.title }}</span>
            <span v-if="org.accountUuid" class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">{{ t('admin.settings.inAccount') }}</span>
            <span class="text-xs text-slate-500">{{ org.status }}</span>
            <!-- Úprava vrátane fakturačných údajov je na vlastnej stránke;
                 modál by musel duplikovať celý formulár aj napojenie na Account. -->
            <RouterLink :to="`/admin/organizations/${org.id}/edit`" class="action-btn">{{ t('admin.settings.edit') }}</RouterLink>
            <button class="action-btn action-btn-danger" @click="remove(org.id)">{{ t('admin.settings.remove') }}</button>
          </li>
          <li v-if="orgs.length === 0" class="text-slate-500">{{ t('admin.settings.orgsEmpty') }}</li>
        </ul>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { listOrganizations, deleteOrganization } from '@/api/organizations'
import type { OrganizationItem } from '@/types'
import { t } from '@/i18n'
import { useToast } from '@/composables/useToast'
import { useSettings, PER_PAGE_OPTIONS } from '@/composables/useSettings'
import FormField from '@/components/FormField.vue'

const perPageOptions = PER_PAGE_OPTIONS.map(n => ({ value: n, label: t('admin.settings.perPage', { n }) }))

const toast = useToast()
const { settings, save: persistSettings, reset: resetToDefaults } = useSettings()

// Local draft — only applied on save
const draft = reactive({ ...settings.value })

const saved = ref(false)
let savedTimer: ReturnType<typeof setTimeout>

function saveSettings() {
  Object.assign(settings.value, draft)
  persistSettings()
  saved.value = true
  clearTimeout(savedTimer)
  savedTimer = setTimeout(() => { saved.value = false }, 2000)
}

function resetSettings() {
  resetToDefaults()
  Object.assign(draft, settings.value)
  persistSettings()
  saved.value = true
  clearTimeout(savedTimer)
  savedTimer = setTimeout(() => { saved.value = false }, 2000)
}

// Organizations
const orgs = ref<OrganizationItem[]>([])
const loading = ref(false)

onMounted(async () => {
  loading.value = true
  // Bez ohlásenia chyby by zlyhané načítanie vyzeralo ako prázdny zoznam
  // („Žiadne organizácie."), čo je nerozoznateľné od skutočne prázdneho stavu.
  try { orgs.value = (await listOrganizations('admin')).data }
  catch { toast.error(t('admin.settings.orgsLoadFailed')) }
  finally { loading.value = false }
})

async function remove(id: number) {
  if (!confirm(t('admin.settings.removeConfirm'))) return
  try {
    await deleteOrganization('admin', id)
    orgs.value = orgs.value.filter(o => o.id !== id)
    toast.success(t('admin.settings.removed'))
  } catch {
    toast.error(t('admin.settings.removeFailed'))
  }
}
</script>
