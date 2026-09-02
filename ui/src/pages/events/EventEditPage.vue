<template>
  <div class="edit-shell">
    <!-- Hlavička stránky. Odkaz na verejný detail je tu preto, aby sa dala
         úprava hneď skontrolovať — pri vytváraní ešte nie je čo zobraziť. -->
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
      <div>
        <RouterLink :to="indexRoute" class="text-sm text-blue-700 no-underline">{{ t('events.form.back') }}</RouterLink>
        <h1 class="my-2 text-2xl text-slate-900">
          {{ fileableId ? t('events.form.editTitle') : t('events.form.createTitle') }}
        </h1>
      </div>
      <RouterLink v-if="fileableId" :to="`${prefix}/events/${fileableId}`" class="btn btn-secondary">
        {{ t('events.form.view') }}
      </RouterLink>
    </div>

    <p v-if="loadingData" class="text-slate-600">{{ t('events.form.loading') }}</p>
    <p v-if="serverError" ref="errorBanner" class="mb-4 text-red-600">{{ serverError }}</p>

    <!--
      Obsah vľavo, nastavenia v lepkavom paneli vpravo. Celá mriežka je vnútri
      jedného <form>: tlačidlo Uložiť síce sedí v paneli, ale odosiela natívne
      a prehliadač zvaliduje povinné polia z oboch stĺpcov naraz.
    -->
    <form v-if="!loadingData" class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]" @submit.prevent="submit">
      <!-- ── Ľavý stĺpec: to, čo sa píše ──────────────────────────────── -->
      <div class="grid gap-5">
        <div class="edit-card grid gap-4">
          <FormField v-model="form.name" :label="t('events.fields.name')" required :error="errors.name" />

          <!-- Popis je obalený vo FormField len kvôli popiske a chybe zo
               servera — kým tu FormField nebol, chyba na `body` sa nemala kde
               zobraziť a človek videl iba všeobecný banner. -->
          <FormField :label="t('events.sections.description')" :error="errors.body">
            <HtmlEditor v-model="form.body" :placeholder="t('events.fields.bodyPlaceholder')" min-height="260px" />
          </FormField>

          <!--
            Pomocník s textom. Panel si sám rozhodne, čo z neho ukázať —
            ukazovateľ pripravenosti, poznámky z kontroly po zverejnení alebo
            samotnú AI (viď AiAssistPanel.vue).
          -->
          <AiAssistPanel v-model="form.body" kind="event" :scope="scope" :values="readinessValues"
            :name="form.name" :record-id="fileableId" />
        </div>

        <div class="edit-card">
          <p class="field-legend">{{ t('events.sections.schedule') }}</p>
          <!-- `allow-past` je tu podstatné: bez neho dá DateTimeInput na pole
               `min="teraz"`, termín v minulosti je natívne neplatný a
               prehliadač odoslanie formulára ticho zablokuje — event sa
               jednoducho nedá uložiť a nie je vidieť prečo. -->
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField v-model="form.start_at" type="datetime" :allow-past="true" :label="t('events.fields.startAt')" :error="errors.start_at" />
            <FormField v-model="form.end_at" type="datetime" :allow-past="true" :label="t('events.fields.endAt')" :error="errors.end_at" />
          </div>
        </div>

        <!-- Kontakt sa pri bežnej úprave neotvára, tak je zbalený. Chyba zo
             servera ho otvorí za človeka — inak by ostala neviditeľná. -->
        <FormSection :title="t('events.sections.contact')" :note="contactNote" :force-open="hasContactError">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField v-model="form.website" type="url" :label="t('events.fields.website')" :error="errors.website">
              <template #footer>
                <AttributeIssueHint :issue="websiteIssue" :label="t('events.fields.websiteIssueLabel')" />
              </template>
            </FormField>
            <FormField v-model="form.email" type="email" :label="t('events.fields.email')" :error="errors.email" />
            <FormField v-model="form.phone" type="tel" :label="t('events.fields.phone')" :error="errors.phone" />
          </div>
        </FormSection>
      </div>

      <!-- ── Pravý panel: nastavenia. Drží sa pri skrolovaní, aby bolo
             Uložiť vždy na dosah. ──────────────────────────────────────── -->
      <aside class="grid gap-4 xl:sticky xl:top-4 xl:self-start">
        <div class="edit-card grid gap-3">
          <p class="field-legend mb-0">{{ t('events.sections.publish') }}</p>
          <!-- Archivácia je jednosmerka: archivovaný event už policy upraviť
               nedovolí. Späť ho dostane len „Vrátiť z archívu" z menu akcií,
               a to iba dovtedy, kým naň nevisia vydané lístky. -->
          <FormField
            v-model="form.status"
            type="select"
            :label="t('events.fields.status')"
            :error="errors.status"
            :hint="form.status === 'archived' ? t('events.form.archivedHint') : undefined"
          >
            <option value="draft">{{ t('events.statuses.draft') }}</option>
            <option value="scheduled">{{ t('events.statuses.scheduled') }}</option>
            <option value="published">{{ t('events.statuses.published') }}</option>
            <option value="archived">{{ t('events.statuses.archived') }}</option>
          </FormField>
          <!-- Termín zverejnenia patrí k stavu „Naplánovaný"; pri ostatných
               stavoch ho backend aj tak zahodí, tak ho ani neukazujeme.
               Minulosť sa zakazuje len pri zakladaní — pri úprave už
               naplánovaného eventu by inak nešlo uložiť vôbec nič. -->
          <FormField
            v-if="form.status === 'scheduled'"
            v-model="form.publish_at"
            type="datetime"
            :allow-past="!isCreate"
            :label="t('events.fields.publishAt')"
            required
            :error="errors.publish_at"
            :hint="t('events.fields.publishAtHint')"
          />
          <div class="mt-1 flex gap-2">
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? t('events.form.saving') : t('events.form.save') }}
            </button>
            <RouterLink :to="indexRoute" class="btn btn-secondary">{{ t('events.form.cancel') }}</RouterLink>
          </div>
        </div>

        <div class="edit-card grid gap-3">
          <p class="field-legend mb-0">{{ t('events.sections.placement') }}</p>
          <FormField v-model="form.canal_id" type="select" :label="t('events.fields.canal')" :error="errors.canal_id">
            <option v-if="!form.canal_id" :value="null" disabled>{{ t('events.fields.canalPlaceholder') }}</option>
            <option v-for="c in canals" :key="c.id" :value="c.id">{{ c.name }}</option>
          </FormField>
          <FormField v-model="form.venue_id" :label="t('events.fields.venue')" :error="errors.venue_id">
            <template #default="{ value, invalid, update }">
              <div class="grid gap-2">
                <SearchableSelect
                  :model-value="value ?? null"
                  :options="venuesForCanal"
                  :placeholder="t('events.fields.venuePlaceholder')"
                  :invalid="invalid"
                  @update:model-value="update"
                />
                <button type="button" class="btn btn-secondary btn-sm justify-self-start" @click="openVenueModal">
                  {{ t('events.fields.venueAdd') }}
                </button>
              </div>
            </template>
          </FormField>
        </div>

        <div class="edit-card">
          <p class="field-legend">{{ t('events.sections.images') }}</p>
          <ImageManager v-if="fileableId" ref="imageManager" fileable-type="event" :fileable-id="fileableId" />
          <ImagePicker v-else ref="picker" />
          <!-- Obrázky sa neukladajú s formulárom, ale hneď pri každej zmene —
               z rozloženia to poznať nie je, tak to treba povedať. -->
          <p class="mt-3 text-xs text-slate-500">{{ t('events.sections.imagesNote') }}</p>
        </div>

        <!-- Nastavenie vstupeniek žije len v dashboarde (route
             `admin-events-tickets` neexistuje) a je vecou vlastníka kanála,
             nie super-admina — v admin scope sa preto neponúka vôbec. -->
        <div v-if="scope === 'dashboard'" class="edit-card">
          <p class="field-legend">{{ t('events.sections.tickets') }}</p>
          <p v-if="isCreate" class="text-sm text-slate-500">{{ t('events.tickets.createHint') }}</p>
          <template v-else>
            <p class="mb-3 text-sm text-slate-600">{{ t('events.tickets.manageHint') }}</p>
            <RouterLink :to="`/dashboard/events/${route.params.id}/tickets`" class="btn btn-secondary">
              {{ t('events.tickets.manage') }}
            </RouterLink>
          </template>
        </div>
      </aside>
    </form>

    <!-- Štítky sa v editore nezobrazujú: prideľuje ich `app:events-ai-tag`
         a odvodenie z dát, ručný zásah tu nemá čo meniť. -->
  </div>

  <!-- Quick venue create modal -->
  <Teleport to="body">
    <div v-if="venueModal.show" class="fixed inset-0 z-600 flex items-center justify-center bg-black/40 p-4" @mousedown.self="venueModal.show = false">
      <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-start gap-3">
          <h2 class="flex-1 text-lg font-semibold text-slate-900">{{ t('events.venueModal.title') }}</h2>
          <button type="button" class="-mr-1 -mt-1 p-1 leading-none text-slate-400 hover:text-slate-700"
            :aria-label="t('events.venueModal.close')" @click="venueModal.show = false">✕</button>
        </div>
        <p v-if="venueModal.error" class="mb-3 text-sm text-red-600">{{ venueModal.error }}</p>
        <!-- Modál má vlastný stav validácie — červená sa v ňom rozsvieti až po
             kliknutí na „Vytvoriť miesto", nezávisle od hlavného formulára. -->
        <div class="grid gap-3">
          <FormField
            v-model="venueModal.form.name"
            :label="t('events.venueModal.name')"
            required
            :validated="venueModal.validated"
            :error="venueModal.errors.name"
            :placeholder="t('events.venueModal.namePlaceholder')"
          />
          <FormField
            v-model="venueModal.form.village_id"
            :label="t('events.venueModal.village')"
            required
            :validated="venueModal.validated"
            :error="venueModal.errors.village_id"
          >
            <template #default="{ value, invalid, update }">
              <SearchableSelect
                :model-value="value ?? null"
                :options="municipalities"
                :placeholder="t('events.venueModal.villagePlaceholder')"
                :invalid="invalid"
                @update:model-value="update"
              />
            </template>
          </FormField>
          <div class="grid grid-cols-2 gap-3">
            <FormField v-model="venueModal.form.street" :label="t('events.venueModal.street')" :placeholder="t('events.venueModal.streetPlaceholder')" />
            <FormField v-model="venueModal.form.postcode" :label="t('events.venueModal.postcode')" placeholder="01234" />
          </div>
        </div>
        <div class="mt-5 flex gap-2">
          <button type="button" class="btn btn-primary" :disabled="venueModal.saving" @click="saveNewVenue">
            {{ venueModal.saving ? t('events.venueModal.saving') : t('events.venueModal.submit') }}
          </button>
          <button type="button" class="btn btn-secondary" @click="venueModal.show = false">{{ t('events.venueModal.cancel') }}</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showEvent, createEvent, updateEvent } from '@/api/events'
