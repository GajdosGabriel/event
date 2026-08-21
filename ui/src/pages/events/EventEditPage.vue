<template>
  <div class="edit-shell">
    <div class="edit-card">
      <div class="mb-4">
        <RouterLink :to="indexRoute" class="text-sm text-blue-700 no-underline">{{ t('events.form.back') }}</RouterLink>
        <h1 class="my-2 text-2xl text-slate-900">
          {{ fileableId ? t('events.form.editTitle') : t('events.form.createTitle') }}
        </h1>
      </div>

      <p v-if="loadingData" class="text-slate-600">{{ t('events.form.loading') }}</p>
      <p v-if="serverError" ref="errorBanner" class="text-red-600 mt-2">{{ serverError }}</p>

      <form v-if="!loadingData" class="grid gap-4 mt-4" @submit.prevent="submit">
        <fieldset class="field-group">
          <legend class="field-legend">{{ t('events.sections.basic') }}</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="form.name" :label="t('events.fields.name')" required :error="errors.name" class="lg:col-span-2" />
            <FormField v-model="form.status" type="select" :label="t('events.fields.status')" :error="errors.status">
              <option value="draft">{{ t('events.statuses.draft') }}</option>
              <option value="scheduled">{{ t('events.statuses.scheduled') }}</option>
              <option value="published">{{ t('events.statuses.published') }}</option>
              <option value="archived">{{ t('events.statuses.archived') }}</option>
            </FormField>
            <!-- Termín zverejnenia patrí k stavu „Naplánovaný"; pri ostatných
                 stavoch ho backend aj tak zahodí, tak ho ani neukazujeme. -->
            <FormField
              v-if="form.status === 'scheduled'"
              v-model="form.publish_at"
              type="datetime"
              :label="t('events.fields.publishAt')"
              required
              :error="errors.publish_at"
              :hint="t('events.fields.publishAtHint')"
              class="lg:col-span-2"
            />
            <FormField v-model="form.canal_id" type="select" :label="t('events.fields.canal')" :error="errors.canal_id">
              <option v-if="!form.canal_id" :value="null" disabled>{{ t('events.fields.canalPlaceholder') }}</option>
              <option v-for="c in canals" :key="c.id" :value="c.id">{{ c.name }}</option>
            </FormField>
            <FormField v-model="form.venue_id" :label="t('events.fields.venue')" :error="errors.venue_id" class="lg:col-span-2">
              <template #default="{ value, invalid, update }">
                <div class="flex gap-2">
                  <div class="min-w-0 flex-1">
                    <SearchableSelect
                      :model-value="value ?? null"
                      :options="venuesForCanal"
                      :placeholder="t('events.fields.venuePlaceholder')"
                      :invalid="invalid"
                      @update:model-value="update"
                    />
                  </div>
                  <button type="button" class="btn btn-secondary shrink-0" @click="openVenueModal">
                    {{ t('events.fields.venueAdd') }}
                  </button>
                </div>
              </template>
            </FormField>
          </div>
        </fieldset>

        <fieldset class="field-group">
          <legend class="field-legend">{{ t('events.sections.schedule') }}</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="form.start_at" type="datetime" :label="t('events.fields.startAt')" :error="errors.start_at" />
            <FormField v-model="form.end_at" type="datetime" :label="t('events.fields.endAt')" :error="errors.end_at" />
          </div>
        </fieldset>

        <fieldset class="field-group">
          <legend class="field-legend">{{ t('events.sections.tickets') }}</legend>
          <p v-if="isCreate" class="text-sm text-slate-500">
            {{ t('events.tickets.createHint') }}
          </p>
          <div v-else class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-4 py-3">
            <p class="text-sm text-slate-600">{{ t('events.tickets.manageHint') }}</p>
            <RouterLink :to="`/dashboard/events/${route.params.id}/tickets`" class="btn btn-secondary shrink-0">
              {{ t('events.tickets.manage') }}
            </RouterLink>
          </div>
        </fieldset>

        <!-- Štítky sa v editore nezobrazujú: prideľuje ich `app:events-ai-tag`
             a odvodenie z dát, ručný zásah tu nemá čo meniť. -->

        <fieldset class="field-group">
          <legend class="field-legend">{{ t('events.sections.description') }}</legend>
          <HtmlEditor v-model="form.body" :placeholder="t('events.fields.bodyPlaceholder')" min-height="180px" />

          <!-- AI suggest panel — active when body >= 100 chars -->
          <div v-if="form.body.length >= 100" class="mt-3 rounded-xl border border-violet-200 bg-violet-50 p-3">
            <button type="button" class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-violet-700"
              @click="improveOpen = !improveOpen">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              {{ improveOpen ? t('events.improve.hide') : t('events.improve.show') }}
            </button>
            <div v-if="improveOpen" class="mt-3 grid gap-3">
              <div class="flex flex-wrap gap-3">
                <label class="flex items-center gap-1.5 text-sm text-violet-800 cursor-pointer">
                  <input type="checkbox" v-model="improveModes" value="grammar" class="accent-violet-600" /> {{ t('events.improve.grammar') }}
                </label>
                <label class="flex items-center gap-1.5 text-sm text-violet-800 cursor-pointer">
                  <input type="checkbox" v-model="improveModes" value="style" class="accent-violet-600" /> {{ t('events.improve.style') }}
                </label>
                <label class="flex items-center gap-1.5 text-sm text-violet-800 cursor-pointer">
                  <input type="checkbox" v-model="improveModes" value="expand" class="accent-violet-600" /> {{ t('events.improve.expand') }}
                </label>
              </div>
              <p class="text-xs text-violet-600">{{ t('events.improve.note', { field: 'body_ai' }) }}</p>
              <div class="flex items-center gap-3">
                <button type="button" class="btn btn-sm bg-violet-600 text-white hover:bg-violet-700 border-transparent"
                  :disabled="improving || !improveModes.length" @click="runImprove">
                  {{ improving ? t('events.improve.running') : t('events.improve.run') }}
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
                      @click="improvePreview = 'html'">{{ t('events.improve.preview') }}</button>
                    <button type="button"
                      :class="improvePreview === 'raw' ? 'bg-violet-600 text-white' : 'text-violet-700 hover:bg-violet-100'"
                      class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                      @click="improvePreview = 'raw'">{{ t('events.improve.source') }}</button>
                  </div>
                </div>
                <div class="max-h-72 overflow-y-auto p-3">
                  <div v-if="improvePreview === 'html'" class="prose prose-sm prose-slate max-w-none" v-html="improveResult.improved_text" />
                  <pre v-else class="whitespace-pre-wrap text-xs text-slate-700 font-mono">{{ improveResult.improved_text }}</pre>
                </div>
                <div class="flex flex-wrap gap-2 border-t border-violet-100 px-3 py-2">
                  <button type="button" class="btn btn-sm bg-violet-600 text-white hover:bg-violet-700 border-transparent" @click="applyImproveAsAi">
                    {{ t('events.improve.saveAsAi') }}
                  </button>
                  <button type="button" class="btn btn-sm btn-secondary" @click="applyImproveAsBody">
                    {{ t('events.improve.replaceOriginal') }}
                  </button>
                  <button type="button" class="btn btn-sm btn-secondary" @click="improveResult = null">
                    {{ t('events.improve.discard') }}
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
                  {{ t('events.ai.badge') }}
                </span>
                <span class="text-xs text-emerald-600">{{ t('events.ai.savedWithForm') }}</span>
              </div>
              <div class="flex gap-1">
                <button type="button"
                  :class="aiPreview === 'html' ? 'bg-emerald-600 text-white' : 'text-emerald-700 hover:bg-emerald-100'"
                  class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                  @click="aiPreview = 'html'">{{ t('events.ai.preview') }}</button>
                <button type="button"
                  :class="aiPreview === 'edit' ? 'bg-emerald-600 text-white' : 'text-emerald-700 hover:bg-emerald-100'"
                  class="rounded px-2 py-0.5 text-xs font-medium transition-colors"
                  @click="aiPreview = 'edit'">{{ t('events.ai.edit') }}</button>
              </div>
            </div>
            <div v-if="aiPreview === 'html'" class="max-h-60 overflow-y-auto rounded-lg border border-emerald-100 bg-white p-3">
              <div class="prose prose-sm prose-slate max-w-none" v-html="form.body_ai" />
            </div>
            <HtmlEditor v-else v-model="form.body_ai" min-height="150px" />
            <div class="mt-2 flex gap-2">
              <button type="button" class="btn btn-sm btn-secondary text-red-600 hover:bg-red-50 hover:border-red-200"
                @click="form.body_ai = ''">
                {{ t('events.ai.remove') }}
              </button>
            </div>
          </div>
        </fieldset>

        <fieldset class="field-group">
          <legend class="field-legend">{{ t('events.sections.contact') }}</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="form.website" type="url" :label="t('events.fields.website')" :error="errors.website">
              <template #footer>
                <AttributeIssueHint :issue="websiteIssue" :label="t('events.fields.websiteIssueLabel')" />
              </template>
            </FormField>
            <FormField v-model="form.email" type="email" :label="t('events.fields.email')" :error="errors.email" />
            <FormField v-model="form.phone" type="tel" :label="t('events.fields.phone')" :error="errors.phone" />
          </div>
        </fieldset>

        <div class="flex gap-2">
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? t('events.form.saving') : t('events.form.save') }}
          </button>
          <RouterLink :to="indexRoute" class="btn btn-secondary">{{ t('events.form.cancel') }}</RouterLink>
        </div>
      </form>
    </div>

    <div class="edit-card">
      <h2 class="mb-4 text-lg font-semibold text-slate-800">{{ t('events.sections.images') }}</h2>
      <ImageManager v-if="fileableId" fileable-type="event" :fileable-id="fileableId" />
      <ImagePicker v-else ref="picker" />
    </div>
  </div>

  <!-- Quick venue create modal -->
  <Teleport to="body">
    <div v-if="venueModal.show" class="fixed inset-0 z-600 flex items-center justify-center bg-black/40 p-4" @mousedown.self="venueModal.show = false">
      <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-start gap-3">
          <h2 class="flex-1 text-lg font-semibold text-slate-900">{{ t('events.venueModal.title') }}</h2>
          <button type="button" class="-mr-1 -mt-1 p-1 leading-none text-slate-400 hover:text-slate-700"
            :aria-label="t('events.venueModal.close')" @click="venueModal.show = false">✕</button>
        </div>
        <p v-if="venueModal.error" class="mb-3 text-sm text-red-600">{{ venueModal.error }}</p>
        <!-- Modál má vlastný stav validácie — červená sa v ňom rozsvieti až po
             kliknutí na „Vytvoriť miesto", nezávisle od hlavného formulára. -->
        <div class="grid gap-3">
          <FormField
            v-model="venueModal.form.name"
            :label="t('events.venueModal.name')"
            required
            :validated="venueModal.validated"
            :error="venueModal.errors.name"
            :placeholder="t('events.venueModal.namePlaceholder')"
          />
          <FormField
            v-model="venueModal.form.village_id"
            :label="t('events.venueModal.village')"
            required
            :validated="venueModal.validated"
            :error="venueModal.errors.village_id"
          >
            <template #default="{ value, invalid, update }">
              <SearchableSelect
                :model-value="value ?? null"
                :options="municipalities"
                :placeholder="t('events.venueModal.villagePlaceholder')"
                :invalid="invalid"
                @update:model-value="update"
              />
            </template>
          </FormField>
          <div class="grid grid-cols-2 gap-3">
            <FormField v-model="venueModal.form.street" :label="t('events.venueModal.street')" :placeholder="t('events.venueModal.streetPlaceholder')" />
            <FormField v-model="venueModal.form.postcode" :label="t('events.venueModal.postcode')" placeholder="01234" />
          </div>
        </div>
        <div class="mt-5 flex gap-2">
          <button type="button" class="btn btn-primary" :disabled="venueModal.saving" @click="saveNewVenue">
            {{ venueModal.saving ? t('events.venueModal.saving') : t('events.venueModal.submit') }}
          </button>
          <button type="button" class="btn btn-secondary" @click="venueModal.show = false">{{ t('events.venueModal.cancel') }}</button>
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
import { t } from '@/i18n'
import { useToast } from '@/composables/useToast'
import { useFormOptions } from '@/composables/useFormOptions'
import { provideFormValidation } from '@/composables/useFormValidation'
import { useWebsiteIssue } from '@/composables/useWebsiteIssue'
import { isImageLikeUpload } from '@/utils/uploadFileTypes'
import { scrollToError } from '@/utils/scrollToError'
import { errorBody, isCancelled, withDependencyConsent } from '@/utils/publishFlow'
import AttributeIssueHint from '@/components/AttributeIssueHint.vue'
import FormField from '@/components/FormField.vue'
import ImageManager from '@/components/ImageManager.vue'
import ImagePicker from '@/components/ImagePicker.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
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

