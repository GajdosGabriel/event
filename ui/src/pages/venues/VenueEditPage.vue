<template>
  <div class="edit-shell">
    <div class="edit-card">
      <RouterLink :to="indexRoute" class="text-sm text-blue-700 no-underline">← Späť</RouterLink>
      <h1 class="my-2 text-2xl text-slate-900">{{ isCreate ? 'Nové miesto' : 'Upraviť miesto' }}</h1>
      <p v-if="serverError" class="text-red-600 mt-2">{{ serverError }}</p>

      <form class="grid gap-3 mt-4" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
          <label class="form-label">
            Názov *
            <input v-model="form.name" type="text" class="form-input" :class="{ invalid: errors.name }" required />
            <span v-if="errors.name" class="field-error">{{ errors.name }}</span>
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
            Kapacita
            <input v-model.number="form.capacity" type="number" class="form-input" />
          </label>
          <label class="form-label">
            Email
            <input v-model="form.email" type="email" class="form-input" />
          </label>
          <label class="form-label">
            Telefón
            <input v-model="form.phone" type="tel" class="form-input" />
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
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showVenue, createVenue, updateVenue } from '@/api/venues'
import { useToast } from '@/composables/useToast'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute(); const router = useRouter(); const toast = useToast()
const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const isCreate = computed(() => !route.params.id)
const indexRoute = computed(() => `${prefix.value}/venues`)

const form = ref({ name: '', street: '', postcode: '', capacity: null as number | null, email: '', phone: '', website: '', body: '', status: 'draft' })
const errors = ref<Record<string, string>>({})
const serverError = ref<string | null>(null)
const saving = ref(false)

onMounted(async () => {
  if (!isCreate.value) {
    try {
      const v = await showVenue('dashboard', Number(route.params.id))
      form.value = { name: v.name, street: v.street ?? '', postcode: v.postcode ?? '', capacity: v.capacity, email: v.email ?? '', phone: v.phone ?? '', website: v.website ?? '', body: v.body ?? '', status: v.status }
    } catch { serverError.value = 'Nepodarilo sa načítať.' }
  }
})

async function submit() {
  errors.value = {}; serverError.value = null; saving.value = true
  try {
    if (isCreate.value) {
      const v = await createVenue(form.value as Record<string, unknown>)
      toast.success('Miesto vytvorené.')
      router.push(`${prefix.value}/venues/${v.id}`)
    } else {
      await updateVenue(Number(route.params.id), form.value as Record<string, unknown>)
      toast.success('Miesto uložené.')
      router.push(`${prefix.value}/venues/${route.params.id}`)
    }
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    if (resp?.errors) errors.value = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    serverError.value = resp?.message ?? 'Uloženie zlyhalo.'
  } finally { saving.value = false }
}
</script>
