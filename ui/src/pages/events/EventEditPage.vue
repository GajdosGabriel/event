<template>
  <div class="edit-shell">
    <div class="edit-card">
      <div class="mb-4">
        <RouterLink :to="indexRoute" class="text-sm text-blue-700 no-underline">← Späť na zoznam</RouterLink>
        <h1 class="my-2 text-2xl text-slate-900">{{ fileableId ? 'Upraviť event' : 'Nový event' }}</h1>
      </div>

      <p v-if="loadingData" class="text-slate-600">Načítavam…</p>
      <p v-if="serverError" ref="errorBanner" class="text-red-600 mt-2">{{ serverError }}</p>

      <form v-if="!loadingData" class="grid gap-4 mt-4" @submit.prevent="submit">
        <fieldset class="field-group">
          <legend class="field-legend">Základné info</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <label class="form-label lg:col-span-2">
              Názov *
              <input v-model="form.name" type="text" class="form-input" :class="{ invalid: errors.name }" required />
              <span v-if="errors.name" class="field-error">{{ errors.name }}</span>
            </label>
            <label class="form-label">
              Stav
              <select v-model="form.status" class="form-input" :class="{ invalid: errors.status }">
                <option value="draft">Koncept</option>
                <option value="published">Publikovaný</option>
                <option value="archived">Archivovaný</option>
                <option value="scheduled">Naplánovaný</option>
                <option value="pending_review">Čaká na schválenie</option>
              </select>
              <span v-if="errors.status" class="field-error">{{ errors.status }}</span>
            </label>
            <label class="form-label">
              Kanál
              <select v-model="form.canal_id" class="form-input" :class="{ invalid: errors.canal_id }">
                <option v-if="!form.canal_id" :value="null" disabled>— vyberte kanál —</option>
                <option v-for="c in canals" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
              <span v-if="errors.canal_id" class="field-error">{{ errors.canal_id }}</span>
            </label>
            <label class="form-label lg:col-span-2">
              Miesto konania
              <div class="flex gap-2">
                <select v-model="form.venue_id" class="form-input min-w-0" :class="{ invalid: errors.venue_id }">
                  <option :value="null">— bez miesta —</option>
                  <option v-for="v in venuesForCanal" :key="v.id" :value="v.id">{{ v.name }}</option>
                </select>
                <button type="button" class="btn btn-secondary shrink-0" @click="openVenueModal">
                  + Pridať nové
                </button>
              </div>
              <span v-if="errors.venue_id" class="field-error">{{ errors.venue_id }}</span>
            </label>
          </div>
        </fieldset>

        <fieldset class="field-group">
          <legend class="field-legend">Termín</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <label class="form-label">
              Začiatok
              <DateTimeInput v-model="form.start_at" class="form-input" :class="{ invalid: errors.start_at }" />
              <span v-if="errors.start_at" class="field-error">{{ errors.start_at }}</span>
            </label>
            <label class="form-label">
              Koniec
              <DateTimeInput v-model="form.end_at" class="form-input" :class="{ invalid: errors.end_at }" />
              <span v-if="errors.end_at" class="field-error">{{ errors.end_at }}</span>
            </label>
          </div>
        </fieldset>

        <fieldset class="field-group">
          <legend class="field-legend">Registrácia a lístky</legend>
          <p v-if="isCreate" class="text-sm text-slate-500">
            Lístky a registráciu nastavíte po vytvorení eventu v samostatnej sekcii „Lístky".
          </p>
          <div v-else class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-4 py-3">
            <p class="text-sm text-slate-600">Predaj lístkov, typy lístkov, prihlásení a check-in spravujete v samostatnej sekcii.</p>
            <RouterLink :to="`/dashboard/events/${route.params.id}/tickets`" class="btn btn-secondary shrink-0">
              Spravovať lístky →
            </RouterLink>
          </div>
        </fieldset>

        <fieldset class="field-group">
          <legend class="field-legend">Popis akcie</legend>
          <HtmlEditor v-model="form.body" placeholder="Napíšte popis eventu…" min-height="180px" />

          <!-- AI suggest panel — active when body >= 100 chars -->
          <div v-if="form.body.length >= 100" class="mt-3 rounded-xl border border-violet-200 bg-violet-50 p-3">
            <button type="button" class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-violet-700"
              @click="improveOpen = !improveOpen">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              {{ improveOpen ? 'Skryť AI návrh' : 'AI návrh vylepšeného textu' }}
            </button>
            <div v-if="improveOpen" class="mt-3 grid gap-3">
              <div class="flex flex-wrap gap-3">
                <label class="flex items-center gap-1.5 text-sm text-violet-800 cursor-pointer">
                  <input type="checkbox" v-model="improveModes" value="grammar" class="accent-violet-600" /> Gramatika
                </label>
                <label class="flex items-center gap-1.5 text-sm text-violet-800 cursor-pointer">
                  <input type="checkbox" v-model="improveModes" value="style" class="accent-violet-600" /> Štýl
                </label>
                <label class="flex items-center gap-1.5 text-sm text-violet-800 cursor-pointer">
                  <input type="checkbox" v-model="improveModes" value="expand" class="accent-violet-600" /> Rozšíriť obsah
                </label>
              </div>
              <p class="text-xs text-violet-600">HTML formátovanie je vždy zapnuté — výsledok sa uloží do <strong>body_ai</strong>, originál ostane zachovaný.</p>
              <div class="flex items-center gap-3">
                <button type="button" class="btn btn-sm bg-violet-600 text-white hover:bg-violet-700 border-transparent"
                  :disabled="improving || !improveModes.length" @click="runImprove">
                  {{ improving ? 'Generujem AI návrh…' : 'Vygenerovať AI návrh' }}
                </button>
                <span v-if="improveError" class="text-sm text-red-600">{{ improveError }}</span>
              </div>
              <div v-if="improveResult" class="rounded-lg border border-violet-200 bg-white overflow-hidden">
                <div class="flex items-center justify-between gap-2 border-b border-violet-100 bg-violet-50 px-3 py-2">
                  <p class="text-xs font-semibold text-violet-700">{{ improveResult.changes_summary }}</p>
                  <div class="flex gap-1">
                    <button type="button"
                      :class="improvePreview === 'html' ? 'bg-violet-600 text-white' : 'text-violet-700 hover:bg-violet-100'"
                      class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                      @click="improvePreview = 'html'">Náhľad</button>
                    <button type="button"
                      :class="improvePreview === 'raw' ? 'bg-violet-600 text-white' : 'text-violet-700 hover:bg-violet-100'"
                      class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                      @click="improvePreview = 'raw'">Zdrojový kód</button>
                  </div>
                </div>
                <div class="max-h-72 overflow-y-auto p-3">
                  <div v-if="improvePreview === 'html'" class="prose prose-sm prose-slate max-w-none" v-html="improveResult.improved_text" />
                  <pre v-else class="whitespace-pre-wrap text-xs text-slate-700 font-mono">{{ improveResult.improved_text }}</pre>
                </div>
                <div class="flex flex-wrap gap-2 border-t border-violet-100 px-3 py-2">
                  <button type="button" class="btn btn-sm bg-violet-600 text-white hover:bg-violet-700 border-transparent" @click="applyImproveAsAi">
                    Uložiť ako AI verziu
                  </button>
                  <button type="button" class="btn btn-sm btn-secondary" @click="applyImproveAsBody">
                    Nahradiť originál
                  </button>
                  <button type="button" class="btn btn-sm btn-secondary" @click="improveResult = null">
                    Zahodiť
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- body_ai section — shown when AI version exists -->
          <div v-if="form.body_ai" class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3">
            <div class="flex items-center justify-between gap-2 mb-2">
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                  <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                  AI verzia
                </span>
                <span class="text-xs text-emerald-600">Uloží sa spolu s formulárom</span>
              </div>
              <div class="flex gap-1">
                <button type="button"
                  :class="aiPreview === 'html' ? 'bg-emerald-600 text-white' : 'text-emerald-700 hover:bg-emerald-100'"
                  class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                  @click="aiPreview = 'html'">Náhľad</button>
                <button type="button"
                  :class="aiPreview === 'edit' ? 'bg-emerald-600 text-white' : 'text-emerald-700 hover:bg-emerald-100'"
                  class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                  @click="aiPreview = 'edit'">Upraviť</button>
              </div>
            </div>
            <div v-if="aiPreview === 'html'" class="max-h-60 overflow-y-auto rounded-lg border border-emerald-100 bg-white p-3">
              <div class="prose prose-sm prose-slate max-w-none" v-html="form.body_ai" />
            </div>
            <HtmlEditor v-else v-model="form.body_ai" min-height="150px" />
            <div class="mt-2 flex gap-2">
              <button type="button" class="btn btn-sm btn-secondary text-red-600 hover:bg-red-50 hover:border-red-200"
                @click="form.body_ai = ''">
                Zmazať AI verziu
              </button>
            </div>
          </div>
        </fieldset>

        <fieldset class="field-group">
          <legend class="field-legend">Kontakt</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <label class="form-label">
              Web
              <input v-model="form.website" type="url" class="form-input" :class="{ invalid: errors.website }" />
              <span v-if="errors.website" class="field-error">{{ errors.website }}</span>
            </label>
            <label class="form-label">
              Email
              <input v-model="form.email" type="email" class="form-input" :class="{ invalid: errors.email }" />
              <span v-if="errors.email" class="field-error">{{ errors.email }}</span>
            </label>
            <label class="form-label">
              Telefón
              <input v-model="form.phone" type="tel" class="form-input" :class="{ invalid: errors.phone }" />
              <span v-if="errors.phone" class="field-error">{{ errors.phone }}</span>
            </label>
          </div>
        </fieldset>

        <div class="flex gap-2">
          <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Ukladám…' : 'Uložiť' }}</button>
          <RouterLink :to="indexRoute" class="btn btn-secondary">Zrušiť</RouterLink>
        </div>
      </form>
    </div>

    <div class="edit-card">
      <h2 class="mb-4 text-lg font-semibold text-slate-800">Obrázky</h2>
      <ImageManager v-if="fileableId" fileable-type="event" :fileable-id="fileableId" />
      <ImagePicker v-else ref="picker" />
    </div>
  </div>

  <!-- Quick venue create modal -->
  <Teleport to="body">
    <div v-if="venueModal.show" class="fixed inset-0 z-600 flex items-center justify-center bg-black/40 p-4" @mousedown.self="venueModal.show = false">
      <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Nové miesto konania</h2>
        <p v-if="venueModal.error" class="mb-3 text-sm text-red-600">{{ venueModal.error }}</p>
        <div class="grid gap-3">
          <label class="form-label">
            Názov *
            <input v-model="venueModal.form.name" type="text" class="form-input"
              :class="{ invalid: venueModal.errors.name }" placeholder="napr. Kultúrny dom" />
            <span v-if="venueModal.errors.name" class="field-error">{{ venueModal.errors.name }}</span>
          </label>
          <label class="form-label">
            Obec *
            <SearchableSelect
              v-model="venueModal.form.village_id"
              :options="municipalities"
              placeholder="— vyberte obec —"
              :invalid="!!venueModal.errors.village_id"
            />
            <span v-if="venueModal.errors.village_id" class="field-error">{{ venueModal.errors.village_id }}</span>
          </label>
          <div class="grid grid-cols-2 gap-3">
            <label class="form-label">
              Ulica
              <input v-model="venueModal.form.street" type="text" class="form-input" placeholder="napr. Hlavná 12" />
            </label>
            <label class="form-label">
              PSČ
              <input v-model="venueModal.form.postcode" type="text" class="form-input" placeholder="01234" />
            </label>
          </div>
        </div>
        <div class="mt-5 flex gap-2">
          <button type="button" class="btn btn-primary" :disabled="venueModal.saving" @click="saveNewVenue">
            {{ venueModal.saving ? 'Ukladám…' : 'Vytvoriť miesto' }}
          </button>
          <button type="button" class="btn btn-secondary" @click="venueModal.show = false">Zrušiť</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showEvent, createEvent, updateEvent, improveEventText, type ImproveMode } from '@/api/events'
