<template>
  <div class="edit-shell">
    <div class="edit-card">
      <RouterLink :to="indexRoute" class="text-sm text-blue-700 no-underline">← Späť</RouterLink>
      <h1 class="my-2 text-2xl text-slate-900">{{ savedId || !isCreate ? 'Upraviť kanál' : 'Nový kanál' }}</h1>
      <p v-if="serverError" class="text-red-600 mt-2">{{ serverError }}</p>

      <form class="grid gap-3 mt-4" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
          <label class="form-label">
            Názov *
            <input v-model="form.name" type="text" class="form-input" :class="{ invalid: errors.name }" required />
            <span v-if="errors.name" class="field-error">{{ errors.name }}</span>
          </label>
          <label class="form-label">
            Email
            <input v-model="form.email" type="email" class="form-input" />
          </label>
          <label class="form-label">
            Web
            <input v-model="form.website" type="url" class="form-input" />
          </label>
          <label class="form-label">
            Stav
            <select v-model="form.status" class="form-input">
              <option value="draft">Návrh</option>
              <option value="published">Publikované</option>
            </select>
          </label>
          <label class="form-label lg:col-span-2">
            Popis
            <textarea v-model="form.body" class="form-textarea" rows="5" />
          </label>
        </div>
        <div class="flex gap-2 mt-2">
          <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Ukladám…' : 'Uložiť' }}</button>
          <RouterLink :to="indexRoute" class="btn btn-secondary">Zrušiť</RouterLink>
        </div>
      </form>
    </div>

    <div class="edit-card">
      <h2 class="mb-4 text-lg font-semibold text-slate-800">Obrázky</h2>
      <ImageManager v-if="fileableId" fileable-type="canal" :fileable-id="fileableId" />
      <ImagePicker v-else ref="picker" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showCanal, createCanal, updateCanal } from '@/api/canals'
import { uploadFiles } from '@/api/files'
import { useToast } from '@/composables/useToast'
import ImageManager from '@/components/ImageManager.vue'
import ImagePicker from '@/components/ImagePicker.vue'

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

const form = ref({ name: '', email: '', website: '', body: '', status: 'draft' })
const errors = ref<Record<string, string>>({})
const serverError = ref<string | null>(null)
const saving = ref(false)

onMounted(async () => {
  if (!isCreate.value) {
    try {
      const c = await showCanal('dashboard', Number(route.params.id))
      form.value = { name: c.name, email: c.email ?? '', website: c.website ?? '', body: c.body ?? '', status: c.status }
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
  } finally { saving.value = false }
}
</script>
