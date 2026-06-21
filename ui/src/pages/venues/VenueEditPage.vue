<template>
  <div class="edit-shell">
    <div class="edit-card">
      <RouterLink :to="indexRoute" class="text-sm text-blue-700 no-underline">← Späť</RouterLink>
      <h1 class="my-2 text-2xl text-slate-900">{{ fileableId ? 'Upraviť miesto' : 'Nové miesto' }}</h1>
      <p v-if="serverError" class="text-red-600 mt-2">{{ serverError }}</p>

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
              Kanál
              <select v-model="form.canal_id" class="form-input">
                <option :value="null">— vyberte kanál —</option>
                <option v-for="c in canals" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
              <span v-if="errors.canal_id" class="field-error">{{ errors.canal_id }}</span>
            </label>
            <label class="form-label">
              Stav
              <select v-model="form.status" class="form-input">
                <option value="draft">Koncept</option>
                <option value="published">Publikovaný</option>
                <option value="archived">Archivovaný</option>
              </select>
            </label>
            <label class="form-label">
              Kategória
              <input v-model="form.category" type="text" class="form-input" placeholder="napr. kultúrny dom, škola…" />
            </label>
            <label class="form-label">
              Kapacita
              <input v-model.number="form.capacity" type="number" min="0" class="form-input" />
            </label>
            <label class="form-label lg:col-span-2">
              Popis
              <textarea v-model="form.body" class="form-textarea" rows="5" />
            </label>
          </div>
        </fieldset>

        <fieldset class="field-group">
          <legend class="field-legend">Adresa</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <label class="form-label">
              Obec / Mesto *
              <select v-model="form.village_id" class="form-input" :class="{ invalid: errors.village_id }">
                <option :value="null">— vyberte obec —</option>
                <option v-for="m in municipalities" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
              <span v-if="errors.village_id" class="field-error">{{ errors.village_id }}</span>
            </label>
            <label class="form-label">
              Ulica
              <input v-model="form.street" type="text" class="form-input" />
            </label>
            <label class="form-label">
              PSČ
              <input v-model="form.postcode" type="text" class="form-input" />
            </label>
            <label class="form-label">
              Krajina
              <input v-model="form.country" type="text" class="form-input" placeholder="Slovensko" />
            </label>
            <label class="form-label">
              Zemepisná šírka (lat)
              <input v-model="form.latitude" type="number" step="any" class="form-input" />
            </label>
            <label class="form-label">
              Zemepisná dĺžka (lng)
              <input v-model="form.longitude" type="number" step="any" class="form-input" />
            </label>
          </div>
        </fieldset>

        <fieldset class="field-group">
          <legend class="field-legend">Kontakt</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <label class="form-label">
              Web
              <input v-model="form.website" type="url" class="form-input" />
            </label>
            <label class="form-label">
              Email
              <input v-model="form.email" type="email" class="form-input" />
            </label>
            <label class="form-label">
              Telefón
              <input v-model="form.phone" type="tel" class="form-input" />
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
      <ImageManager v-if="fileableId" fileable-type="venue" :fileable-id="fileableId" />
      <ImagePicker v-else ref="picker" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showVenue, createVenue, updateVenue } from '@/api/venues'
import { uploadFiles } from '@/api/files'
import { useToast } from '@/composables/useToast'
import { useFormOptions } from '@/composables/useFormOptions'
import ImageManager from '@/components/ImageManager.vue'
import ImagePicker from '@/components/ImagePicker.vue'

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
const serverError = ref<string | null>(null)
const saving = ref(false)

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
    } catch { serverError.value = 'Nepodarilo sa načítať.' }
  }
})

async function submit() {
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
      router.replace(`${prefix.value}/venues/${v.id}/edit`)
    } else {
      await updateVenue(Number(route.params.id), form.value as Record<string, unknown>)
      toast.success('Miesto uložené.')
    }
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    if (resp?.errors) errors.value = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    serverError.value = resp?.message ?? 'Uloženie zlyhalo.'
  } finally { saving.value = false }
}
</script>
