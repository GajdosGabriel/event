<template>
  <div class="grid gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold text-slate-900">{{ t('organizations.list.title') }}</h1>
      <RouterLink :to="`${prefix}/organizations/create`" class="btn btn-primary">{{ t('organizations.list.create') }}</RouterLink>
    </div>

    <p class="text-sm text-slate-500">{{ t('organizations.list.lead') }}</p>

    <p v-if="loading" class="text-slate-600">{{ t('common.loading') }}</p>
    <p v-else-if="loadError" class="text-red-600">{{ loadError }}</p>

    <div v-else class="panel-card">
      <ul class="grid gap-2">
        <li v-for="org in orgs" :key="org.id"
          class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 p-3">
          <span class="flex-1 min-w-40 font-medium text-slate-900">{{ org.title }}</span>

          <span class="text-xs text-slate-500">{{ statusLabel('organizations', org.status) }}</span>

          <!-- Komu firma patrí, sa pozná podľa kanálov — ľudia visia na nich,
               nie na firme. Detail ukáže aj mená a role. -->
          <span class="rounded-full px-2 py-0.5 text-xs"
            :class="org.canalsCount > 0 ? 'bg-slate-100 text-slate-600' : 'bg-amber-50 text-amber-700'">
            {{ canalsLabel(org.canalsCount) }}
          </span>

          <span v-if="org.accountUuid"
            class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
            {{ t('organizations.list.inAccount') }}
          </span>
          <span v-else class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">
            {{ t('organizations.list.localOnly') }}
          </span>

          <RowActions>
            <RouterLink :to="`${prefix}/organizations/${org.id}/edit`" class="row-menu-item">{{ t('common.edit') }}</RouterLink>
            <button class="row-menu-item row-menu-item-danger" @click="remove(org.id)">{{ t('common.remove') }}</button>
          </RowActions>
        </li>
        <li v-if="orgs.length === 0" class="text-slate-500">{{ t('organizations.list.empty') }}</li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { listOrganizations, deleteOrganization } from '@/api/organizations'
import RowActions from '@/components/RowActions.vue'
import type { OrganizationItem } from '@/types'
import { useToast } from '@/composables/useToast'
import { useI18n } from '@/i18n'
import { statusLabel } from '@/utils/statusLabel'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute(); const toast = useToast()
const { t, plural } = useI18n()
const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')

const orgs = ref<OrganizationItem[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)

/** Firma bez kanála nemá za koho fakturovať — preto to nie je len číslo. */
function canalsLabel(count: number) {
  return count === 0 ? t('organizations.list.noCanal') : plural('organizations.counts.canals', count)
}

onMounted(async () => {
  try {
    orgs.value = (await listOrganizations(scope.value)).data
  } catch {
    // Bez hlášky by zlyhané načítanie vyzeralo ako prázdny zoznam, čo je
    // nerozoznateľné od skutočne prázdneho stavu.
    loadError.value = t('organizations.list.loadFailed')
  } finally {
    loading.value = false
  }
})

async function remove(id: number) {
  if (!confirm(t('organizations.list.removeConfirm'))) return
  try {
    await deleteOrganization(scope.value, id)
    orgs.value = orgs.value.filter(o => o.id !== id)
    toast.success(t('common.removed'))
  } catch {
    toast.error(t('common.removeFailed'))
  }
}
</script>
