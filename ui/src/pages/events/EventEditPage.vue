<template>
  <div class="edit-shell">
    <div class="edit-card">
      <div class="mb-4">
        <RouterLink :to="indexRoute" class="text-sm text-blue-700 no-underline">← Späť na zoznam</RouterLink>
        <h1 class="my-2 text-2xl text-slate-900">{{ fileableId ? 'Upraviť event' : 'Nový event' }}</h1>
      </div>

      <p v-if="loadingData" class="text-slate-600">Načítavam…</p>
      <p v-if="serverError" class="text-red-600 mt-2">{{ serverError }}</p>

      <form v-if="!loadingData" class="grid gap-3 mt-4" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
          <label class="form-label">
            Názov *
            <input v-model="form.name" type="text" class="form-input" :class="{ invalid: errors.name }" required />
            <span v-if="errors.name" class="field-error">{{ errors.name }}</span>
          </label>

          <label class="form-label">
            Stav
            <select v-model="form.status" class="form-input">
              <option value="draft">Návrh</option>
              <option value="published">Publikované</option>
              <option value="archived">Archivované</option>
            </select>
          </label>

          <label class="form-label">
            Začiatok
            <input v-model="form.start_at" type="datetime-local" class="form-input" />
          </label>

          <label class="form-label">
            Koniec
            <input v-model="form.end_at" type="datetime-local" class="form-input" />
          </label>

          <label class="form-label">
            Miesto
            <input v-model="form.location_name" type="text" class="form-input" />
          </label>

          <label class="form-label">
            Web
            <input v-model="form.website" type="url" class="form-input" />
          </label>

          <label class="form-label lg:col-span-2">
            Popis
            <textarea v-model="form.body" class="form-textarea" rows="6" />
          </label>
        </div>

        <div class="flex gap-2 mt-2">
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? 'Ukladám…' : 'Uložiť' }}
          </button>
          <RouterLink :to="indexRoute" class="btn btn-secondary">Zrušiť</RouterLink>
        </div>
      </form>
    </div>

    <div class="edit-card">
      <h2 class="mb-4 text-lg font-semibold text-slate-800">Obrázky</h2>
      <ImageManager v-if="fileableId" fileable-type="event" :fileable-id="fileableId" />
      <ImagePicker v-else ref="picker" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showEvent, createEvent, updateEvent } from '@/api/events'
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
const indexRoute = computed(() => `${prefix.value}/events`)

const savedId = ref<number | null>(null)
const fileableId = computed(() => route.params.id ? Number(route.params.id) : savedId.value)
const picker = ref<InstanceType<typeof ImagePicker> | null>(null)

const form = ref({ name: '', status: 'draft', start_at: '', end_at: '', location_name: '', website: '', body: '' })
const errors = ref<Record<string, string>>({})
const serverError = ref<string | null>(null)
const saving = ref(false)
const loadingData = ref(false)

onMounted(async () => {
  if (!isCreate.value) {
    loadingData.value = true
    try {
      const ev = await showEvent('dashboard', Number(route.params.id))
      form.value = {
        name: ev.name,
        status: ev.status,
        start_at: ev.startAt?.slice(0, 16) ?? '',
        end_at: ev.endAt?.slice(0, 16) ?? '',
        location_name: ev.locationName ?? '',
        website: ev.website ?? '',
        body: ev.body ?? '',
      }
    } catch { serverError.value = 'Event sa nepodarilo načítať.' }
    finally { loadingData.value = false }
  }
})

async function submit() {
  errors.value = {}
  serverError.value = null
  saving.value = true
  try {
    if (isCreate.value) {
      const ev = await createEvent(form.value)
      savedId.value = ev.id
      const pending = picker.value?.files ?? []
      if (pending.length) {
        const fd = new FormData()
        fd.append('fileable_type', 'event')
        fd.append('fileable_id', String(ev.id))
        pending.forEach(f => fd.append('files[]', f))
        await uploadFiles(fd)
      }
      toast.success('Event vytvorený.')
      router.replace(`${prefix.value}/events/${ev.id}/edit`)
    } else {
      await updateEvent(Number(route.params.id), form.value)
      toast.success('Event uložený.')
    }
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    if (resp?.errors) {
      errors.value = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    }
    serverError.value = resp?.message ?? 'Uloženie zlyhalo.'
  } finally { saving.value = false }
}
</script>
