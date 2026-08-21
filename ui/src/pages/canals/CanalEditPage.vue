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
            <FormField v-model="form.status" type="select" :label="t('canals.fields.status')" :error="errors.status">
              <option value="draft">{{ t('canals.statuses.draft') }}</option>
              <option value="published">{{ t('canals.statuses.published') }}</option>
              <option value="archived">{{ t('canals.statuses.archived') }}</option>
            </FormField>
            <FormField v-model="form.municipality_id" :label="t('canals.fields.municipality')" required :error="errors.municipality_id">
              <template #default="{ value, invalid, update }">
                <SearchableSelect
                  :model-value="value ?? null"
                  :options="municipalities"
                  :placeholder="t('canals.fields.municipalityPlaceholder')"
                  :invalid="invalid"
                  @update:model-value="update"
                />
              </template>
            </FormField>
            <FormField :label="t('canals.fields.description')" :error="errors.body" class="lg:col-span-2">
              <HtmlEditor v-model="form.body" min-height="130px" />
            </FormField>
          </div>
        </fieldset>

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
      <div>
        <h2 class="mb-2 text-lg font-semibold text-slate-800">{{ t('canals.sections.location') }}</h2>
        <p class="mb-2 text-xs text-slate-500">
          {{ t('canals.locationHint') }}
        </p>
        <VenueMapPicker
          :lat="form.latitude"
          :lng="form.longitude"
          @update:lat="form.latitude = $event"
          @update:lng="form.longitude = $event"
        />
        <div class="mt-2 grid grid-cols-2 gap-2">
          <FormField v-model="form.latitude" type="number" :label="t('canals.fields.lat')" step="any" class="text-xs" />
          <FormField v-model="form.longitude" type="number" :label="t('canals.fields.lng')" step="any" class="text-xs" />
        </div>
      </div>

      <div>
        <h2 class="mb-4 text-lg font-semibold text-slate-800">{{ t('canals.sections.images') }}</h2>
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
import { uploadFiles } from '@/api/files'
import { t } from '@/i18n'
import { useToast } from '@/composables/useToast'
import { useFormOptions } from '@/composables/useFormOptions'
import { provideFormValidation } from '@/composables/useFormValidation'
import { useWebsiteIssue } from '@/composables/useWebsiteIssue'
import { scrollToError } from '@/utils/scrollToError'
import AttributeIssueHint from '@/components/AttributeIssueHint.vue'
import FormField from '@/components/FormField.vue'
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
  // Kanál doteraz pole stavu nemal a ostával na DB defaulte `published`.
  // Nový kanál je koncept — publikuje sa až vtedy, keď ho niekto naozaj chce
  // mať vonku (alebo automaticky s prvým publikovaným podujatím).
  status: 'draft',
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

/** Po uložení sa upozornenie na web načíta znova — adresa sa mohla zmeniť. */
async function reloadWebsiteIssue() {
  const id = fileableId.value
  if (!id) return
  try {
    applyWebsiteIssue(await showCanal(scope.value, id))
  } catch { /* upozornenie nie je kritické — formulár funguje aj bez neho */ }
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
        status: c.status ?? 'draft',
        municipality_id: c.municipalityId ?? null,
        email: c.email ?? '',
        phone: c.phone ?? '',
        website: c.website ?? '',
        latitude: c.latitude ?? null,
        longitude: c.longitude ?? null,
        body: c.body ?? '',
      }
      applyWebsiteIssue(c)
    } catch { serverError.value = t('canals.form.loadFailed') }
  }
})

async function submit() {
  validation.markValidated()
  errors.value = {}; serverError.value = null; saving.value = true
  try {
    if (isCreate.value) {
      const c = await createCanal(form.value, scope.value)
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
      await updateCanal(Number(route.params.id), form.value, scope.value)
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
