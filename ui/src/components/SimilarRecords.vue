<template>
  <div v-if="items.length" class="rounded-lg bg-amber-50 p-3 text-sm lg:col-span-2" role="status">
    <p>{{ t('roadmap.similar') }}</p>
    <ul>
      <li v-for="item in items" :key="item.id">
        <!-- Cudzie zverejnené miesto sa v dashboarde neotvorí — vedie na verejnú stránku. -->
        <RouterLink :to="item.own === false ? `/${resource}/${item.id}` : `/${scope}/${resource}/${item.id}`" :target="newTab || item.own === false ? '_blank' : undefined">{{ item.name }}</RouterLink>
        <span v-if="item.street" class="text-gray-600"> · {{ item.street }}</span>
        <span v-if="item.own === false" class="text-gray-600"> · {{ t('roadmap.similarForeign') }}</span>
      </li>
    </ul>
    <p v-if="items.some(item => item.own === false)" class="mt-1 text-gray-700">{{ t('roadmap.similarForeignHint') }}</p>
  </div>
</template>
<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue'
import http from '@/api/index'
import { useI18n } from '@/i18n'
const props = defineProps<{ scope: string; resource: string; name: string; municipality?: number | null; street?: string | null; excludeId?: number | null; newTab?: boolean }>()
const { t } = useI18n()
const items = ref<{ id: number; name: string; street?: string | null; own?: boolean }[]>([])
let version = 0
let timer: ReturnType<typeof setTimeout> | undefined
watch(() => [props.name, props.municipality, props.street, props.excludeId, props.scope, props.resource], () => {
  const id = ++version
  clearTimeout(timer)
  items.value = []
  if (props.name.trim().length < 2) return
  timer = setTimeout(async () => {
    try {
      const { data } = await http.get(`/${props.scope}/${props.resource}/similar`, { params: { name: props.name, municipality: props.municipality, street: props.street || undefined, exclude_id: props.excludeId } })
      if (id === version) items.value = data.data
    } catch { /* Advice must never prevent saving. */ }
  }, 300)
}, { immediate: true })
onUnmounted(() => { ++version; clearTimeout(timer) })
</script>
