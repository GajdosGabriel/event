<template>
  <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium"
    :class="STYLES[status.value]">
    <span class="size-1.5 rounded-full" :class="DOTS[status.value]" />
    {{ status.label }}
  </span>
</template>

<script setup lang="ts">
import type { SupportTicket } from '@/api/support'

defineProps<{ status: SupportTicket['status'] }>()

const STYLES = {
  open: 'bg-amber-50 text-amber-700',
  answered: 'bg-emerald-50 text-emerald-700',
  closed: 'bg-slate-100 text-slate-500',
}
const DOTS = {
  open: 'bg-amber-500',
  answered: 'bg-emerald-500',
  closed: 'bg-slate-400',
}
</script>
