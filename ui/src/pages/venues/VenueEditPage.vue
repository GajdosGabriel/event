<template>
  <div class="edit-shell">
    <div class="edit-card">
      <RouterLink :to="indexRoute" class="text-sm text-blue-700 no-underline">{{ t('venues.form.back') }}</RouterLink>
      <h1 class="my-2 text-2xl text-slate-900">
        {{ fileableId ? t('venues.form.editTitle') : t('venues.form.createTitle') }}
      </h1>
      <p v-if="serverError" ref="errorBanner" class="text-red-600 mt-2">{{ serverError }}</p>

      <!-- AI Detect panel -->
      <div class="mt-3 rounded-xl border border-blue-200 bg-blue-50 p-4">
        <button type="button" class="flex items-center gap-2 text-sm font-semibold text-blue-700"
          @click="detectOpen = !detectOpen">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
          {{ detectOpen ? t('venues.detect.hide') : t('venues.detect.show') }}
        </button>
        <div v-if="detectOpen" class="mt-3 grid gap-3">
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <FormField v-model="detectForm.name" :label="t('venues.detect.name')" :placeholder="t('venues.detect.namePlaceholder')" />
            <FormField v-model="detectForm.city" :label="t('venues.detect.city')" :placeholder="t('venues.detect.cityPlaceholder')" />
            <FormField v-model="detectForm.country" :label="t('venues.detect.country')" :placeholder="t('venues.detect.countryPlaceholder')" />
          </div>
          <div class="flex items-center gap-3">
            <button type="button" class="btn btn-primary" :disabled="detecting || !detectForm.name || !detectForm.city"
              @click="runDetect">
              {{ detecting ? t('venues.detect.running') : t('venues.detect.run') }}
            </button>
            <span v-if="detectError" class="text-sm text-red-600">{{ detectError }}</span>
          </div>
          <div v-if="detectResult" class="rounded-lg border border-blue-200 bg-white p-3 text-sm">
            <p class="mb-2 font-semibold text-slate-800">{{ t('venues.detect.result') }}</p>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-slate-700">
              <template v-for="(val, key) in detectSummary" :key="key">
                <dt class="text-slate-500">{{ key }}</dt>
                <dd class="truncate">{{ val }}</dd>
              </template>
            </dl>
            <button type="button" class="mt-3 btn btn-primary" @click="applyDetect">{{ t('venues.detect.apply') }}</button>
          </div>
        </div>
      </div>

      <form class="grid gap-4 mt-4" @submit.prevent="submit">
        <fieldset class="field-group">
          <legend class="field-legend">{{ t('venues.sections.basic') }}</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="form.name" :label="t('venues.fields.name')" required :error="errors.name" class="lg:col-span-2" />
            <FormField v-model="form.canal_id" type="select" :label="t('venues.fields.canal')" :error="errors.canal_id">
              <option :value="null">{{ t('venues.fields.canalPlaceholder') }}</option>
              <option v-for="c in canals" :key="c.id" :value="c.id">{{ c.name }}</option>
            </FormField>
            <!-- Koncept = stiahnutie z výpisu. Miesto, ktoré používa podujatie,
                 sa stiahnuť nesmie — voľba zošedne a povie prečo, nech to
                 nekončí až chybou po uložení. -->
            <FormField v-model="form.status" type="select" :label="t('venues.fields.status')" :error="errors.status"
              :hint="unpublishBlockedReason ?? undefined">
              <option value="draft" :disabled="Boolean(unpublishBlockedReason)">{{ t('venues.statuses.draft') }}</option>
              <option value="published">{{ t('venues.statuses.published') }}</option>
              <option value="archived">{{ t('venues.statuses.archived') }}</option>
            </FormField>
            <FormField v-model="form.category" :label="t('venues.fields.category')" :error="errors.category" :placeholder="t('venues.fields.categoryPlaceholder')" />
            <FormField v-model="form.capacity" type="number" :label="t('venues.fields.capacity')" min="0" :error="errors.capacity" />
            <FormField :label="t('venues.fields.description')" :error="errors.body" class="lg:col-span-2">
              <HtmlEditor v-model="form.body" min-height="130px" />
            </FormField>

            <!--
              Pomocník s textom — ten istý komponent ako v podujatí a kanáli.
              Panel si sám rozhodne, čo z neho ukázať (viď AiAssistPanel.vue).
              Nad ním ostáva „AI detekcia": tá vypĺňa polia z názvu a obce,
              kým tento pracuje s hotovým popisom.
            -->
            <AiAssistPanel v-model="form.body" kind="venue" :scope="scope" :values="readinessValues"
              :name="form.name" :context="aiContext" :record-id="fileableId" class="lg:col-span-2" />
          </div>
        </fieldset>

        <AddressFieldset ref="addressFields" v-model="address" :scope="scope" :errors="errors" municipality-key="village_id" />

        <fieldset class="field-group">
          <legend class="field-legend">{{ t('venues.sections.contact') }}</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="form.website" type="url" :label="t('venues.fields.website')" :error="errors.website">
              <template #footer>
                <AttributeIssueHint :issue="websiteIssue" :label="t('venues.fields.websiteIssueLabel')" />
              </template>
            </FormField>
            <FormField v-model="form.email" type="email" :label="t('venues.fields.email')" :error="errors.email" />
            <FormField v-model="form.phone" type="tel" :label="t('venues.fields.phone')" :error="errors.phone" />
          </div>
        </fieldset>

        <div class="flex gap-2">
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? t('venues.form.saving') : t('venues.form.save') }}
          </button>
          <RouterLink :to="indexRoute" class="btn btn-secondary">{{ t('venues.form.cancel') }}</RouterLink>
        </div>
      </form>
    </div>

    <div class="edit-card grid gap-6">
      <AddressMapField v-model="address" />

      <div>
        <h2 class="mb-4 text-lg font-semibold text-slate-800">{{ t('venues.sections.images') }}</h2>
        <ImageManager v-if="fileableId" ref="imageManager" fileable-type="venue" :fileable-id="fileableId" />
        <ImagePicker v-else ref="picker" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showVenue, createVenue, updateVenue, detectVenue } from '@/api/venues'
