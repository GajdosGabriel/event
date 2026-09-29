<template>
  <nav class="mb-4 flex gap-1 border-b border-slate-200" :aria-label="t('nav.messages')">
    <RouterLink to="/dashboard/spravy" class="tab" :class="{ active: active === 'inbox' }">
      {{ t('messages.tabs.inbox') }}
      <span v-if="inboxUnread" class="tab-badge">{{ inboxUnread }}</span>
    </RouterLink>
    <RouterLink to="/dashboard/spravy/podpora" class="tab" :class="{ active: active === 'support' }">
      {{ t('messages.tabs.support') }}
      <span v-if="supportUnread" class="tab-badge">{{ supportUnread }}</span>
    </RouterLink>
  </nav>
</template>

<script setup lang="ts">
import { useI18n } from '@/i18n'

/** Záložky sekcie Správy: prijaté správy od návštevníkov a vlákna s podporou. */
defineProps<{ active: 'inbox' | 'support'; inboxUnread?: number; supportUnread?: number }>()

const { t } = useI18n()
</script>

<style scoped>
@reference "tailwindcss";

.tab {
  @apply -mb-px inline-flex items-center gap-2 border-b-2 border-transparent px-3 py-2 text-sm font-medium text-slate-500 no-underline hover:text-slate-800;
}
.tab.active { @apply border-teal-600 text-teal-800; }
.tab-badge { @apply rounded-full bg-teal-600 px-1.5 text-[11px] font-semibold text-white; }
</style>
