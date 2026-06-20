<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <p v-if="loading" class="text-slate-600">Načítavam…</p>
    <div v-else-if="error" class="show-not-found"><h1>Kanál nenájdený</h1><RouterLink :to="indexRoute">← Späť</RouterLink></div>
    <template v-else-if="canal">
      <div class="mb-4 flex flex-wrap gap-2">
        <RouterLink :to="indexRoute" class="inline-flex rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 no-underline hover:bg-slate-50">← Späť</RouterLink>
        <RouterLink v-if="canal.permissions.update" :to="editRoute" class="inline-flex rounded-md border border-blue-200 px-3 py-1.5 text-sm text-blue-700 no-underline hover:bg-blue-50">Upraviť</RouterLink>
      </div>
      <div class="show-shell">
        <div class="show-card">
          <h1 class="mb-1 text-3xl text-slate-900">{{ canal.name }}</h1>
          <p class="mb-3 font-semibold text-slate-600">{{ canal.status }}</p>
          <div v-if="canal.body" class="text-slate-700" v-html="canal.body" />
        </div>
        <aside>
          <dl class="show-card grid gap-3">
            <div v-if="canal.email" class="detail-card"><dt>Email</dt><dd>{{ canal.email }}</dd></div>
            <div v-if="canal.website" class="detail-card"><dt>Web</dt><dd><a :href="canal.website" target="_blank" class="text-blue-700">{{ canal.website }}</a></dd></div>
            <div class="detail-card"><dt>Vytvorené</dt><dd>{{ formatDate(canal.createdAt) }}</dd></div>
          </dl>
        </aside>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { showCanal } from '@/api/canals'
import type { CanalItem } from '@/types'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute()
const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const indexRoute = computed(() => `${prefix.value}/canals`)
const editRoute = computed(() => `${prefix.value}/canals/${route.params.id}/edit`)

const canal = ref<CanalItem | null>(null)
const loading = ref(false)
const error = ref(false)

function formatDate(d: string) { return new Date(d).toLocaleDateString('sk-SK') }

onMounted(async () => {
  loading.value = true
  try { canal.value = await showCanal(scope.value, Number(route.params.id)) }
  catch { error.value = true }
  finally { loading.value = false }
})
</script>