import { addressFrom, emptyAddress, toAddressPayload } from '@/api/address'
import type { CoordinatesSource } from '@/types'
import { uploadFiles } from '@/api/files'
import { t } from '@/i18n'
import { useToast } from '@/composables/useToast'
import { useFormOptions } from '@/composables/useFormOptions'
import { useAuthStore } from '@/stores/auth'
import { provideFormValidation } from '@/composables/useFormValidation'
import { useWebsiteIssue } from '@/composables/useWebsiteIssue'
import { scrollToError } from '@/utils/scrollToError'
import AiAssistPanel from '@/components/ai/AiAssistPanel.vue'
import AddressFieldset from '@/components/AddressFieldset.vue'
import AddressMapField from '@/components/AddressMapField.vue'
import AttributeIssueHint from '@/components/AttributeIssueHint.vue'
import FormField from '@/components/FormField.vue'
import ImageManager from '@/components/ImageManager.vue'
import ImagePicker from '@/components/ImagePicker.vue'
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

const auth = useAuthStore()
const { canals, loadCanals } = useFormOptions(scope.value)

const validation = provideFormValidation()

const form = ref({
  name: '',
  canal_id: null as number | null,
  capacity: null as number | null,
  category: '',
  website: '',
  email: '',
  phone: '',
  body: '',
  status: 'draft',
})

// Adresa aj poloha žijú v AddressFieldset / AddressMapField — vrátane PSČ z
// číselníka a geokódera. Rovnaký kus formulára má aj editor kanála.
const address = ref(emptyAddress())
const addressFields = ref<InstanceType<typeof AddressFieldset> | null>(null)
const imageManager = ref<InstanceType<typeof ImageManager> | null>(null)

/**
 * Hodnoty pre ukazovateľ pripravenosti pod menami z `config/content_review.php`.
 * Obec sa musí premenovať: miesto ju má v `village_id`, kanál v
 * `municipality_id`, a konfigurácia pozná len jedno meno — rovnako ako
 * `addressFrom()` a PublishReadiness::valuesFrom() na serveri.
 */
const readinessValues = computed(() => ({
  ...form.value,
  municipality_id: address.value.municipalityId,
  image: fileableId.value ? (imageManager.value?.imageCount ?? 0) > 0 : (picker.value?.files.length ?? 0) > 0,
}))

/** Obec ako kontext pre AI — bez nej model o polohe radšej nepíše. */
const aiContext = computed(() => addressFields.value?.municipalityName ?? undefined)

const errors = ref<Record<string, string>>({})

// Upozornenie na neodpovedajúcu webovú adresu (overuje sa na pozadí,
// viď App\Services\Attributes na backende).
const { apply: applyWebsiteIssue, issue: websiteIssue } = useWebsiteIssue(() => form.value.website)
const serverError = ref<string | null>(null)
const errorBanner = ref<HTMLElement | null>(null)
const saving = ref(false)

