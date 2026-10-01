<template>
  <div class="edit-shell">
    <div class="edit-card">
      <EditPageHeader :back-to="indexRoute" :back-label="t('venues.form.back')" kind="venue" :scope="scope" :values="readinessValues"
        :title="fileableId ? t('venues.form.editTitle') : t('venues.form.createTitle')" />
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
        <SimilarRecords :scope="scope" resource="venues" :name="form.name" :exclude-id="route.params.id ? Number(route.params.id) : null" :municipality="address.municipalityId" />
        <fieldset class="field-group">
          <legend class="field-legend">{{ t('venues.sections.basic') }}</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="form.name" :label="t('venues.fields.name')" required :error="errors.name" class="lg:col-span-2" />
            <!-- Správca miesta = kanál, ktorý smie miesto upravovať (canal_venue.is_owner).
                 Nie je to nájomca: kanály, ktorých podujatia sa tu konajú, sa
                 pripájajú samy. V dashboarde ostáva povinný (bez neho by si
                 používateľ miesto založil a stratil k nemu prístup), admin
                 ho môže nechať prázdny — miesto potom spravuje on. -->
            <FormField v-model="form.owner_canal_id" :label="t('venues.fields.ownerCanal')" :required="scope !== 'admin'"
              :hint="t('venues.fields.ownerCanalHint')" :error="errors.owner_canal_id ?? errors.canal_id" class="lg:col-span-2">
              <template #default="{ value, invalid, update }">
                <SearchableSelect :model-value="value ?? null" :options="canalOptions" :source="`/${scope}/canals`" :invalid="invalid" @update:model-value="update" />
                <button v-if="scope === 'admin' && value" type="button" class="mt-1 text-sm text-blue-700" @click="update(null)">
                  {{ t('venues.fields.ownerCanalNone') }}
                </button>
              </template>
            </FormField>
            <RecordStatusField v-model="form.status" kind="venues" :error="errors.status" :blocked-reason="unpublishBlockedReason" />
            <FormField v-model="form.category" :label="t('venues.fields.category')" :error="errors.category" :placeholder="t('venues.fields.categoryPlaceholder')" />
            <FormField v-model="form.capacity" type="number" :label="t('venues.fields.capacity')" min="0" max="1000000" step="1" :error="errors.capacity" />
            <!-- Editor + AI pomocník (poznámky z kontroly, pripravenosť, vylepšenie) v jednom komponente. -->
            <DescriptionField v-model="form.body" :label="t('venues.fields.description')" :error="errors.body" min-height="130px"
              kind="venue" :scope="scope" :values="readinessValues" :name="form.name" :context="aiContext"
              :record-id="fileableId" class="lg:col-span-2" />
          </div>
        </fieldset>

        <AddressFieldset ref="addressFields" v-model="address" :scope="scope" :errors="errors" municipality-key="village_id" />

        <ContactFieldset v-model:email="form.email" v-model:phone="form.phone" v-model:website="form.website"
          kind="venues" :errors="errors" :website-issue="websiteIssue" />

        <RecordFormActions kind="venues" :saving="saving" :cancel-to="indexRoute" />
      </form>
    </div>

    <div class="edit-card grid gap-6">
      <RecordImages ref="images" kind="venues" fileable-type="venue" :fileable-id="fileableId" />

      <AddressMapField v-model="address" :errors="errors" />
    </div>
  </div>
</template>

<script setup lang="ts">
import SimilarRecords from '@/components/SimilarRecords.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showVenue, createVenue, updateVenue, detectVenue } from '@/api/venues'
import { addressFrom, emptyAddress, toAddressPayload } from '@/api/address'
import type { CoordinatesSource } from '@/types'
import { uploadFiles } from '@/api/files'
import { t } from '@/i18n'
import { useToast } from '@/composables/useToast'
import { useFormOptions, type SelectOption } from '@/composables/useFormOptions'
import { useAuthStore } from '@/stores/auth'
import { provideFormValidation } from '@/composables/useFormValidation'
import { useWebsiteIssue } from '@/composables/useWebsiteIssue'
import { scrollToError } from '@/utils/scrollToError'
import DescriptionField from '@/components/ai/DescriptionField.vue'
import EditPageHeader from '@/components/EditPageHeader.vue'
import AddressFieldset from '@/components/AddressFieldset.vue'
import AddressMapField from '@/components/AddressMapField.vue'
import ContactFieldset from '@/components/ContactFieldset.vue'
import RecordFormActions from '@/components/RecordFormActions.vue'
import RecordImages from '@/components/RecordImages.vue'
import RecordStatusField from '@/components/RecordStatusField.vue'
import FormField from '@/components/FormField.vue'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute(); const router = useRouter(); const toast = useToast()
const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const isCreate = computed(() => !route.params.id)
const indexRoute = computed(() => `${prefix.value}/venues`)

const savedId = ref<number | null>(null)
const fileableId = computed(() => route.params.id ? Number(route.params.id) : savedId.value)

const auth = useAuthStore()
const { canals, loadCanals } = useFormOptions(scope.value)

const validation = provideFormValidation()

const form = ref({
  name: '',
  owner_canal_id: null as number | null,
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
const images = ref<InstanceType<typeof RecordImages> | null>(null)

/**
 * Hodnoty pre ukazovateľ pripravenosti pod menami z `config/content_review.php`.
 * Obec sa musí premenovať: miesto ju má v `village_id`, kanál v
 * `municipality_id`, a konfigurácia pozná len jedno meno — rovnako ako
 * `addressFrom()` a PublishReadiness::valuesFrom() na serveri.
 */
const readinessValues = computed(() => ({
  ...form.value,
  municipality_id: address.value.municipalityId,
  image: images.value?.hasImages ?? false,
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
  if (isCreate.value && scope.value !== 'admin' && id && !form.value.owner_canal_id) form.value.owner_canal_id = id
}, { immediate: true })

watch(canals, (list) => {
  if (isCreate.value && scope.value !== 'admin' && list.length > 0 && form.value.owner_canal_id === null) {
    form.value.owner_canal_id = list[0].id
  }
})

// Kanál práve upravovaného miesta. `loadCanals()` ťahá len prvú stránku
// kanálov — keď v nej chýba, select by ostal prázdny, hoci miesto kanál má.
const venueCanal = ref<SelectOption | null>(null)

const canalOptions = computed(() => {
  const own = venueCanal.value
  if (!own || canals.value.some(c => c.id === own.id)) return canals.value
  return [own, ...canals.value]
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
        owner_canal_id: v.ownerCanalId,
        capacity: v.capacity ?? null,
        category: v.category ?? '',
        website: v.website ?? '',
        email: v.email ?? '',
        phone: v.phone ?? '',
        body: v.body ?? '',
        status: v.status,
      }
      const own = v.canalsList.find(c => c.id === v.ownerCanalId)
      venueCanal.value = own ? { id: own.id, name: own.name } : null
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
      const pending = images.value?.pendingFiles ?? []
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
