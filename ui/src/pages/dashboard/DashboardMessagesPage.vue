<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <div class="mb-4">
      <h1 class="text-2xl font-semibold text-slate-900">Správy</h1>
      <p class="text-sm text-slate-500">Otázky návštevníkov k vašim podujatiam, miestam a kanálom.</p>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2">
      <input v-model="search" type="search" placeholder="Hľadať v texte správ…"
        class="w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
        @input="onSearch" />
      <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
        <input v-model="onlyUnread" type="checkbox" class="accent-teal-600" @change="load(1)" />
        Len neprečítané
      </label>
    </div>

    <p v-if="loading" class="text-slate-500">Načítavam…</p>
    <p v-else-if="error" class="text-red-600">{{ error }}</p>
    <p v-else-if="!messages.length" class="text-slate-400">
      {{ onlyUnread ? 'Žiadne neprečítané správy.' : 'Zatiaľ vám nikto nenapísal.' }}
    </p>

    <div v-else class="grid gap-4 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
      <!-- Zoznam vlákien -->
      <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <li v-for="message in messages" :key="message.id">
          <button type="button" class="msg-row" :class="{ active: activeId === message.id }"
            @click="open(message)">
            <span class="flex items-center gap-2">
              <span v-if="!message.readAt" class="size-2 shrink-0 rounded-full bg-teal-500" aria-label="Neprečítané" />
              <span class="truncate font-semibold text-slate-900" :class="{ 'font-normal': message.readAt }">
                {{ partyName(message) }}
              </span>
              <span class="ml-auto shrink-0 text-xs text-slate-400">{{ formatDate(message.createdAt) }}</span>
            </span>
            <span v-if="message.target" class="mt-0.5 block truncate text-xs text-slate-500">
              {{ targetLabel(message.target.type) }}: {{ message.target.name }}
            </span>
            <span class="mt-1 block truncate text-sm text-slate-600">{{ message.body }}</span>
          </button>
        </li>
      </ul>

      <!-- Vlákno -->
      <section v-if="thread" class="rounded-2xl border border-slate-200 bg-white p-4">
        <header class="mb-3 flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-3">
          <div class="min-w-0">
            <p class="font-semibold text-slate-900">{{ partyName(thread) }}</p>
            <RouterLink v-if="thread.target && targetRoute(thread.target)" :to="targetRoute(thread.target)!"
              class="text-sm text-blue-700 no-underline hover:underline">
              {{ targetLabel(thread.target.type) }}: {{ thread.target.name }} →
            </RouterLink>
            <p v-else-if="thread.target" class="text-sm text-slate-500">
              {{ targetLabel(thread.target.type) }}: {{ thread.target.name }}
            </p>
          </div>
          <button v-if="thread.permissions.markRead" type="button" class="action-btn"
            @click="toggleRead(thread)">
            {{ thread.readAt ? 'Označiť ako neprečítané' : 'Označiť ako prečítané' }}
          </button>
        </header>

        <article v-for="entry in [thread, ...thread.replies]" :key="entry.id" class="mb-3">
          <p class="text-xs text-slate-400">
            {{ entry.outgoing ? 'Vy' : entry.senderName }} · {{ formatDateTime(entry.createdAt) }}
          </p>
          <p class="mt-1 whitespace-pre-line rounded-xl px-3 py-2 text-sm"
            :class="entry.outgoing ? 'bg-teal-50 text-slate-800' : 'bg-slate-50 text-slate-800'">
            {{ entry.body }}
          </p>
        </article>

        <form v-if="thread.permissions.reply" class="mt-4 border-t border-slate-100 pt-3" @submit.prevent="sendReply">
          <FormField v-model="replyBody" type="textarea" rows="4" placeholder="Napíšte odpoveď…" />
          <p v-if="replyError" class="mt-1 text-sm text-red-600">{{ replyError }}</p>
          <div class="mt-2 flex items-center gap-2">
            <button type="submit" class="btn btn-primary" :disabled="replying || replyBody.trim().length < 2">
              {{ replying ? 'Odosielam…' : 'Odpovedať' }}
            </button>
            <span class="text-xs text-slate-500">Odpoveď pošleme na e-mail odosielateľa.</span>
          </div>
        </form>
        <p v-else class="mt-4 border-t border-slate-100 pt-3 text-sm text-slate-500">
          <template v-if="thread.outgoing">Toto vlákno začalo vašou správou — odpovedá v ňom druhá strana.</template>
          <template v-else>Odpovedať sa tu nedá. Odpovede posielajú len účty s overeným e-mailom.</template>
        </p>
      </section>

      <section v-else class="hidden rounded-2xl border border-dashed border-slate-200 p-6 text-slate-400 lg:block">
        Vyberte správu zo zoznamu.
      </section>
    </div>

    <div v-if="meta && meta.last_page > 1" class="mt-4 flex items-center gap-2">
      <button type="button" class="action-btn" :disabled="page <= 1" @click="load(page - 1)">← Predch.</button>
      <span class="text-sm text-slate-500">{{ page }} / {{ meta.last_page }}</span>
      <button type="button" class="action-btn" :disabled="page >= meta.last_page" @click="load(page + 1)">Ďalej →</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  indexMessages,
  showMessage,
  markMessageRead,
  replyToMessage,
  type MessageItem,
  type MessageTargetType,
} from '@/api/messages'
import { useToast } from '@/composables/useToast'
import FormField from '@/components/FormField.vue'
import type { PaginatedResponse } from '@/types'