import { createVenue } from '@/api/venues'
import { uploadFiles } from '@/api/files'
import { useToast } from '@/composables/useToast'
import { useFormOptions } from '@/composables/useFormOptions'
import { isImageLikeUpload } from '@/utils/uploadFileTypes'
import { scrollToError } from '@/utils/scrollToError'
import ImageManager from '@/components/ImageManager.vue'
import ImagePicker from '@/components/ImagePicker.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import DateTimeInput from '@/components/DateTimeInput.vue'
import HtmlEditor from '@/components/HtmlEditor.vue'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const auth = useAuthStore()

const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const isCreate = computed(() => !route.params.id)
const indexRoute = computed(() => `${prefix.value}/events`)

const savedId = ref<number | null>(null)
const fileableId = computed(() => route.params.id ? Number(route.params.id) : savedId.value)
const picker = ref<InstanceType<typeof ImagePicker> | null>(null)

const { canals, venues, municipalities, loadCanals, loadVenues, loadMunicipalities } = useFormOptions(scope.value)

const form = ref({
  name: '',
  status: 'draft',
  canal_id: auth.canalId ?? null,
  venue_id: null as number | null,
  start_at: '',
  end_at: '',
  website: '',
  email: '',
  phone: '',
  body: '',
  body_ai: '',
})

