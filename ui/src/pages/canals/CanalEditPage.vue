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
            <label class="form-label lg:col-span-2">
              Názov *
              <input v-model="form.name" type="text" class="form-input" :class="{ invalid: errors.name }" required />
              <span v-if="errors.name" class="field-error">{{ errors.name }}</span>
            </label>
            <label class="form-label">
              Predpona názvu
              <input v-model="form.title_prefix" type="text" class="form-input" :class="{ invalid: errors.title_prefix }" placeholder="napr. Spoločnosť" />
              <span v-if="errors.title_prefix" class="field-error">{{ errors.title_prefix }}</span>
            </label>
            <label class="form-label">
              Prípona názvu
              <input v-model="form.title_suffix" type="text" class="form-input" :class="{ invalid: errors.title_suffix }" placeholder="napr. o.z." />
              <span v-if="errors.title_suffix" class="field-error">{{ errors.title_suffix }}</span>
            </label>
            <label class="form-label">
              Typ identity
              <select v-model="form.identity_mode" class="form-input" :class="{ invalid: errors.identity_mode }">
                <option value="personal">Osobná</option>
                <option value="organization">Organizácia</option>
                <option value="pseudonymous">Pseudonymná</option>
              </select>
              <span v-if="errors.identity_mode" class="field-error">{{ errors.identity_mode }}</span>
            </label>
            <label class="form-label">
              Obec / Mesto *
              <SearchableSelect
                v-model="form.municipality_id"
                :options="municipalities"
                placeholder="— vyberte obec —"
                :invalid="!!errors.municipality_id"
              />
              <span v-if="errors.municipality_id" class="field-error">{{ errors.municipality_id }}</span>
            </label>
            <label class="form-label lg:col-span-2">
              Popis
              <HtmlEditor v-model="form.body" min-height="130px" />
              <span v-if="errors.body" class="field-error">{{ errors.body }}</span>
            </label>
          </div>
        </fieldset>

        <fieldset class="field-group">
          <legend class="field-legend">Kontakt</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
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
            <label class="form-label">
              Web
              <input v-model="form.website" type="url" class="form-input" :class="{ invalid: errors.website }" />
              <span v-if="errors.website" class="field-error">{{ errors.website }}</span>
            </label>
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
          <label class="form-label text-xs">Lat
            <input v-model.number="form.latitude" type="number" step="any" class="form-input" />
          </label>
          <label class="form-label text-xs">Lng
            <input v-model.number="form.longitude" type="number" step="any" class="form-input" />
          </label>
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
import { uploadFiles } from '@/api/files'
import { useToast } from '@/composables/useToast'
import { useFormOptions } from '@/composables/useFormOptions'
import { scrollToError } from '@/utils/scrollToError'
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

const { municipalities, loadMunicipalities } = useFormOptions(scope.value)

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
const serverError = ref<string | null>(null)
const errorBanner = ref<HTMLElement | null>(null)
const saving = ref(false)

onMounted(async () => {
  loadMunicipalities()
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
    } catch { serverError.value = 'Nepodarilo sa načítať.' }
  }
})

async function submit() {
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
      router.replace(`${prefix.value}/canals/${c.id}/edit`)
    } else {
      await updateCanal(Number(route.params.id), form.value)
      toast.success('Kanál uložený.')
    }
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    if (resp?.errors) errors.value = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    serverError.value = resp?.message ?? 'Uloženie zlyhalo.'
    await scrollToError(errorBanner)
  } finally { saving.value = false }
}
</script>
