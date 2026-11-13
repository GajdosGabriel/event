<template>
  <!-- Odkaz na skener pre dobrovoľníkov pri vchode. Nevyžaduje účet ani
       miesto v tíme — platí len pre toto podujatie a deň po jeho konci. -->
  <section class="rounded-2xl border border-slate-200 bg-white p-4">
    <h2 class="m-0 text-sm font-semibold text-slate-900">{{ t('checkin.links.title') }}</h2>
    <p class="mt-1 text-xs leading-relaxed text-slate-600">{{ t('checkin.links.lead') }}</p>

    <p v-if="loadError" class="mt-3 text-xs text-red-600">{{ t('checkin.links.loadFailed') }}</p>

    <ul v-if="links.length" class="m-0 mt-3 grid list-none gap-2 p-0">
      <li v-for="link in links" :key="link.id" class="rounded-xl bg-slate-50 p-3 ring-1 ring-inset ring-slate-200">
        <div class="flex items-start justify-between gap-2">
          <p class="m-0 text-sm font-medium text-slate-900">{{ link.label || t('checkin.links.unnamed') }}</p>
          <button type="button" class="shrink-0 text-xs text-red-600 hover:underline" @click="revoke(link)">
            {{ t('checkin.links.revoke') }}
          </button>
        </div>
        <p class="m-0 mt-0.5 text-xs text-slate-500">
          {{ t('checkin.links.validUntil', { date: formatDate(link.expiresAt) }) }}
          <template v-if="link.lastUsedAt"> · {{ t('checkin.links.lastUsed', { date: formatDate(link.lastUsedAt) }) }}</template>
        </p>
        <div class="mt-2 flex flex-wrap gap-2">
          <button type="button" class="action-btn text-xs" @click="copy(link)">
            {{ copiedId === link.id ? t('checkin.links.copied') : t('checkin.links.copy') }}
          </button>
          <button v-if="canShare" type="button" class="action-btn text-xs" @click="share(link)">
            {{ t('checkin.links.share') }}
          </button>
        </div>
      </li>
    </ul>

    <form class="mt-3 grid gap-2" @submit.prevent="create">
      <input
        v-model.trim="label"
        type="text"
        maxlength="80"
        :placeholder="t('checkin.links.labelPlaceholder')"
        class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
      />
      <button type="submit" class="btn btn-primary" :disabled="busy">
        {{ busy ? t('checkin.links.creating') : t('checkin.links.create') }}
      </button>
      <p v-if="createError" class="m-0 text-xs text-red-600" role="alert">{{ createError }}</p>
    </form>
    <p class="mt-3 mb-0 text-xs text-slate-500">{{ t('checkin.links.privacy') }}</p>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { createCheckinLink, listCheckinLinks, revokeCheckinLink, type CheckinLinkItem } from '@/api/checkinLinks'
import { currentLocale, t } from '@/i18n'

const props = defineProps<{ eventId: number }>()

const links = ref<CheckinLinkItem[]>([])
const label = ref('')
const busy = ref(false)
const loadError = ref(false)
const createError = ref<string | null>(null)
const copiedId = ref<number | null>(null)
const canShare = typeof navigator !== 'undefined' && typeof navigator.share === 'function'

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString(currentLocale(), { day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function load() {
  try {
    links.value = await listCheckinLinks(props.eventId)
    loadError.value = false
  } catch {
    loadError.value = true
  }
}

async function create() {
  busy.value = true
  createError.value = null
  try {
    links.value = [...links.value, await createCheckinLink(props.eventId, label.value)]
    label.value = ''
  } catch {
    createError.value = t('checkin.links.createFailed')
  } finally {
    busy.value = false
  }
}

async function revoke(link: CheckinLinkItem) {
  if (!confirm(t('checkin.links.revokeConfirm'))) return
  try {
    await revokeCheckinLink(props.eventId, link.id)
    links.value = links.value.filter((item) => item.id !== link.id)
  } catch {
    createError.value = t('checkin.links.revokeFailed')
  }
}

async function copy(link: CheckinLinkItem) {
  try {
    await navigator.clipboard.writeText(link.url)
    copiedId.value = link.id
    setTimeout(() => { if (copiedId.value === link.id) copiedId.value = null }, 2000)
  } catch {
    // Schránka nedostupná (http, staré prehliadače) — aspoň ukážeme adresu.
    prompt(t('checkin.links.copy'), link.url)
  }
}

async function share(link: CheckinLinkItem) {
  try {
    await navigator.share({ title: t('checkin.links.shareTitle'), url: link.url })
  } catch {
    // Zrušené zdieľanie nie je chyba.
  }
}

onMounted(load)
</script>
