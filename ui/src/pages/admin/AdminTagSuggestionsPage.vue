<template>
  <div class="grid gap-4">
    <div class="flex items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold text-slate-900">{{ t('tagSuggestions.title') }}</h1>
    </div>

    <p class="max-w-3xl text-sm text-slate-600">{{ t('tagSuggestions.intro') }}</p>

    <div class="flex flex-wrap gap-2">
      <button
        v-for="option in filters"
        :key="option"
        class="btn"
        :class="filter === option ? 'btn-primary' : 'btn-secondary'"
        @click="setFilter(option)"
      >
        {{ t(`tagSuggestions.filter.${option}`) }}
      </button>
    </div>

    <p v-if="loading" class="text-slate-600">{{ t('tagSuggestions.loading') }}</p>

    <div v-else class="panel-card">
      <!-- Široká tabuľka sa posúva vo vlastnom rámčeku — na telefóne inak
           roztiahne celý dashboard a posúva sa stránka. -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
              <th class="pb-2 pr-4">{{ t('tagSuggestions.colLabel') }}</th>
              <th class="pb-2 pr-4">{{ t('tagSuggestions.colOccurrences') }}</th>
              <th class="pb-2 pr-4">{{ t('tagSuggestions.colLastSeen') }}</th>
              <th class="pb-2">{{ t('tagSuggestions.colActions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id" class="border-b border-slate-100 last:border-0">
              <td class="py-2 pr-4">
                <span class="font-medium text-slate-900">{{ item.label }}</span>
                <span class="ml-2 text-xs text-slate-400">{{ item.slug }}</span>
              </td>
              <!-- Počet výskytov je jediné poradie, ktoré tu dáva zmysel:
                   výraz zo stovky podujatí je kandidát na štítok, výraz
                   z jedného je preklep. -->
              <td class="py-2 pr-4 font-semibold text-slate-900">{{ item.occurrences }}</td>
              <td class="py-2 pr-4 text-slate-600">{{ formatDate(item.lastSeenAt) }}</td>
              <td class="py-2">
                <div v-if="item.resolution === null" class="flex gap-2">
                  <button class="btn btn-secondary" :disabled="saving === item.id" @click="resolve(item, 'promoted')">
                    {{ t('tagSuggestions.promote') }}
                  </button>
                  <button class="btn btn-secondary" :disabled="saving === item.id" @click="resolve(item, 'rejected')">
                    {{ t('tagSuggestions.reject') }}
                  </button>
                </div>
                <span v-else class="text-slate-500">{{ t(`tagSuggestions.filter.${item.resolution}`) }}</span>
              </td>
            </tr>
            <tr v-if="items.length === 0">
              <td colspan="4" class="py-4 text-slate-500">{{ t('tagSuggestions.empty') }}</td>
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
import { indexTagSuggestions, resolveTagSuggestion } from '@/api/tagSuggestions'
import type { TagSuggestionFilter, TagSuggestionItem, TagSuggestionResolution } from '@/api/tagSuggestions'
import { useToast } from '@/composables/useToast'
import { useI18n } from '@/i18n'

const { t } = useI18n()
const toast = useToast()

const filters: TagSuggestionFilter[] = ['unresolved', 'promoted', 'rejected']
const filter = ref<TagSuggestionFilter>('unresolved')
const items = ref<TagSuggestionItem[]>([])
const loading = ref(false)
const saving = ref<number | null>(null)
const meta = ref({ current_page: 1, last_page: 1, per_page: 50, total: 0 })

onMounted(() => loadPage(1))

async function loadPage(page: number) {
  loading.value = true
  try {
    const res = await indexTagSuggestions({ resolution: filter.value, page })
    items.value = res.data
    meta.value = res.meta
  } catch {
    toast.error(t('tagSuggestions.loadFailed'))
  } finally {
    loading.value = false
  }
}

function setFilter(next: TagSuggestionFilter) {
  if (filter.value === next) return
  filter.value = next
  loadPage(1)
}

/**
 * Vybavený návrh zo zoznamu nezmizne prekreslením celej stránky, ale
 * odobratím riadku — inak by sa pri každom kliknutí posunulo stránkovanie
 * a človek by stratil miesto, kde bol.
 */
async function resolve(item: TagSuggestionItem, resolution: TagSuggestionResolution) {
  saving.value = item.id
  try {
    await resolveTagSuggestion(item.id, resolution)

    if (filter.value === 'unresolved') {
      items.value = items.value.filter(i => i.id !== item.id)
      meta.value = { ...meta.value, total: Math.max(0, meta.value.total - 1) }
    } else {
      item.resolution = resolution
    }

    toast.success(t('tagSuggestions.resolved'))
  } catch {
    toast.error(t('tagSuggestions.resolveFailed'))
  } finally {
    saving.value = null
  }
}

function formatDate(value: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString()
}
</script>
