<template>
  <div class="edit-shell">
    <div class="edit-card">
      <EditPageHeader :back-to="indexRoute" :back-label="t('canals.form.back')" kind="canal" :scope="scope" :values="readinessValues"
        :title="savedId || !isCreate ? t('canals.form.editTitle') : t('canals.form.createTitle')" />
      <p v-if="serverError" ref="errorBanner" class="text-red-600 mt-2">{{ serverError }}</p>

      <form class="grid gap-4 mt-4" @submit.prevent="submit">
        <SimilarRecords :scope="scope" resource="canals" :name="form.name" :exclude-id="route.params.id ? Number(route.params.id) : null" :municipality="address.municipalityId" />
        <fieldset class="field-group">
          <legend class="field-legend">{{ t('canals.sections.basic') }}</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="form.name" :label="t('canals.fields.name')" required :error="errors.name" class="lg:col-span-2" />
            <FormField v-model="form.title_prefix" :label="t('canals.fields.titlePrefix')" :error="errors.title_prefix" :placeholder="t('canals.fields.titlePrefixPlaceholder')" />
            <FormField v-model="form.title_suffix" :label="t('canals.fields.titleSuffix')" :error="errors.title_suffix" :placeholder="t('canals.fields.titleSuffixPlaceholder')" />
            <FormField v-model="form.identity_mode" type="select" :label="t('canals.fields.identityMode')" :options="canalIdentityModes" :error="errors.identity_mode" />
            <RecordStatusField v-model="form.status" kind="canals" :error="errors.status" :blocked-reason="unpublishBlockedReason" />
            <!-- Editor + AI pomocník (poznámky z kontroly, pripravenosť, vylepšenie) v jednom komponente. -->
            <DescriptionField v-model="form.body" :label="t('canals.fields.description')" :error="errors.body" min-height="130px"
              kind="canal" :scope="scope" :values="readinessValues" :name="form.name" :context="aiContext"
              :record-id="fileableId" class="lg:col-span-2" />
          </div>
        </fieldset>

        <AddressFieldset ref="addressFields" v-model="address" :scope="scope" :errors="errors" municipality-key="municipality_id" />

        <ContactFieldset v-model:email="form.email" v-model:phone="form.phone" v-model:website="form.website"
          kind="canals" :errors="errors" :website-issue="websiteIssue" />

        <RecordFormActions kind="canals" :saving="saving" :cancel-to="indexRoute" />
      </form>
    </div>

    <div class="edit-card grid gap-6">
      <RecordImages ref="images" kind="canals" fileable-type="canal" :fileable-id="fileableId" />

      <!-- Tím sa spravuje tu, kde sa upravuje kanál — detail naň už len
           odkazuje. V admine nie: tam sa používatelia riešia inde. Nový kanál
           ešte nemá komu poslať pozvánku, preto až po uložení. -->
      <CanalTeamPanel v-if="scope === 'dashboard' && fileableId" :canal-id="fileableId" />

      <AddressMapField v-model="address" />
    </div>
  </div>
</template>

<script setup lang="ts">
import SimilarRecords from '@/components/SimilarRecords.vue'
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { addressFrom, emptyAddress, toAddressPayload } from '@/api/address'
import { showCanal, createCanal, updateCanal } from '@/api/canals'
import { uploadFiles } from '@/api/files'
import { t } from '@/i18n'
import { useToast } from '@/composables/useToast'
import { useFormOptions } from '@/composables/useFormOptions'
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
import CanalTeamPanel from '@/components/CanalTeamPanel.vue'
import FormField from '@/components/FormField.vue'

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

const { canalIdentityModes, loadCanalIdentityModes } = useFormOptions(scope.value)

const validation = provideFormValidation()

const form = ref({
  name: '',
  title_prefix: '',
  title_suffix: '',
  identity_mode: 'organization',
  // Kanál doteraz pole stavu nemal a ostával na DB defaulte `published`.
  // Nový kanál je koncept — publikuje sa až vtedy, keď ho niekto naozaj chce
  // mať vonku (alebo automaticky s prvým publikovaným podujatím).
  status: 'draft',
  email: '',
  phone: '',
  website: '',
  body: '',
})

// Adresa sídla kanála. Rovnaký tvar aj rovnaký editor ako pri mieste — vrátane
// PSČ z číselníka a polohy, ktorá ide za adresou.
const address = ref(emptyAddress())
const addressFields = ref<InstanceType<typeof AddressFieldset> | null>(null)
const images = ref<InstanceType<typeof RecordImages> | null>(null)

/**
 * Hodnoty pre ukazovateľ pripravenosti pod menami z `config/content_review.php`
 * — jediného miesta, kde je napísané, čo znamená „hotové".
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
 * Prečo sa kanál nedá stiahnuť z výpisu (odkazuje naň podujatie). Backend ho
 * počíta len publikovanému kanálu; tu zošedne voľbu „Koncept", nech to
 * nekončí až chybou po uložení.
 */
const unpublishBlockedReason = ref<string | null>(null)

/** Po uložení sa upozornenie na web načíta znova — adresa sa mohla zmeniť. */
async function reloadWebsiteIssue() {
  const id = fileableId.value
  if (!id) return
  try {
    applyWebsiteIssue(await showCanal(scope.value, id))
  } catch { /* upozornenie nie je kritické — formulár funguje aj bez neho */ }
}

onMounted(async () => {
  loadCanalIdentityModes()
  if (!isCreate.value) {
    try {
      const c = await showCanal(scope.value, Number(route.params.id))
      form.value = {
        name: c.name,
        title_prefix: c.titlePrefix ?? '',
        title_suffix: c.titleSuffix ?? '',
        identity_mode: c.identityMode ?? 'organization',
        status: c.status ?? 'draft',
        email: c.email ?? '',
        phone: c.phone ?? '',
        website: c.website ?? '',
        body: c.body ?? '',
      }
      unpublishBlockedReason.value = c.unpublishBlockedReason
      address.value = addressFrom(c)
      applyWebsiteIssue(c)
    } catch { serverError.value = t('canals.form.loadFailed') }
  }
})

function payload() {
  return { ...form.value, ...toAddressPayload(address.value, 'municipality_id') }
}

async function submit() {
  validation.markValidated()
  errors.value = {}; serverError.value = null; saving.value = true
  try {
    if (isCreate.value) {
      const c = await createCanal(payload(), scope.value)
      savedId.value = c.id
      const pending = images.value?.pendingFiles ?? []
      if (pending.length) {
        const fd = new FormData()
        fd.append('fileable_type', 'canal')
        fd.append('fileable_id', String(c.id))
        pending.forEach(f => fd.append('files[]', f))
        await uploadFiles(fd)
      }
      toast.success(t('canals.form.created'))
      await reloadWebsiteIssue()
      router.replace(`${prefix.value}/canals/${c.id}/edit`)
    } else {
      await updateCanal(Number(route.params.id), payload(), scope.value)
      toast.success(t('canals.form.saved'))
      await reloadWebsiteIssue()
    }
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    if (resp?.errors) errors.value = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    serverError.value = resp?.message ?? t('canals.form.saveFailed')
    await scrollToError(errorBanner)
  } finally { saving.value = false }
}
</script>
