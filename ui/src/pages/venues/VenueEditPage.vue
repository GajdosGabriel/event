<template>
  <div class="edit-shell">
    <div class="edit-card">
      <RouterLink :to="indexRoute" class="text-sm text-blue-700 no-underline">← Späť</RouterLink>
      <h1 class="my-2 text-2xl text-slate-900">{{ fileableId ? 'Upraviť miesto' : 'Nové miesto' }}</h1>
      <p v-if="serverError" ref="errorBanner" class="text-red-600 mt-2">{{ serverError }}</p>

      <!-- AI Detect panel -->
      <div class="mt-3 rounded-xl border border-blue-200 bg-blue-50 p-4">
        <button type="button" class="flex items-center gap-2 text-sm font-semibold text-blue-700"
          @click="detectOpen = !detectOpen">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
          {{ detectOpen ? 'Skryť AI detekciu' : 'Vyplniť pomocou AI' }}
        </button>
        <div v-if="detectOpen" class="mt-3 grid gap-3">
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <FormField v-model="detectForm.name" label="Názov miesta" placeholder="napr. Kultúrny dom" />
            <FormField v-model="detectForm.city" label="Mesto / Obec" placeholder="napr. Trenčín" />
            <FormField v-model="detectForm.country" label="Krajina" placeholder="Slovensko" />
          </div>
          <div class="flex items-center gap-3">
            <button type="button" class="btn btn-primary" :disabled="detecting || !detectForm.name || !detectForm.city"
              @click="runDetect">
              {{ detecting ? 'Detekcujem…' : 'Detekovať' }}
            </button>
            <span v-if="detectError" class="text-sm text-red-600">{{ detectError }}</span>
          </div>
          <div v-if="detectResult" class="rounded-lg border border-blue-200 bg-white p-3 text-sm">
            <p class="mb-2 font-semibold text-slate-800">Výsledok detekcie:</p>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-slate-700">
              <template v-for="(val, key) in detectSummary" :key="key">
                <dt class="text-slate-500">{{ key }}</dt>
                <dd class="truncate">{{ val }}</dd>
              </template>
            </dl>
            <button type="button" class="mt-3 btn btn-primary" @click="applyDetect">Vyplniť formulár</button>
          </div>
        </div>
      </div>

      <form class="grid gap-4 mt-4" @submit.prevent="submit">
        <fieldset class="field-group">
          <legend class="field-legend">Základné info</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="form.name" label="Názov" required :error="errors.name" class="lg:col-span-2" />
            <FormField v-model="form.canal_id" type="select" label="Kanál" :error="errors.canal_id">
              <option :value="null">— vyberte kanál —</option>
              <option v-for="c in canals" :key="c.id" :value="c.id">{{ c.name }}</option>
            </FormField>
            <FormField v-model="form.status" type="select" label="Stav" :error="errors.status">
              <option value="draft">Koncept</option>
              <option value="published">Publikovaný</option>
              <option value="archived">Archivovaný</option>
            </FormField>
            <FormField v-model="form.category" label="Kategória" :error="errors.category" placeholder="napr. kultúrny dom, škola…" />
            <FormField v-model="form.capacity" type="number" label="Kapacita" min="0" :error="errors.capacity" />
            <FormField label="Popis" :error="errors.body" class="lg:col-span-2">
              <HtmlEditor v-model="form.body" min-height="130px" />
            </FormField>
          </div>
        </fieldset>

        <fieldset class="field-group">
          <legend class="field-legend">Adresa</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="form.village_id" label="Obec / Mesto" required :error="errors.village_id">
              <template #default="{ value, invalid, update }">
                <SearchableSelect
                  :model-value="value ?? null"
                  :options="municipalities"
                  placeholder="— vyberte obec —"
                  :invalid="invalid"
                  @update:model-value="update"
                />
              </template>
            </FormField>
            <FormField v-model="form.street" label="Ulica" :error="errors.street" />
            <FormField v-model="form.postcode" label="PSČ" :error="errors.postcode" />
            <FormField v-model="form.country" label="Krajina" :error="errors.country" placeholder="Slovensko" />
            <FormField v-model="form.latitude" type="number" label="Zemepisná šírka (lat)" step="any" :error="errors.latitude" />
            <FormField v-model="form.longitude" type="number" label="Zemepisná dĺžka (lng)" step="any" :error="errors.longitude" />
          </div>
        </fieldset>

        <fieldset class="field-group">
          <legend class="field-legend">Kontakt</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="form.website" type="url" label="Web" :error="errors.website">
              <template #footer>
                <AttributeIssueHint :issue="websiteIssue" label="Táto adresa" />
              </template>
            </FormField>
            <ContactEmailField
              v-model="form.email"
              target="venue"
              :target-id="fileableId"
              :state="emailVerification"
              :saved-email="savedEmail"
              label="Email"
              :error="errors.email"
              @resent="reloadEmailState"
            />
            <FormField v-model="form.phone" type="tel" label="Telefón" :error="errors.phone" />
          </div>
        </fieldset>

        <div class="flex gap-2">
          <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Ukladám…' : 'Uložiť' }}</button>
          <RouterLink :to="indexRoute" class="btn btn-secondary">Zrušiť</RouterLink>
        </div>
      </form>
    </div>

    <div class="edit-card grid gap-6">
      <div>
        <h2 class="mb-2 text-lg font-semibold text-slate-800">Poloha</h2>
        <VenueMapPicker
          :lat="form.latitude"
          :lng="form.longitude"
          @update:lat="form.latitude = $event"
          @update:lng="form.longitude = $event"
        />
        <div class="mt-2 grid grid-cols-2 gap-2">
          <FormField v-model="form.latitude" type="number" label="Lat" step="any" class="text-xs" />
          <FormField v-model="form.longitude" type="number" label="Lng" step="any" class="text-xs" />
        </div>
      </div>

      <div>
        <h2 class="mb-4 text-lg font-semibold text-slate-800">Obrázky</h2>
        <ImageManager v-if="fileableId" fileable-type="venue" :fileable-id="fileableId" />
        <ImagePicker v-else ref="picker" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showVenue, createVenue, updateVenue, detectVenue } from '@/api/venues'
