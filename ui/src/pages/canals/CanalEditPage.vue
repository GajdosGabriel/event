<template>
  <div class="edit-shell">
    <div class="edit-card">
      <RouterLink :to="indexRoute" class="text-sm text-blue-700 no-underline">← Späť</RouterLink>
      <h1 class="my-2 text-2xl text-slate-900">{{ savedId || !isCreate ? 'Upraviť kanál' : 'Nový kanál' }}</h1>
      <p v-if="serverError" ref="errorBanner" class="text-red-600 mt-2">{{ serverError }}</p>

      <form class="grid gap-4 mt-4" @submit.prevent="submit">
        <fieldset class="field-group">
          <legend class="field-legend">Základné info</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="form.name" label="Názov" required :error="errors.name" class="lg:col-span-2" />
            <FormField v-model="form.title_prefix" label="Predpona názvu" :error="errors.title_prefix" placeholder="napr. Spoločnosť" />
            <FormField v-model="form.title_suffix" label="Prípona názvu" :error="errors.title_suffix" placeholder="napr. o.z." />
            <FormField v-model="form.identity_mode" type="select" label="Typ identity" :options="canalIdentityModes" :error="errors.identity_mode" />
            <FormField v-model="form.municipality_id" label="Obec / Mesto" required :error="errors.municipality_id">
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
            <FormField label="Popis" :error="errors.body" class="lg:col-span-2">
              <HtmlEditor v-model="form.body" min-height="130px" />
            </FormField>
          </div>
        </fieldset>

        <fieldset class="field-group">
          <legend class="field-legend">Kontakt</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <ContactEmailField
              v-model="form.email"
              target="canal"
              :target-id="fileableId"
              :state="emailVerification"
              :saved-email="savedEmail"
              label="Email"
              :error="errors.email"
              @resent="reloadEmailState"
            />
            <FormField v-model="form.phone" type="tel" label="Telefón" :error="errors.phone" />
            <FormField v-model="form.website" type="url" label="Web" :error="errors.website">
              <template #footer>
                <AttributeIssueHint :issue="websiteIssue" label="Táto adresa" />
              </template>
            </FormField>
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
        <p class="mb-2 text-xs text-slate-500">
          Súradnice sa po uložení skúsia doplniť automaticky pomocou AI. Polohu môžeš upraviť kliknutím do mapy.
        </p>
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
        <ImageManager v-if="fileableId" fileable-type="canal" :fileable-id="fileableId" />
        <ImagePicker v-else ref="picker" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showCanal, createCanal, updateCanal } from '@/api/canals'
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
const route = useRoute()
const router = useRouter()
const toast = useToast()
const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const isCreate = computed(() => !route.params.id)
const indexRoute = computed(() => `${prefix.value}/canals`)

const savedId = ref<number | null>(null)
const fileableId = computed(() => route.params.id ? Number(route.params.id) : savedId.value)
const picker = ref<InstanceType<typeof ImagePicker> | null>(null)

const { municipalities, loadMunicipalities, canalIdentityModes, loadCanalIdentityModes } = useFormOptions(scope.value)

const validation = provideFormValidation()

const form = ref({
  name: '',
  title_prefix: '',
  title_suffix: '',
  identity_mode: 'organization',
  municipality_id: null as number | null,
  email: '',
  phone: '',
  website: '',
  latitude: null as number | null,
  longitude: null as number | null,
  body: '',
})

const errors = ref<Record<string, string>>({})

// Upozornenie na neodpovedajúcu webovú adresu (overuje sa na pozadí,
// viď App\Services\Attributes na backende).
const { apply: applyWebsiteIssue, issue: websiteIssue } = useWebsiteIssue(() => form.value.website)
const serverError = ref<string | null>(null)
const errorBanner = ref<HTMLElement | null>(null)
const saving = ref(false)

// Overenie kontaktného e-mailu. `savedEmail` je adresa, ktorej sa stav týka —
// rozpísaná zmena v poli sa ním nesmie tváriť ako overená.
const emailVerification = ref<ContactEmailState | null>(null)
const savedEmail = ref('')

/**
 * Detaily o čakajúcom overení chodia len z detailu kanála (na výpise by dotaz
 * bežal na každý riadok), preto sa po uložení načíta znova.
 */
async function reloadEmailState() {
  const id = fileableId.value
  if (!id) return
  try {
    const c = await showCanal(scope.value, id)
    applyWebsiteIssue(c)
    savedEmail.value = c.email ?? ''
    emailVerification.value = c.emailVerification
  } catch { /* stav overenia nie je kritický — formulár funguje aj bez neho */ }
}

onMounted(async () => {
  loadMunicipalities()
  loadCanalIdentityModes()
  if (!isCreate.value) {
    try {
      const c = await showCanal(scope.value, Number(route.params.id))
      form.value = {
        name: c.name,
        title_prefix: c.titlePrefix ?? '',
        title_suffix: c.titleSuffix ?? '',
        identity_mode: c.identityMode ?? 'organization',
        municipality_id: c.municipalityId ?? null,
        email: c.email ?? '',
        phone: c.phone ?? '',
        website: c.website ?? '',
        latitude: c.latitude ?? null,
        longitude: c.longitude ?? null,
        body: c.body ?? '',
      }
      applyWebsiteIssue(c)
    savedEmail.value = c.email ?? ''
      emailVerification.value = c.emailVerification
    } catch { serverError.value = 'Nepodarilo sa načítať.' }
  }
})

async function submit() {
  validation.markValidated()
  errors.value = {}; serverError.value = null; saving.value = true
  try {
    if (isCreate.value) {
      const c = await createCanal(form.value)
      savedId.value = c.id
      const pending = picker.value?.files ?? []
      if (pending.length) {
        const fd = new FormData()
        fd.append('fileable_type', 'canal')
        fd.append('fileable_id', String(c.id))
        pending.forEach(f => fd.append('files[]', f))
        await uploadFiles(fd)
      }
      toast.success('Kanál vytvorený.')
      await reloadEmailState()
      router.replace(`${prefix.value}/canals/${c.id}/edit`)
    } else {
      await updateCanal(Number(route.params.id), form.value)
      toast.success('Kanál uložený.')
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