const errors = ref<Record<string, string>>({})
const serverError = ref<string | null>(null)
const errorBanner = ref<HTMLElement | null>(null)
const saving = ref(false)
const loadingData = ref(false)

watch(() => auth.canalId, (id) => {
  if (id && !form.value.canal_id) form.value.canal_id = id
}, { immediate: true })

watch(canals, (list) => {
  if (list.length > 0 && form.value.canal_id === null) {
    form.value.canal_id = list[0].id
  }
})

// Only offer venues that actually belong to the selected canal — picking an
// incompatible pair would be rejected by the backend at submit time anyway.
// Admins manage venues across all canals, so they see the full list unfiltered.
const venuesForCanal = computed(() => {
  if (scope.value === 'admin' || !form.value.canal_id) return venues.value
  return venues.value.filter(v => v.canalIds.includes(form.value.canal_id as number))
})

watch(() => form.value.canal_id, () => {
  // Skip while venues haven't loaded yet — avoids clobbering a valid venue_id
  // restored from an existing event before loadVenues() has resolved.
  if (!venues.value.length) return
  if (form.value.venue_id && !venuesForCanal.value.some(v => v.id === form.value.venue_id)) {
    form.value.venue_id = null
  }
})

watch(() => form.value.start_at, (startAt) => {
  if (!startAt || form.value.end_at) return
  const d = new Date(startAt)
  if (isNaN(d.getTime())) return
  d.setHours(d.getHours() + 2)
  // Build the datetime-local string from local components — toISOString() would
  // convert to UTC and shift the displayed value by the local timezone offset.
  const pad = (n: number) => String(n).padStart(2, '0')
  form.value.end_at = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
})

