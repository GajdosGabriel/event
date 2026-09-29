<template>
  <div class="grid gap-4" :class="{ 'xl:grid-cols-[minmax(0,1fr)_16rem]': details }">
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
      <header class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 p-4">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-xs text-slate-400">{{ ticket.reference }}</span>
            <SupportStatusBadge :status="ticket.status" />
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ ticket.category.label }}</span>
          </div>
          <h2 class="mt-1 break-words text-lg font-semibold text-slate-900">{{ ticket.subject }}</h2>
        </div>
        <button type="button" class="action-btn" :disabled="busy" @click="toggleClosed">
          {{ isClosed ? t('support.reopen') : t('support.close') }}
        </button>
      </header>

      <div class="grid gap-4 bg-slate-50/60 p-4">
        <div v-for="m in ticket.messages" :key="m.id" class="flex gap-3" :class="{ 'flex-row-reverse': m.mine }">
          <span class="grid size-8 shrink-0 place-items-center rounded-full text-xs font-semibold"
            :class="m.isStaff ? 'bg-teal-600 text-white' : 'bg-slate-200 text-slate-600'" aria-hidden="true">
            <svg v-if="m.isStaff" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <template v-else>{{ initials(m.author) }}</template>
          </span>
          <div class="flex max-w-[85%] flex-col sm:max-w-[75%]" :class="m.mine ? 'items-end' : 'items-start'">
            <p class="mb-1 text-[11px] text-slate-400">
              <span class="font-medium text-slate-600">{{ m.mine ? t('messages.me') : m.author }}</span>
              · {{ formatDateTime(m.createdAt) }}
            </p>
            <p class="whitespace-pre-line break-words rounded-2xl px-4 py-2.5 text-sm"
              :class="m.mine
                ? 'rounded-tr-sm bg-teal-600 text-white'
                : m.isStaff
                  ? 'rounded-tl-sm border border-teal-100 bg-white text-slate-800'
                  : 'rounded-tl-sm border border-slate-200 bg-white text-slate-800'">{{ m.body }}</p>
          </div>
        </div>
      </div>

      <form class="grid gap-2 border-t border-slate-100 p-4" @submit.prevent="send">
        <p v-if="isClosed" class="text-xs text-slate-500">{{ t('support.closedHint') }}</p>
        <textarea v-model="reply" rows="3" maxlength="5000" class="support-input resize-y"
          :placeholder="t('support.replyPlaceholder')"
          @keydown.ctrl.enter.prevent="send" @keydown.meta.enter.prevent="send" />
        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
        <div class="flex items-center gap-3">
          <span class="hidden text-xs text-slate-400 sm:inline">{{ t('support.ctrlEnter') }}</span>
          <button type="submit" class="btn btn-primary ml-auto" :disabled="busy || !reply.trim()">
            {{ busy ? t('support.sending') : t('support.send') }}
          </button>
        </div>
      </form>
    </section>

    <!-- Detaily pre podporu: kto, odkiaľ, v akom prehliadači -->
    <aside v-if="details" class="h-fit rounded-2xl border border-slate-200 bg-white p-4 text-sm">
      <p class="mb-3 font-semibold text-slate-900">{{ t('support.admin.details') }}</p>
      <dl class="grid gap-3">
        <div>
          <dt class="text-xs text-slate-400">{{ t('support.admin.user') }}</dt>
          <dd class="text-slate-800">{{ ticket.userName }}</dd>
          <dd v-if="ticket.userEmail">
            <a :href="`mailto:${ticket.userEmail}`" class="break-all text-blue-700">{{ ticket.userEmail }}</a>
          </dd>
          <dd v-if="ticket.userId">
            <RouterLink :to="`/admin/users/${ticket.userId}`" class="text-xs text-blue-700">{{ t('support.admin.profile') }} →</RouterLink>
          </dd>
        </div>
        <div>
          <dt class="text-xs text-slate-400">{{ t('support.admin.canal') }}</dt>
          <dd v-if="ticket.canal">
            <RouterLink :to="`/admin/canals/${ticket.canal.id}`" class="text-blue-700">{{ ticket.canal.name }}</RouterLink>
          </dd>
          <dd v-else class="text-slate-500">{{ t('support.admin.noCanal') }}</dd>
        </div>
        <div v-if="ticket.pageUrl">
          <dt class="text-xs text-slate-400">{{ t('support.admin.page') }}</dt>
          <dd class="break-all font-mono text-xs text-slate-700">{{ ticket.pageUrl }}</dd>
        </div>
        <div>
          <dt class="text-xs text-slate-400">{{ t('support.admin.created') }}</dt>
          <dd class="text-slate-800">{{ formatDateTime(ticket.createdAt) }}</dd>
        </div>
        <div v-if="ticket.userAgent">
          <dt class="text-xs text-slate-400">{{ t('support.admin.browser') }}</dt>
          <dd class="break-words text-xs text-slate-500">{{ ticket.userAgent }}</dd>
        </div>
      </dl>
    </aside>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { replySupportTicket, setSupportTicketStatus, type SupportTicket } from '@/api/support'
import SupportStatusBadge from '@/components/support/SupportStatusBadge.vue'
import { currentLocale, useI18n } from '@/i18n'

/**
 * Jedno vlákno podpory — rovnaké pre používateľa (Správy → Podpora) aj pre
 * administrátora (Admin → Podpora). `details` zapína bočný panel s kontextom,
 * ktorý vidí len podpora.
 */
const props = defineProps<{ ticket: SupportTicket; details?: boolean }>()
const emit = defineEmits<{ updated: [ticket: SupportTicket] }>()

const { t } = useI18n()

const reply = ref('')
const busy = ref(false)
const error = ref<string | null>(null)

const isClosed = computed(() => props.ticket.status.value === 'closed')

watch(() => props.ticket.id, () => {
  reply.value = ''
  error.value = null
})

function formatDateTime(value: string) {
  return new Date(value).toLocaleString(currentLocale(), { day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function initials(name: string) {
  return name.split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]!.toUpperCase()).join('') || '?'
}

async function send() {
  if (!reply.value.trim() || busy.value) return
  busy.value = true
  error.value = null
  try {
    emit('updated', await replySupportTicket(props.ticket.id, reply.value.trim()))
    reply.value = ''
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { message?: string } } })?.response?.data
    error.value = resp?.message ?? t('support.sendFailed')
  } finally {
    busy.value = false
  }
}

async function toggleClosed() {
  busy.value = true
  try {
    emit('updated', await setSupportTicketStatus(props.ticket.id, isClosed.value ? 'open' : 'closed'))
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
@reference "tailwindcss";

.support-input {
  @apply w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30;
}
</style>
