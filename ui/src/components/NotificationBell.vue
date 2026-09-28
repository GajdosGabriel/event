<template>
  <div v-if="auth.ready && auth.isAuthenticated" ref="root" class="relative shrink-0" @keydown.esc.stop.prevent="close(true)">
    <button ref="trigger" type="button" class="relative flex h-9 w-9 items-center justify-center rounded-lg transition hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-blue-400" :class="unread ? 'text-red-400' : 'text-current'" :aria-label="unread ? t('notifications.unread', { count: unread }) : t('notifications.title')" :aria-expanded="open" :aria-controls="panelId" @click="toggle">
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 00-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
      <span v-if="unread" class="absolute -right-1 -top-1 min-w-4 rounded-full bg-red-500 px-1 text-center text-[10px] font-bold leading-4 text-white">{{ unread > 99 ? '99+' : unread }}</span>
    </button>
    <section v-if="open" :id="panelId" :aria-label="t('notifications.title')" class="fixed inset-x-3 top-20 z-50 overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-800 shadow-xl sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:w-96">
      <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
        <h2 class="text-sm font-bold">{{ t('notifications.title') }}</h2>
        <button v-if="unread" type="button" class="text-xs font-semibold text-blue-700 disabled:opacity-50" :disabled="busy || loading" @click="mutate(() => markNotifications(true))">{{ t('notifications.readAll') }}</button>
      </div>
      <div v-if="error" role="alert" class="px-4 py-3 text-sm text-red-600">{{ error }} <button type="button" class="underline" :disabled="busy || loading" @click="load()">{{ t('notifications.retry') }}</button></div>
      <p v-if="loading && !items.length" role="status" class="px-4 py-8 text-center text-sm text-slate-500">{{ t('notifications.loading') }}</p>
      <p v-else-if="!items.length && !error" class="px-4 py-8 text-center text-sm text-slate-500">{{ t('notifications.empty') }}</p>
      <ul v-if="items.length" class="max-h-[min(24rem,60dvh)] divide-y divide-slate-100 overflow-y-auto">
        <li v-for="group in groups" :key="group.key" class="flex items-start gap-1 px-3 py-3" :class="group.unreadIds.length ? 'bg-blue-50/70' : ''">
          <component :is="group.link ? 'a' : 'button'" :href="group.link ?? undefined" :type="group.link ? undefined : 'button'" class="min-w-0 flex-1 rounded px-1 text-left text-sm no-underline hover:text-blue-700" @click="visit($event, group)">
            <span class="block break-words" :class="{ 'font-semibold': group.unreadIds.length }">{{ group.message }}</span>
            <span class="mt-1 block text-xs text-slate-500"><time :datetime="group.created_at">{{ formatDate(group.created_at) }}</time><span v-if="group.ids.length > 1" class="ml-2 rounded bg-slate-100 px-1.5">{{ group.ids.length }}×</span></span>
          </component>
          <button type="button" class="flex h-7 w-7 shrink-0 items-center justify-center rounded hover:bg-slate-200 disabled:opacity-50" :title="group.unreadIds.length ? t('notifications.read') : t('notifications.unreadAction')" :aria-label="group.unreadIds.length ? t('notifications.read') : t('notifications.unreadAction')" :disabled="busy || loading" @click="mutate(() => markNotifications(!!group.unreadIds.length, group.ids))"><span class="h-2.5 w-2.5 rounded-full border border-blue-600" :class="{ 'bg-blue-600': group.unreadIds.length }" /></button>
          <button type="button" class="h-7 w-7 shrink-0 rounded text-lg text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-50" :aria-label="t('notifications.delete')" :title="t('notifications.delete')" :disabled="busy || loading" @click="mutate(() => deleteNotifications({ ids: group.ids }))">×</button>
        </li>
      </ul>
      <div v-if="page < lastPage" class="px-4 py-2 text-center"><button type="button" class="text-xs font-semibold text-blue-700 disabled:opacity-50" :disabled="loading || busy" @click="load(page + 1)">{{ t('notifications.older') }}</button></div>
      <div v-if="items.length" class="flex justify-end gap-4 border-t border-slate-100 px-4 py-3">
        <button type="button" class="text-xs text-slate-500 hover:text-slate-800 disabled:opacity-50" :disabled="busy || loading" @click="removeAll('read')">{{ t('notifications.deleteRead') }}</button>
        <button type="button" class="text-xs text-slate-500 hover:text-red-600 disabled:opacity-50" :disabled="busy || loading" @click="removeAll()">{{ t('notifications.deleteAll') }}</button>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { localeTag, useI18n } from '@/i18n'