/**
 * Prečo sa miesto nedá stiahnuť z výpisu (používa ho podujatie). Backend ho
 * počíta len publikovanému miestu; tu zošedne voľbu „Koncept", nech to nekončí
 * až chybou po uložení.
 */
const unpublishBlockedReason = ref<string | null>(null)

// Kanál pre nové miesto: aktívny kanál používateľa, inak prvý dostupný.
// Rovnaká predvoľba ako v editore eventu — organizátor s jedným kanálom ho
// nemá čo vyberať ručne.
watch(() => auth.canalId, (id) => {
  if (isCreate.value && id && !form.value.canal_id) form.value.canal_id = id
}, { immediate: true })

watch(canals, (list) => {
  if (isCreate.value && list.length > 0 && form.value.canal_id === null) {
    form.value.canal_id = list[0].id
  }
})

const detectOpen = ref(false)
const detecting = ref(false)
const detectError = ref<string | null>(null)
const detectResult = ref<Record<string, unknown> | null>(null)
const detectForm = ref({ name: '', city: '', country: t('venues.detect.countryPlaceholder') })

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
    if (!(res['success'] as boolean)) throw new Error((res['error'] as string) ?? t('venues.detect.failed'))
    detectResult.value = res
  } catch (e: unknown) {
    detectError.value =
      (e as { response?: { data?: { message?: string } } })?.response?.data?.message ??
      (e as Error)?.message ??
      t('venues.detect.failed')
  } finally {
    detecting.value = false
  }
}

function applyDetect() {
  const p = detectResult.value?.['venue_store_payload'] as Record<string, unknown> | undefined
  if (!p) return
  if (p['name']) form.value.name = p['name'] as string
  if (p['website']) form.value.website = p['website'] as string
  if (p['email']) form.value.email = p['email'] as string
  if (p['phone']) form.value.phone = p['phone'] as string
  if (p['body']) form.value.body = p['body'] as string
  address.value = {
    municipalityId: (p['village_id'] as number) ?? address.value.municipalityId,
    street: (p['street'] as string) || address.value.street,
    postcode: (p['postcode'] as string) || address.value.postcode,
    country: (p['country'] as string) || address.value.country,
    latitude: (p['latitude'] as number) ?? address.value.latitude,
    longitude: (p['longitude'] as number) ?? address.value.longitude,
    coordinatesSource: (p['coordinates_source'] as CoordinatesSource) ?? null,
  }
  detectOpen.value = false
  toast.success(t('venues.detect.applied'))

  // Detekcia obec a ulicu nájde častejšie než súradnice. Keď mapa ostane
  // prázdna, dotiahne ju geokóder z práve doplnenej adresy.
  if (address.value.latitude == null || address.value.longitude == null) {
    addressFields.value?.geocode()
  }
}

onMounted(async () => {
  loadCanals()
  if (!isCreate.value) {
    try {
      const v = await showVenue(scope.value, Number(route.params.id))
      form.value = {
        name: v.name,
        canal_id: v.canalId ?? null,
        capacity: v.capacity ?? null,
        category: v.category ?? '',
        website: v.website ?? '',
        email: v.email ?? '',
        phone: v.phone ?? '',
        body: v.body ?? '',
        status: v.status,
      }
      unpublishBlockedReason.value = v.unpublishBlockedReason
      address.value = addressFrom(v)
      applyWebsiteIssue(v)
    } catch { serverError.value = t('venues.form.loadFailed') }
  }
})

function payload(): Record<string, unknown> {
  return { ...form.value, ...toAddressPayload(address.value, 'village_id') }
}

async function submit() {
  validation.markValidated()
  errors.value = {}; serverError.value = null; saving.value = true
  try {
    if (isCreate.value) {
      const v = await createVenue(payload(), scope.value)
      savedId.value = v.id
      const pending = picker.value?.files ?? []
      if (pending.length) {
        const fd = new FormData()
        fd.append('fileable_type', 'venue')
        fd.append('fileable_id', String(v.id))
        pending.forEach(f => fd.append('files[]', f))
        await uploadFiles(fd)
      }
      toast.success(t('venues.form.created'))
      router.replace(`${prefix.value}/venues/${v.id}/edit`)
    } else {
      await updateVenue(Number(route.params.id), payload(), scope.value)
      toast.success(t('venues.form.saved'))
    }
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    if (resp?.errors) errors.value = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    serverError.value = resp?.message ?? t('venues.form.saveFailed')
    await scrollToError(errorBanner)
  } finally { saving.value = false }
}
</script>
