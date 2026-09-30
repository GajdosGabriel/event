<template>
  <!-- Spoločná hlavička editora podujatia, kanála a miesta: odkaz späť, nadpis
       a vpravo odznak pripravenosti profilu. Ďalšie tlačidlá idú do slotu. -->
  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <RouterLink :to="backTo" class="text-sm text-blue-700 no-underline">{{ backLabel }}</RouterLink>
      <h1 class="my-2 text-2xl text-slate-900">{{ title }}</h1>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <ReadinessBadge :kind="kind" :scope="scope" :values="values" />
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'
import type { AiKind, Scope } from '@/api/ai'
import ReadinessBadge from '@/components/ai/ReadinessBadge.vue'

defineProps<{
  title: string
  backTo: RouteLocationRaw
  backLabel: string
  kind: AiKind
  scope: Scope
  values: Record<string, unknown>
}>()
</script>