const toast = useToast()

const messages = ref<MessageItem[]>([])
const meta = ref<PaginatedResponse<MessageItem>['meta'] | null>(null)
const thread = ref<MessageItem | null>(null)
const activeId = ref<number | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const search = ref('')
const onlyUnread = ref(false)
const page = ref(1)
const replyBody = ref('')
const replyError = ref<string | null>(null)
const replying = ref(false)

let searchTimeout: ReturnType<typeof setTimeout> | undefined

const TARGET_LABELS: Record<MessageTargetType, string> = {
  event: 'Podujatie',
  venue: 'Miesto',
  canal: 'Kanál',
}

function targetLabel(type: MessageTargetType | null): string {
  return type ? TARGET_LABELS[type] : 'Záznam'
}

/** V zozname patrí meno protistrany, nie moje — aj keď vlákno začalo mojou správou. */
function partyName(message: MessageItem): string {
  return message.outgoing ? message.recipientName : message.senderName
}

/** Cieľ vedie do dashboardu, nie na verejný detail — odtiaľ vie organizátor konať. */
function targetRoute(target: NonNullable<MessageItem['target']>): string | null {
  if (!target.type) return null
  return `/dashboard/${{ event: 'events', venue: 'venues', canal: 'canals' }[target.type]}/${target.id}`
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('sk-SK', { day: 'numeric', month: 'numeric' })
}

function formatDateTime(value: string) {
  return new Date(value).toLocaleString('sk-SK', { day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function load(targetPage = 1) {
  loading.value = true
  error.value = null
  try {
    const result = await indexMessages({
      page: targetPage,
      search: search.value || undefined,
      unread: onlyUnread.value || undefined,
    })
    messages.value = result.data
    meta.value = result.meta
    page.value = targetPage
  } catch {
    error.value = 'Správy sa nepodarilo načítať.'
  } finally {
    loading.value = false
  }
}

function onSearch() {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => load(1), 300)
}

async function open(message: MessageItem) {
  replyBody.value = ''
  replyError.value = null
  activeId.value = message.id
  // Detail vracia koreň vlákna — pri odpovedi na moju vlastnú správu je to iný
  // záznam než riadok, na ktorý sa kliklo. Preto je aktívny riadok zvlášť.
  thread.value = await showMessage(message.id)
  // Otvorenie označí správu na backende za prečítanú; zoznam to musí odzrkadliť
  // aj bez opätovného načítania celej stránky.
  message.readAt = message.readAt ?? new Date().toISOString()
}

async function toggleRead(message: MessageItem) {
  const updated = await markMessageRead(message.id, !message.readAt)
  message.readAt = updated.readAt
  const row = messages.value.find((m) => m.id === message.id)
  if (row) row.readAt = updated.readAt
  if (onlyUnread.value) await load(page.value)
}

async function sendReply() {
  if (!thread.value) return
  replyError.value = null
  replying.value = true
  try {
    const reply = await replyToMessage(thread.value.id, replyBody.value.trim())
    thread.value.replies.push(reply)
    replyBody.value = ''
    toast.success('Odpoveď odoslaná.')
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { message?: string } } })?.response?.data
    replyError.value = resp?.message ?? 'Odpoveď sa nepodarilo odoslať.'
  } finally {
    replying.value = false
  }
}

onMounted(() => load(1))
</script>

<style scoped>
@reference "tailwindcss";

.msg-row {
  @apply block w-full cursor-pointer border-0 border-l-2 border-transparent bg-transparent px-4 py-3 text-left hover:bg-slate-50;
}
.msg-row.active { @apply border-l-teal-500 bg-slate-50; }
</style>