const venueModal = ref({
  show: false,
  saving: false,
  error: null as string | null,
  errors: {} as Record<string, string>,
  form: { name: '', village_id: null as number | null, street: '', postcode: '' },
})

function openVenueModal() {
  venueModal.value = { show: true, saving: false, error: null, errors: {}, form: { name: '', village_id: null, street: '', postcode: '' } }
}

async function saveNewVenue() {
  venueModal.value.errors = {}
  venueModal.value.error = null
  venueModal.value.saving = true
  try {
    const payload: Record<string, unknown> = {
      name: venueModal.value.form.name,
      village_id: venueModal.value.form.village_id,
      street: venueModal.value.form.street || null,
      postcode: venueModal.value.form.postcode || null,
      canal_id: form.value.canal_id,
    }
    const created = await createVenue(payload)
    venues.value.push({ id: created.id, name: created.name, canalIds: form.value.canal_id ? [form.value.canal_id] : [] })
    form.value.venue_id = created.id
    venueModal.value.show = false
    toast.success('Miesto vytvorené.')
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    if (resp?.errors) venueModal.value.errors = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    venueModal.value.error = resp?.message ?? 'Uloženie zlyhalo.'
  } finally {
    venueModal.value.saving = false
  }
}

const improveOpen = ref(false)
const improving = ref(false)
const improveError = ref<string | null>(null)
const improveModes = ref<ImproveMode[]>(['grammar', 'style'])
const improveResult = ref<{ improved_text: string; changes_summary: string } | null>(null)
const improvePreview = ref<'html' | 'raw'>('html')
const aiPreview = ref<'html' | 'edit'>('html')