import type { ContactEmailState } from '@/types'
import { uploadFiles } from '@/api/files'
import { useToast } from '@/composables/useToast'
import { useFormOptions } from '@/composables/useFormOptions'
import { provideFormValidation } from '@/composables/useFormValidation'
import { useWebsiteIssue } from '@/composables/useWebsiteIssue'
import { scrollToError } from '@/utils/scrollToError'
import AttributeIssueHint from '@/components/AttributeIssueHint.vue'
import FormField from '@/components/FormField.vue'
import ContactEmailField from '@/components/ContactEmailField.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import ImageManager from '@/components/ImageManager.vue'
import ImagePicker from '@/components/ImagePicker.vue'
import VenueMapPicker from '@/components/VenueMapPicker.vue'
import HtmlEditor from '@/components/HtmlEditor.vue'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute(); const router = useRouter(); const toast = useToast()
const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const isCreate = computed(() => !route.params.id)
const indexRoute = computed(() => `${prefix.value}/venues`)

const savedId = ref<number | null>(null)
const fileableId = computed(() => route.params.id ? Number(route.params.id) : savedId.value)
const picker = ref<InstanceType<typeof ImagePicker> | null>(null)

const { municipalities, canals, loadMunicipalities, loadCanals } = useFormOptions(scope.value)

// Overenie kontaktného e-mailu — `savedEmail` je adresa, ktorej sa stav týka.
const emailVerification = ref<ContactEmailState | null>(null)
const savedEmail = ref('')

/** Detaily o čakajúcom overení chodia len z detailu, preto sa načítajú znova. */
async function reloadEmailState() {
  const id = fileableId.value
  if (!id) return
  try {
    const v = await showVenue(scope.value, id)
    savedEmail.value = v.email ?? ''
    emailVerification.value = v.emailVerification
  } catch { /* stav overenia nie je kritický */ }
}

const validation = provideFormValidation()

const form = ref({
  name: '',
  canal_id: null as number | null,
  village_id: null as number | null,
  street: '',
  postcode: '',
  country: '',
  latitude: null as number | null,
  longitude: null as number | null,
  capacity: null as number | null,
  category: '',
  website: '',
  email: '',
  phone: '',
  body: '',
  status: 'draft',
})

const errors = ref<Record<string, string>>({})