const validation = provideFormValidation()

const form = ref({
  name: '',
  status: 'draft',
  publish_at: '',
  canal_id: auth.canalId ?? null,
  venue_id: null as number | null,
  start_at: '',
  end_at: '',
  website: '',
  email: '',
  phone: '',
  body: '',
  body_ai: '',
  // Len ručne zvolené štítky — tie od AI a odvodené z dát spravuje backend
  // a prepočítava ich, takže do formulára nepatria.
  tag_ids: [] as number[],
})

const errors = ref<Record<string, string>>({})

// Upozornenie na neodpovedajúcu webovú adresu (overuje sa na pozadí,
// viď App\Services\Attributes na backende).
const { apply: applyWebsiteIssue, issue: websiteIssue } = useWebsiteIssue(() => form.value.website)
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

// Only offer venues that actually belong to the selected canal — the backend
// rejects an incompatible canal+venue pair (activeCanals, published pivot), so
// showing the rest is misleading. This holds for admins too: even though they
// can manage venues across all canals, an event's venue must live in the event's
// canal. To use a venue from another canal, switch the canal or add a new venue.
const venuesForCanal = computed(() => {
  if (!form.value.canal_id) return venues.value
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
  validated: false,
  error: null as string | null,
  errors: {} as Record<string, string>,
  form: { name: '', village_id: null as number | null, street: '', postcode: '' },
})