import { createVenue } from '@/api/venues'
import { uploadFiles } from '@/api/files'
import { t } from '@/i18n'
import { useToast } from '@/composables/useToast'
import { useFormOptions } from '@/composables/useFormOptions'
import { provideFormValidation } from '@/composables/useFormValidation'
import { useWebsiteIssue } from '@/composables/useWebsiteIssue'
import { isImageLikeUpload } from '@/utils/uploadFileTypes'
import { scrollToError } from '@/utils/scrollToError'
import { errorBody, isCancelled, withDependencyConsent } from '@/utils/publishFlow'
import AiAssistPanel from '@/components/ai/AiAssistPanel.vue'
import AttributeIssueHint from '@/components/AttributeIssueHint.vue'
import FormField from '@/components/FormField.vue'
import FormSection from '@/components/FormSection.vue'
import ImageManager from '@/components/ImageManager.vue'
import ImagePicker from '@/components/ImagePicker.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import HtmlEditor from '@/components/HtmlEditor.vue'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const auth = useAuthStore()

const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const isCreate = computed(() => !route.params.id)
const indexRoute = computed(() => `${prefix.value}/events`)

const savedId = ref<number | null>(null)
const fileableId = computed(() => route.params.id ? Number(route.params.id) : savedId.value)
const picker = ref<InstanceType<typeof ImagePicker> | null>(null)
const imageManager = ref<InstanceType<typeof ImageManager> | null>(null)