// Upozornenie na neodpovedajúcu webovú adresu (overuje sa na pozadí,
// viď App\Services\Attributes na backende).
const { apply: applyWebsiteIssue, issue: websiteIssue } = useWebsiteIssue(() => form.value.website)
const serverError = ref<string | null>(null)
const errorBanner = ref<HTMLElement | null>(null)
const saving = ref(false)

const detectOpen = ref(false)
const detecting = ref(false)
const detectError = ref<string | null>(null)
const detectResult = ref<Record<string, unknown> | null>(null)
const detectForm = ref({ name: '', city: '', country: 'Slovensko' })

const detectSummary = computed(() => {
  const p = detectResult.value?.['venue_store_payload'] as Record<string, unknown> | undefined
  if (!p) return {}
  return Object.fromEntries(
    Object.entries(p).filter(([, v]) => v !== null && v !== '' && v !== undefined)
  )
})

async function runDetect() {
  detectError.value = null
  detectResult.value = null
  detecting.value = true
  try {
    const res = await detectVenue(detectForm.value.name, detectForm.value.city, detectForm.value.country || undefined)
    if (!(res['success'] as boolean)) throw new Error((res['error'] as string) ?? 'Detekcia zlyhala.')
    detectResult.value = res
  } catch (e: unknown) {
    detectError.value =
      (e as { response?: { data?: { message?: string } } })?.response?.data?.message ??
      (e as Error)?.message ??
      'Detekcia zlyhala.'
  } finally {
    detecting.value = false
  }
}

function applyDetect() {
  const p = detectResult.value?.['venue_store_payload'] as Record<string, unknown> | undefined
  if (!p) return
  if (p['name']) form.value.name = p['name'] as string
  if (p['street']) form.value.street = p['street'] as string
  if (p['postcode']) form.value.postcode = p['postcode'] as string
  if (p['country']) form.value.country = p['country'] as string
  if (p['latitude'] != null) form.value.latitude = p['latitude'] as number
  if (p['longitude'] != null) form.value.longitude = p['longitude'] as number
  if (p['website']) form.value.website = p['website'] as string
  if (p['email']) form.value.email = p['email'] as string
  if (p['phone']) form.value.phone = p['phone'] as string
  if (p['body']) form.value.body = p['body'] as string
  if (p['village_id'] != null) form.value.village_id = p['village_id'] as number
  detectOpen.value = false
  toast.success('Formulár vyplnený z AI detekcie.')
}

onMounted(async () => {
  loadMunicipalities()
  loadCanals()
  if (!isCreate.value) {
    try {
      const v = await showVenue(scope.value, Number(route.params.id))
      form.value = {
        name: v.name,
        canal_id: v.canalId ?? null,
        village_id: v.villageId ?? null,
        street: v.street ?? '',
        postcode: v.postcode ?? '',
        country: v.country ?? '',
        latitude: v.latitude ?? null,
        longitude: v.longitude ?? null,
        capacity: v.capacity ?? null,
        category: v.category ?? '',
        website: v.website ?? '',
        email: v.email ?? '',
        phone: v.phone ?? '',
        body: v.body ?? '',
        status: v.status,
      }
      applyWebsiteIssue(v)
      savedEmail.value = v.email ?? ''
      emailVerification.value = v.emailVerification
    } catch { serverError.value = 'Nepodarilo sa načítať.' }
  }
})

async function submit() {
  validation.markValidated()
  errors.value = {}; serverError.value = null; saving.value = true
  try {
    if (isCreate.value) {
      const v = await createVenue(form.value as Record<string, unknown>)
      savedId.value = v.id
      const pending = picker.value?.files ?? []
      if (pending.length) {
        const fd = new FormData()
        fd.append('fileable_type', 'venue')
        fd.append('fileable_id', String(v.id))
        pending.forEach(f => fd.append('files[]', f))
        await uploadFiles(fd)
      }
      toast.success('Miesto vytvorené.')
      await reloadEmailState()
      router.replace(`${prefix.value}/venues/${v.id}/edit`)
    } else {
      await updateVenue(Number(route.params.id), form.value as Record<string, unknown>)
      toast.success('Miesto uložené.')
      await reloadEmailState()
    }
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    if (resp?.errors) errors.value = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    serverError.value = resp?.message ?? 'Uloženie zlyhalo.'
    await scrollToError(errorBanner)
  } finally { saving.value = false }
}
</script>