import { deleteNotifications, fetchNotifications, fetchUnreadCount, groupNotifications, markNotifications, type NotificationItem } from '@/api/notifications'

const auth = useAuthStore()
const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const panelId = useId()
const root = ref<HTMLElement>()
const trigger = ref<HTMLButtonElement>()
const open = ref(false)
const items = ref<NotificationItem[]>([])
const unread = ref(0)
const page = ref(1)
const lastPage = ref(1)
const loading = ref(false)
const busy = ref(false)
const error = ref('')
const groups = computed(() => groupNotifications(items.value))
let generation = 0
let revision = 0
let countLoading = false
let timer: ReturnType<typeof setInterval> | undefined
const enabled = computed(() => auth.ready && auth.isAuthenticated)

function close(focus = false) { open.value = false; if (focus) trigger.value?.focus() }
function formatDate(value: string) { return new Date(value).toLocaleString(localeTag(), { dateStyle: 'medium', timeStyle: 'short' }) }
async function load(nextPage = 1) {
  if (loading.value || !enabled.value) return
  const current = generation
  revision++
  loading.value = true
  error.value = ''
  try {
    const result = await fetchNotifications(nextPage)
    if (current !== generation) return
    items.value = nextPage === 1 ? result.data : [...new Map([...items.value, ...result.data].map(item => [item.id, item])).values()]
    page.value = result.meta.current_page
    lastPage.value = result.meta.last_page
    unread.value = result.meta.unread
  } catch { if (current === generation) error.value = t('notifications.loadError') }
  finally { if (current === generation) loading.value = false }
}
async function refreshCount() {
  if (!enabled.value || busy.value || loading.value || countLoading || document.hidden) return
  const current = generation
  const countRevision = revision
  countLoading = true
  try {
    const count = await fetchUnreadCount()
    if (current === generation && countRevision === revision && !busy.value && !loading.value) unread.value = count
  } catch { /* The list shows a retry action when opened; background polling stays quiet. */ }
  finally { countLoading = false }
}
function toggle() { open.value = !open.value; if (open.value) void load() }
async function mutate(action: () => Promise<void>): Promise<boolean> {
  if (busy.value || loading.value) return false
  const current = generation
  revision++
  busy.value = true
  error.value = ''
  try {
    await action()
    if (current !== generation) return false
    await load()
    return true
  } catch { if (current === generation) error.value = t('notifications.saveError'); return false }
  finally { if (current === generation) busy.value = false }
}
async function visit(event: MouseEvent, group: ReturnType<typeof groupNotifications>[number]) {
  if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
    if (group.unreadIds.length) void mutate(() => markNotifications(true, group.unreadIds))
    return
  }
  event.preventDefault()
  if (group.unreadIds.length && !await mutate(() => markNotifications(true, group.unreadIds))) return
  if (group.link) { close(); await router.push(group.link) }
}
function removeAll(only?: 'read') {
  if (window.confirm(t(only ? 'notifications.confirmDeleteRead' : 'notifications.confirmDeleteAll'))) {
    void mutate(() => deleteNotifications(only ? { only } : {}))
  }
}
function outside(event: Event) { if (!root.value?.contains(event.target as Node)) close() }
function visible() { if (!document.hidden) void refreshCount() }
watch(() => route.fullPath, () => close())
watch(() => [enabled.value, auth.identity?.id], () => {
  generation++
  close()
  items.value = []; unread.value = 0; page.value = 1; lastPage.value = 1
  loading.value = false; busy.value = false; error.value = ''
  if (enabled.value) void refreshCount()
}, { immediate: true })
onMounted(() => {
  document.addEventListener('pointerdown', outside)
  document.addEventListener('focusin', outside)
  document.addEventListener('visibilitychange', visible)
  timer = setInterval(refreshCount, 60000)
})
onBeforeUnmount(() => {
  generation++
  clearInterval(timer)
  document.removeEventListener('pointerdown', outside)
  document.removeEventListener('focusin', outside)
  document.removeEventListener('visibilitychange', visible)
})
</script>