const { canals, venues, municipalities, loadCanals, loadVenues, loadMunicipalities } = useFormOptions(scope.value)

const validation = provideFormValidation()

const form = ref({
  name: '',
  status: 'draft',
  publish_at: '',
  canal_id: auth.canalId ?? null,
  venue_id: null as number | null,
  start_at: '',
  end_at: '',
  website: '',
  email: '',
  phone: '',
  body: '',
  // Len ručne zvolené štítky — tie od AI a odvodené z dát spravuje backend
  // a prepočítava ich, takže do formulára nepatria.
  tag_ids: [] as number[],
})

const errors = ref<Record<string, string>>({})

// Upozornenie na neodpovedajúcu webovú adresu (overuje sa na pozadí,
// viď App\Services\Attributes na backende).
const { apply: applyWebsiteIssue, issue: websiteIssue } = useWebsiteIssue(() => form.value.website)
const serverError = ref<string | null>(null)
const errorBanner = ref<HTMLElement | null>(null)
const saving = ref(false)
const loadingData = ref(false)

// Zhrnutie zbaleného Kontaktu — nech je bez rozbalenia vidieť, či je vyplnený.
const contactNote = computed(() => form.value.website || form.value.email || form.value.phone || t('events.contact.empty'))
const hasContactError = computed(() => Boolean(errors.value.website || errors.value.email || errors.value.phone))

