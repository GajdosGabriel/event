<template>
  <div class="edit-shell">
    <div class="edit-card">
      <RouterLink :to="indexRoute" class="text-sm text-blue-700 no-underline">{{ t('canals.form.back') }}</RouterLink>
      <h1 class="my-2 text-2xl text-slate-900">
        {{ savedId || !isCreate ? t('canals.form.editTitle') : t('canals.form.createTitle') }}
      </h1>
      <p v-if="serverError" ref="errorBanner" class="text-red-600 mt-2">{{ serverError }}</p>

      <form class="grid gap-4 mt-4" @submit.prevent="submit">
        <fieldset class="field-group">
          <legend class="field-legend">{{ t('canals.sections.basic') }}</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="form.name" :label="t('canals.fields.name')" required :error="errors.name" class="lg:col-span-2" />
            <FormField v-model="form.title_prefix" :label="t('canals.fields.titlePrefix')" :error="errors.title_prefix" :placeholder="t('canals.fields.titlePrefixPlaceholder')" />
            <FormField v-model="form.title_suffix" :label="t('canals.fields.titleSuffix')" :error="errors.title_suffix" :placeholder="t('canals.fields.titleSuffixPlaceholder')" />
            <FormField v-model="form.identity_mode" type="select" :label="t('canals.fields.identityMode')" :options="canalIdentityModes" :error="errors.identity_mode" />
            <!-- Koncept = stiahnutie z výpisu. Kanál, na ktorý odkazuje
                 podujatie, sa stiahnuť nesmie — voľba zošedne a povie prečo,
                 nech to nekončí až chybou po uložení. -->
            <FormField v-model="form.status" type="select" :label="t('canals.fields.status')" :error="errors.status"
              :hint="unpublishBlockedReason ?? undefined">
              <option value="draft" :disabled="Boolean(unpublishBlockedReason)">{{ t('canals.statuses.draft') }}</option>
              <option value="published">{{ t('canals.statuses.published') }}</option>
              <option value="archived">{{ t('canals.statuses.archived') }}</option>
            </FormField>
            <FormField :label="t('canals.fields.description')" :error="errors.body" class="lg:col-span-2">
              <HtmlEditor v-model="form.body" min-height="130px" />
            </FormField>

            <!--
              Pomocník s textom — ten istý komponent ako v podujatí a mieste.
              Panel si sám rozhodne, čo z neho ukázať (viď AiAssistPanel.vue).
            -->
            <AiAssistPanel v-model="form.body" kind="canal" :scope="scope" :values="readinessValues"
              :name="form.name" :context="aiContext" :record-id="fileableId" class="lg:col-span-2" />
          </div>
        </fieldset>

        <AddressFieldset ref="addressFields" v-model="address" :scope="scope" :errors="errors" municipality-key="municipality_id" />

        <fieldset class="field-group">
          <legend class="field-legend">{{ t('canals.sections.contact') }}</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="form.email" type="email" :label="t('canals.fields.email')" :error="errors.email" />
            <FormField v-model="form.phone" type="tel" :label="t('canals.fields.phone')" :error="errors.phone" />
            <FormField v-model="form.website" type="url" :label="t('canals.fields.website')" :error="errors.website">
              <template #footer>
                <AttributeIssueHint :issue="websiteIssue" :label="t('canals.fields.websiteIssueLabel')" />
              </template>
            </FormField>
          </div>
        </fieldset>

        <div class="flex gap-2">
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? t('canals.form.saving') : t('canals.form.save') }}
          </button>
          <RouterLink :to="indexRoute" class="btn btn-secondary">{{ t('canals.form.cancel') }}</RouterLink>
        </div>
      </form>
    </div>

    <div class="edit-card grid gap-6">
      <AddressMapField v-model="address" />

      <div>
        <h2 class="mb-4 text-lg font-semibold text-slate-800">{{ t('canals.sections.images') }}</h2>
        <ImageManager v-if="fileableId" ref="imageManager" fileable-type="canal" :fileable-id="fileableId" />
        <ImagePicker v-else ref="picker" />
      </div>

      <!-- Tím sa spravuje tu, kde sa upravuje kanál — detail naň už len
           odkazuje. V admine nie: tam sa používatelia riešia inde. Nový kanál
           ešte nemá komu poslať pozvánku, preto až po uložení. -->
      <CanalTeamPanel v-if="scope === 'dashboard' && fileableId" :canal-id="fileableId" />
    </div>
  </div>
</template>

<script setup lang="ts">
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
import AiAssistPanel from '@/components/ai/AiAssistPanel.vue'
import AddressFieldset from '@/components/AddressFieldset.vue'
import AddressMapField from '@/components/AddressMapField.vue'
import AttributeIssueHint from '@/components/AttributeIssueHint.vue'
import CanalTeamPanel from '@/components/CanalTeamPanel.vue'
import FormField from '@/components/FormField.vue'
import ImageManager from '@/components/ImageManager.vue'
import ImagePicker from '@/components/ImagePicker.vue'
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
const imageManager = ref<InstanceType<typeof ImageManager> | null>(null)

/**
 * Hodnoty pre ukazovateľ pripravenosti pod menami z `config/content_review.php`
 * — jediného miesta, kde je napísané, čo znamená „hotové".
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
      const pending = picker.value?.files ?? []
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
