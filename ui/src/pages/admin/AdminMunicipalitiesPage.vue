<template>
  <div class="grid gap-4">
    <div class="flex items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold text-slate-900">{{ t('municipalities.title') }}</h1>
    </div>

    <!-- Obce sú číselník na čítanie: API pre ne nemá zápisové operácie
         (Admin\MunicipalityController pozná len index/all/show), takže
         zakladanie, úprava ani mazanie sa tu neponúkajú — tlačidlá by
         skončili chybou servera. Zoznam sa plní importom. -->
    <input v-model="search" type="text" :placeholder="t('filters.search')" class="form-input w-56" @input="onSearch" />

    <p v-if="loading" class="text-slate-600">{{ t('municipalities.loading') }}</p>

    <div v-else class="panel-card">
      <!-- Široká tabuľka sa posúva vo vlastnom rámčeku — na telefóne inak
           roztiahne celý dashboard a posúva sa stránka. -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
              <th class="pb-2 pr-4">{{ t('municipalities.colId') }}</th>
              <th class="pb-2 pr-4">{{ t('municipalities.colName') }}</th>
              <th class="pb-2">{{ t('municipalities.colZip') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id" class="border-b border-slate-100 last:border-0">
              <td class="py-2 pr-4 text-slate-400">{{ item.id }}</td>
              <td class="py-2 pr-4 font-medium text-slate-900">{{ item.shortname ?? '—' }}</td>
              <td class="py-2 text-slate-600">{{ item.zip ?? '—' }}</td>
            </tr>
            <tr v-if="items.length === 0">
              <td colspan="3" class="py-4 text-slate-500">{{ t('municipalities.empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="meta.last_page > 1" class="mt-4 flex items-center gap-2">
        <button class="btn btn-secondary" :disabled="meta.current_page <= 1" @click="loadPage(meta.current_page - 1)">‹</button>
        <span class="text-sm text-slate-600">{{ meta.current_page }} / {{ meta.last_page }}</span>
        <button class="btn btn-secondary" :disabled="meta.current_page >= meta.last_page" @click="loadPage(meta.current_page + 1)">›</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { indexMunicipalities } from '@/api/municipalities'
import type { MunicipalityItem } from '@/types'
import { useToast } from '@/composables/useToast'
import { useI18n } from '@/i18n'

const SCOPE = 'admin' as const

const { t } = useI18n()
const toast = useToast()
const items = ref<MunicipalityItem[]>([])
const loading = ref(false)
const search = ref('')
const meta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
let searchTimer: ReturnType<typeof setTimeout> | null = null

onMounted(() => loadPage(1))

async function loadPage(page: number) {
  loading.value = true
  try {
    const res = await indexMunicipalities(SCOPE, { page, search: search.value || undefined })
    items.value = res.data
    meta.value = res.meta
  } catch {
    toast.error(t('municipalities.loadFailed'))
  } finally {
    loading.value = false
  }
}

function onSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadPage(1), 400)
}
</script>