watch(() => auth.canalId, (id) => {
  if (id && !form.value.canal_id) form.value.canal_id = id
}, { immediate: true })

// Predvoľba prvého kanála je pomôcka pri zakladaní, nie pri úprave. Zoznam sa
// načítava paralelne so `showEvent()`, takže pri úprave eventu bez kanála
// dobehol až po ňom a ticho mu priradil prvý kanál zo zoznamu — v admin scope
// úplne ľubovoľný kanál platformy.
watch(canals, (list) => {
  if (!isCreate.value) return
  if (list.length > 0 && form.value.canal_id === null) {
    form.value.canal_id = list[0].id
  }
})

// Only offer venues that actually belong to the selected canal — the backend
// rejects an incompatible canal+venue pair (activeCanals, published pivot), so
// showing the rest is misleading. This holds for admins too: even though they
// can manage venues across all canals, an event's venue must live in the event's
// canal. To use a venue from another canal, switch the canal or add a new venue.
const venuesForCanal = computed(() => {
  if (!form.value.canal_id) return venues.value
  return venues.value.filter(v => v.canalIds.includes(form.value.canal_id as number))
})

watch(() => form.value.canal_id, () => {
  // Skip while venues haven't loaded yet — avoids clobbering a valid venue_id
  // restored from an existing event before loadVenues() has resolved.
  if (!venues.value.length) return
  if (form.value.venue_id && !venuesForCanal.value.some(v => v.id === form.value.venue_id)) {
    form.value.venue_id = null
    // Bez upozornenia by človek uložil event bez miesta a nevedel by o tom —
    // pole je v bočnom paneli a zmena kanála ho zhodí ticho.
    toast.info(t('events.fields.venueReset'))
  }
})

watch(() => form.value.start_at, (startAt) => {
  if (!startAt || form.value.end_at) return
  const d = new Date(startAt)
  if (isNaN(d.getTime())) return
  d.setHours(d.getHours() + 2)
  // Build the datetime-local string from local components — toISOString() would
  // convert to UTC and shift the displayed value by the local timezone offset.
  const pad = (n: number) => String(n).padStart(2, '0')
  form.value.end_at = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
})

const venueModal = ref({
  show: false,
  saving: false,
  validated: false,
  error: null as string | null,
  errors: {} as Record<string, string>,
  form: { name: '', village_id: null as number | null, street: '', postcode: '' },
})

function openVenueModal() {
  venueModal.value = { show: true, saving: false, validated: false, error: null, errors: {}, form: { name: '', village_id: null, street: '', postcode: '' } }
}

async function saveNewVenue() {
  venueModal.value.validated = true
  venueModal.value.errors = {}
  venueModal.value.error = null
  venueModal.value.saving = true
  try {
    const payload: Record<string, unknown> = {
      name: venueModal.value.form.name,
      village_id: venueModal.value.form.village_id,
      street: venueModal.value.form.street || null,
      postcode: venueModal.value.form.postcode || null,
      canal_id: form.value.canal_id,
    }
    // Zápis musí ísť do rovnakého scope ako zvyšok stránky — dashboard
    // endpoint vyžaduje vlastníctvo cez kanál a admin na ňom skončil na 403.
    const created = await createVenue(payload, scope.value)
    venues.value.push({ id: created.id, name: created.name, canalIds: form.value.canal_id ? [form.value.canal_id] : [] })
    form.value.venue_id = created.id
    venueModal.value.show = false
    toast.success(t('events.venueModal.created'))
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    if (resp?.errors) venueModal.value.errors = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    venueModal.value.error = resp?.message ?? t('events.venueModal.failed')
  } finally {
    venueModal.value.saving = false
  }
}

