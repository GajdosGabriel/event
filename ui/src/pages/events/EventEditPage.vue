<template>
  <div class="edit-shell">
    <div class="edit-card">
      <div class="mb-4">
        <RouterLink :to="indexRoute" class="text-sm text-blue-700 no-underline">← Späť na zoznam</RouterLink>
        <h1 class="my-2 text-2xl text-slate-900">{{ fileableId ? 'Upraviť event' : 'Nový event' }}</h1>
      </div>

      <p v-if="loadingData" class="text-slate-600">Načítavam…</p>
      <p v-if="serverError" class="text-red-600 mt-2">{{ serverError }}</p>

      <form v-if="!loadingData" class="grid gap-4 mt-4" @submit.prevent="submit">
        <fieldset class="field-group">
          <legend class="field-legend">Základné info</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <label class="form-label lg:col-span-2">
              Názov *
              <input v-model="form.name" type="text" class="form-input" :class="{ invalid: errors.name }" required />
              <span v-if="errors.name" class="field-error">{{ errors.name }}</span>
            </label>
            <label class="form-label">
              Stav
              <select v-model="form.status" class="form-input">
                <option value="draft">Koncept</option>
                <option value="published">Publikovaný</option>
                <option value="archived">Archivovaný</option>
                <option value="scheduled">Naplánovaný</option>
                <option value="pending_review">Čaká na schválenie</option>
              </select>
            </label>
            <label class="form-label">
              Kanál
              <select v-model="form.canal_id" class="form-input">
                <option :value="null">— bez kanála —</option>
                <option v-for="c in canals" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </label>
            <label class="form-label">
              Miesto konania
              <select v-model="form.venue_id" class="form-input">
                <option :value="null">— bez miesta —</option>
                <option v-for="v in venues" :key="v.id" :value="v.id">{{ v.name }}</option>
              </select>
            </label>
            <label class="form-label">
              Vlastný názov miesta
              <input v-model="form.location_name" type="text" class="form-input" placeholder="ak nie je v zozname" />
            </label>
          </div>
        </fieldset>

        <fieldset class="field-group">
          <legend class="field-legend">Termín</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <label class="form-label">
              Začiatok
              <input v-model="form.start_at" type="datetime-local" class="form-input" />
            </label>
            <label class="form-label">
              Koniec
              <input v-model="form.end_at" type="datetime-local" class="form-input" />
            </label>
            <label class="form-label">
              Uzávierka registrácie
              <input v-model="form.registration_deadline_at" type="datetime-local" class="form-input" />
            </label>
          </div>
        </fieldset>

        <fieldset class="field-group">
          <legend class="field-legend">Kontakt</legend>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <label class="form-label">
              Web
              <input v-model="form.website" type="url" class="form-input" :class="{ invalid: errors.website }" />
              <span v-if="errors.website" class="field-error">{{ errors.website }}</span>
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

        <fieldset class="field-group">
          <legend class="field-legend">Popis</legend>
          <textarea v-model="form.body" class="form-textarea" rows="7" />
        </fieldset>

        <div class="flex gap-2">
          <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Ukladám…' : 'Uložiť' }}</button>
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
import { useFormOptions } from '@/composables/useFormOptions'
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

const { canals, venues, loadCanals, loadVenues } = useFormOptions(scope.value)

const form = ref({
  name: '',
  status: 'draft',
  canal_id: null as number | null,
  venue_id: null as number | null,
  location_name: '',
  start_at: '',
  end_at: '',
  registration_deadline_at: '',
  website: '',
  email: '',
  phone: '',
  body: '',
})

const errors = ref<Record<string, string>>({})
const serverError = ref<string | null>(null)
const saving = ref(false)
const loadingData = ref(false)

onMounted(async () => {
  loadCanals()
  loadVenues()
  if (!isCreate.value) {
    loadingData.value = true
    try {
      const ev = await showEvent(scope.value, Number(route.params.id))
      form.value = {
        name: ev.name,
        status: ev.status,
        canal_id: ev.canalId ?? null,
        venue_id: ev.venueId ?? null,
        location_name: ev.locationName ?? '',
        start_at: ev.startAt?.slice(0, 16) ?? '',
        end_at: ev.endAt?.slice(0, 16) ?? '',
        registration_deadline_at: (ev as Record<string, unknown>)['registrationDeadlineAt'] as string ?? '',
        website: ev.website ?? '',
        email: ev.email ?? '',
        phone: ev.phone ?? '',
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
    const payload = { ...form.value }
    if (isCreate.value) {
      const ev = await createEvent(payload)
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
      await updateEvent(Number(route.params.id), payload)
      toast.success('Event uložený.')
    }
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    if (resp?.errors) errors.value = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    serverError.value = resp?.message ?? 'Uloženie zlyhalo.'
  } finally { saving.value = false }
}
</script>