async function runImprove() {
  improveError.value = null
  improveResult.value = null
  improving.value = true
  try {
    const modes: ImproveMode[] = [...improveModes.value, 'html']
    const res = await improveEventText(scope.value, form.value.body, modes)
    if (!res.success) throw new Error(res.error ?? 'Vylepšenie zlyhalo.')
    improveResult.value = { improved_text: res.improved_text!, changes_summary: res.changes_summary! }
    improvePreview.value = 'html'
  } catch (e: unknown) {
    improveError.value = (e as Error)?.message ?? 'Vylepšenie zlyhalo.'
  } finally {
    improving.value = false
  }
}

function applyImproveAsAi() {
  if (!improveResult.value) return
  form.value.body_ai = improveResult.value.improved_text
  improveResult.value = null
  improveOpen.value = false
  aiPreview.value = 'html'
  toast.success('AI verzia uložená. Nezabudnite uložiť formulár.')
}

function applyImproveAsBody() {
  if (!improveResult.value) return
  form.value.body = improveResult.value.improved_text
  improveResult.value = null
  improveOpen.value = false
  toast.success('Originálny text bol nahradený.')
}

onMounted(async () => {
  loadCanals()
  loadVenues()
  loadMunicipalities()
  if (!isCreate.value) {
    loadingData.value = true
    try {
      const ev = await showEvent(scope.value, Number(route.params.id))
      form.value = {
        name: ev.name,
        status: ev.status,
        canal_id: ev.canalId ?? auth.canalId ?? null,
        venue_id: ev.venueId ?? null,
        start_at: ev.startAt?.slice(0, 16) ?? '',
        end_at: ev.endAt?.slice(0, 16) ?? '',
        website: ev.website ?? '',
        email: ev.email ?? '',
        phone: ev.phone ?? '',
        body: ev.body ?? '',
        body_ai: ev.bodyAi ?? '',
      }
    } catch { serverError.value = 'Event sa nepodarilo načítať.' }
    finally { loadingData.value = false }
  }
})

async function submit() {
  errors.value = {}
  serverError.value = null
  saving.value = true
  try {
    const payload = { ...form.value }
    if (isCreate.value) {
      const ev = await createEvent(payload, scope.value)
      savedId.value = ev.id
      const pending = picker.value?.files ?? []
      if (pending.length) {
        // PDFs are converted server-side into an image preview, so they upload as type
        // "image" (and can become the primary/cover image); DOC/DOCX upload as type "file".
        const imageFiles = pending.filter(isImageLikeUpload)
        const docFiles = pending.filter(f => !isImageLikeUpload(f))
        for (const [group, type, makePrimary] of [
          [imageFiles, 'image', true] as const,
          [docFiles, 'file', false] as const,
        ]) {
          if (!group.length) continue
          const fd = new FormData()
          fd.append('fileable_type', 'event')
          fd.append('fileable_id', String(ev.id))
          fd.append('type', type)
          fd.append('make_primary', makePrimary ? '1' : '0')
          group.forEach(f => fd.append('files[]', f))
          await uploadFiles(fd)
        }
      }
      toast.success('Event vytvorený.')
      router.replace(`${prefix.value}/events/${ev.id}/edit`)
    } else {
      await updateEvent(Number(route.params.id), payload, scope.value)
      toast.success('Event uložený.')
    }
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    if (resp?.errors) errors.value = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    serverError.value = resp?.message ?? 'Uloženie zlyhalo.'
    await scrollToError(errorBanner)
  } finally { saving.value = false }
}
</script>
