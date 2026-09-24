<template>
  <div v-if="items.length" class="rounded-lg bg-amber-50 p-3 text-sm lg:col-span-2" role="status">
    <p>{{ t('roadmap.similar') }}</p>
    <ul><li v-for="item in items" :key="item.id"><RouterLink :to="`/${scope}/${resource}/${item.id}`">{{ item.name }}</RouterLink></li></ul>
  </div>
</template>
<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue'
import http from '@/api/index'
import { useI18n } from '@/i18n'
const props = defineProps<{ scope: string; resource: string; name: string; municipality?: number | null; excludeId?: number | null }>()
const { t } = useI18n()
const items = ref<{ id: number; name: string }[]>([])
let version = 0
let timer: ReturnType<typeof setTimeout> | undefined
watch(() => [props.name, props.municipality, props.excludeId, props.scope, props.resource], () => {
  const id = ++version
  clearTimeout(timer)
  items.value = []
  if (props.name.trim().length < 2) return
  timer = setTimeout(async () => {
    try {
      const { data } = await http.get(`/${props.scope}/${props.resource}/similar`, { params: { name: props.name, municipality: props.municipality, exclude_id: props.excludeId } })
      if (id === version) items.value = data.data
    } catch { /* Advice must never prevent saving. */ }
  }, 300)
}, { immediate: true })
onUnmounted(() => { ++version; clearTimeout(timer) })
</script>