function openVenueModal() {
  venueModal.value = { show: true, saving: false, validated: false, error: null, errors: {}, form: { name: '', village_id: null, street: '', postcode: '' } }
}

async function saveNewVenue() {
  venueModal.value.validated = true
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
    toast.success(t('events.venueModal.created'))
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    if (resp?.errors) venueModal.value.errors = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    venueModal.value.error = resp?.message ?? t('events.venueModal.failed')
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
    if (!res.success) throw new Error(res.error ?? t('events.improve.failed'))
    improveResult.value = { improved_text: res.improved_text!, changes_summary: res.changes_summary! }
    improvePreview.value = 'html'
  } catch (e: unknown) {
    improveError.value = (e as Error)?.message ?? t('events.improve.failed')
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
  toast.success(t('events.improve.savedAsAi'))
}

function applyImproveAsBody() {
  if (!improveResult.value) return
  form.value.body = improveResult.value.improved_text
  improveResult.value = null
  improveOpen.value = false
  toast.success(t('events.improve.replaced'))
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
        publish_at: ev.publishAt?.slice(0, 16) ?? '',
        canal_id: ev.canalId ?? auth.canalId ?? null,
        venue_id: ev.venueId ?? null,
        start_at: ev.startAt?.slice(0, 16) ?? '',
        end_at: ev.endAt?.slice(0, 16) ?? '',
        website: ev.website ?? '',
        email: ev.email ?? '',
        phone: ev.phone ?? '',
        body: ev.body ?? '',
        body_ai: ev.bodyAi ?? '',
        // Ručné štítky sa naďalej posielajú späť nezmenené — editor ich len
        // neukazuje, mazať ich pri uložení by bola tichá strata dát.
        tag_ids: (ev.tags ?? []).filter((tag) => (tag.source ?? 'manual') === 'manual').map((tag) => tag.id),
      }
      applyWebsiteIssue(ev)
    } catch { serverError.value = t('events.form.loadFailed') }
    finally { loadingData.value = false }
  }
})

async function submit() {
  validation.markValidated()
  errors.value = {}
  serverError.value = null
  saving.value = true
  try {
    // Prázdny reťazec z <input type="datetime-local"> by prešiel ako neplatný
    // dátum — backend chce buď termín, alebo null.
    const payload = { ...form.value, publish_at: form.value.publish_at || null }
    if (isCreate.value) {
      const ev = await withDependencyConsent(p => createEvent(p, scope.value), payload)
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
      toast.success(t('events.form.created'))
      router.replace(`${prefix.value}/events/${ev.id}/edit`)
    } else {
      await withDependencyConsent(p => updateEvent(Number(route.params.id), p, scope.value), payload)
      toast.success(t('events.form.saved'))
    }
  } catch (e: unknown) {
    // Odmietnuté dopublikovanie závislostí nie je chyba — používateľ sa len
    // rozhodol nechať podujatie tak, ako bolo.
    if (isCancelled(e)) { saving.value = false; return }
    const resp = errorBody(e)
    if (resp?.errors) errors.value = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    serverError.value = resp?.message ?? t('events.form.saveFailed')
    await scrollToError(errorBanner)
  } finally { saving.value = false }
}
</script>