/**
 * Hodnoty pre ukazovateľ pripravenosti pod menami z `config/content_review.php`
 * — jediného miesta, kde je napísané, čo znamená „hotové".
 *
 * `image` sa musí doplniť ručne: obrázky sa neukladajú s formulárom, ale hneď
 * pri každej zmene, takže vo `form` nie sú. Pri úprave ich vie ImageManager,
 * pri zakladaní ležia vo výbere súborov.
 */
const readinessValues = computed(() => ({
  ...form.value,
  image: fileableId.value ? (imageManager.value?.imageCount ?? 0) > 0 : (picker.value?.files.length ?? 0) > 0,
}))

onMounted(async () => {
  loadCanals()
  loadVenues()
  loadMunicipalities()
  if (!isCreate.value) {
    loadingData.value = true
    try {
      const ev = await showEvent(scope.value, Number(route.params.id))
      form.value = {
        name: ev.name,
        status: ev.status,
        publish_at: ev.publishAt?.slice(0, 16) ?? '',
        canal_id: ev.canalId ?? auth.canalId ?? null,
        venue_id: ev.venueId ?? null,
        start_at: ev.startAt?.slice(0, 16) ?? '',
        end_at: ev.endAt?.slice(0, 16) ?? '',
        website: ev.website ?? '',
        email: ev.email ?? '',
        phone: ev.phone ?? '',
        body: ev.body ?? '',
        // Ručné štítky sa naďalej posielajú späť nezmenené — editor ich len
        // neukazuje, mazať ich pri uložení by bola tichá strata dát.
        tag_ids: (ev.tags ?? []).filter((tag) => (tag.source ?? 'manual') === 'manual').map((tag) => tag.id),
      }
      applyWebsiteIssue(ev)
    } catch { serverError.value = t('events.form.loadFailed') }
    finally { loadingData.value = false }
  }
})

async function submit() {
  validation.markValidated()
  errors.value = {}
  serverError.value = null
  saving.value = true
  try {
    // Prázdny reťazec z <input type="datetime-local"> by prešiel ako neplatný
    // dátum — backend chce buď termín, alebo null.
    const payload = { ...form.value, publish_at: form.value.publish_at || null }
    if (isCreate.value) {
      const ev = await withDependencyConsent(p => createEvent(p, scope.value), payload)
      savedId.value = ev.id
      const pending = picker.value?.files ?? []
      if (pending.length) {
        // PDFs are converted server-side into an image preview, so they upload as type
        // "image" (and can become the primary/cover image); DOC/DOCX upload as type "file".
        const imageFiles = pending.filter(isImageLikeUpload)
        const docFiles = pending.filter(f => !isImageLikeUpload(f))
        for (const [group, type, makePrimary] of [
          [imageFiles, 'image', true] as const,
          [docFiles, 'file', false] as const,
        ]) {
          if (!group.length) continue
          const fd = new FormData()
          fd.append('fileable_type', 'event')
          fd.append('fileable_id', String(ev.id))
          fd.append('type', type)
          fd.append('make_primary', makePrimary ? '1' : '0')
          group.forEach(f => fd.append('files[]', f))
          await uploadFiles(fd)
        }
      }
      toast.success(t('events.form.created'))
      router.replace(`${prefix.value}/events/${ev.id}/edit`)
    } else {
      await withDependencyConsent(p => updateEvent(Number(route.params.id), p, scope.value), payload)
      toast.success(t('events.form.saved'))
    }
  } catch (e: unknown) {
    // Odmietnuté dopublikovanie závislostí nie je chyba — používateľ sa len
    // rozhodol nechať podujatie tak, ako bolo.
    if (isCancelled(e)) { saving.value = false; return }
    const resp = errorBody(e)
    if (resp?.errors) errors.value = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    serverError.value = resp?.message ?? t('events.form.saveFailed')
    await scrollToError(errorBanner)
  } finally { saving.value = false }
}
</script>
