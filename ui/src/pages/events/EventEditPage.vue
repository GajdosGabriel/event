<template>
  <div class="edit-shell">
    <!-- Hlavička stránky. Odkaz vedie na verejnú podobu podujatia, aby sa dala
         úprava hneď skontrolovať očami návštevníka — pri vytváraní ešte nie je
         čo zobraziť. Otvára sa vedľa editora, nie namiesto neho: kto sa ide
         pozrieť, sa vzápätí vracia k rozrobenému formuláru a `RouterLink` by
         mu ho prepísal aj s neuloženými zmenami. Šípka za textom hovorí, že
         odkaz odchádza z aplikácie — rovnako ako v `ActionButton`. -->
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
      <div>
        <RouterLink :to="indexRoute" class="text-sm text-blue-700 no-underline">{{ t('events.form.back') }}</RouterLink>
        <h1 class="my-2 text-2xl text-slate-900">
          {{ fileableId ? t('events.form.editTitle') : t('events.form.createTitle') }}
        </h1>
      </div>
      <a v-if="fileableId" :href="publicUrl" target="_blank" rel="noopener" class="btn btn-secondary no-underline">
        {{ t('events.form.view') }}
        <AppIcon name="externalLink" class="h-4 w-4 shrink-0" />
      </a>
    </div>

    <nav v-if="guided" ref="stepNavigation" class="mb-5 grid grid-cols-3 gap-2" :aria-label="t('events.form.createTitle')">
      <button v-for="(label, index) in stepLabels" :key="index" type="button"
        class="rounded-xl border px-3 py-3 text-left text-sm font-semibold"
        :class="step === index + 1 ? 'border-teal-700 bg-teal-50 text-teal-900' : 'border-slate-200 bg-white text-slate-600'"
        :aria-current="step === index + 1 ? 'step' : undefined" :disabled="saving" @click="goToStep(index + 1)">
        <span class="block text-xs text-slate-500">{{ index + 1 }} / 3</span>{{ label }}
      </button>
    </nav>
    <p v-if="loadingData" class="text-slate-600">{{ t('events.form.loading') }}</p>
    <p v-if="serverError" ref="errorBanner" role="alert" class="mb-4 text-red-600">{{ serverError }}</p>

    <!--
      Obsah vľavo, nastavenia v lepkavom paneli vpravo. Celá mriežka je vnútri
      jedného <form>: tlačidlo Uložiť síce sedí v paneli, ale odosiela natívne
      a prehliadač zvaliduje povinné polia z oboch stĺpcov naraz.
    -->
    <form v-if="!loadingData" :novalidate="guided" class="grid items-start gap-5"
      :class="guided ? 'mx-auto w-full max-w-3xl' : 'xl:grid-cols-[minmax(0,1fr)_360px]'"
      @submit.prevent="guided ? (step < 3 ? goToStep(step + 1) : saveGuided('publish')) : submit()">
      <fieldset :disabled="saving" class="contents">
      <!-- ── Ľavý stĺpec: to, čo sa píše ──────────────────────────────── -->
      <div v-show="!guided || step === 1" class="grid gap-5">
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
          <AiAssistPanel v-if="!guided" v-model="form.body" kind="event" :scope="scope" :values="readinessValues"
            :name="form.name" :record-id="fileableId" />
        </div>

        <div class="edit-card">
          <p class="field-legend">{{ t('events.sections.schedule') }}</p>
          <!-- `allow-past` zrkadlí EventDatetimeRule na serveri: minulý termín
               smie mať len publikované podujatie (tam sa dopisujú staršie
               akcie), inde ho pravidlo odmietne. Keby tu bolo natvrdo `true`,
               koncept s minulým termínom by prešiel formulárom a spadol až na
               422; keby tu nebolo vôbec, publikované podujatie by sa pre
               `min="teraz"` nedalo uložiť a nebolo by vidieť prečo. -->
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField v-model="form.start_at" type="datetime" :allow-past="allowPastSchedule" :label="t('events.fields.startAt')" :error="errors.start_at" />
            <FormField v-model="form.end_at" type="datetime" :allow-past="allowPastSchedule" :label="t('events.fields.endAt')" :error="errors.end_at" />
          </div>
        </div>

        <!-- Kontakt sa pri bežnej úprave neotvára, tak je zbalený. Chyba zo
             servera ho otvorí za človeka — inak by ostala neviditeľná. -->
        <FormSection v-if="!guided" :title="t('events.sections.contact')" :note="contactNote" :force-open="hasContactError">
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

      <!-- ── Pravý panel: nastavenia. Drží sa pri skrolovaní, aby sa dali
             meniť aj pri dlhom popise. Poradie kariet kopíruje poradie
             rozhodnutí: kto podujatie robí, ako vyzerá, čo stojí — a až
             nakoniec Publikovanie s tlačidlom Uložiť. ─────────────────── -->
      <aside v-show="!guided || step !== 1" class="grid gap-4" :class="{ 'xl:sticky xl:top-4 xl:self-start': !guided }">
        <div v-show="!guided || step === 2" class="edit-card grid gap-3">
          <p class="field-legend mb-0">{{ guided ? t('eventJourney.place') : t('events.sections.organizer') }}</p>
          <p v-if="guided && organizersLoading" role="status">{{ t('common.loading') }}</p>
          <p v-if="guided && organizersFailed" role="alert">{{ t('eventJourney.loadFailed') }} <button type="button" class="underline" @click="loadOrganizers">{{ t('roadmap.retry') }}</button></p>
          <div v-if="guided && !organizersLoading && !organizersFailed && !canalOptions.length && !form.canal_id" class="grid gap-3 rounded-xl bg-slate-50 p-4">
            <p class="font-semibold">{{ t('eventJourney.publishAs') }}</p>
            <p class="text-sm text-slate-600">{{ t('eventJourney.organizerHint') }}</p>
            <FormField v-model="organizer.name" :label="t('eventJourney.organizerName')" :error="organizer.errors.name" />
            <FormField v-model="organizer.municipality_id" :label="t('eventJourney.organizerTown')" :error="organizer.errors.municipality_id">
              <template #default="{ value, update, invalid }">
                <SearchableSelect :model-value="value ?? null" :options="municipalities" :invalid="invalid" @update:model-value="update" />
              </template>
            </FormField>
            <p v-if="organizer.error" role="alert" class="text-sm text-red-600">{{ organizer.error }}</p>
            <button type="button" class="btn btn-secondary justify-self-start" :disabled="organizer.saving" @click="saveOrganizer">{{ organizer.saving ? t('events.form.saving') : t('eventJourney.addOrganizer') }}</button>
          </div>
          <p v-if="guided && canalOptions.length === 1 && form.canal_id === canalOptions[0]?.id" class="text-sm text-slate-600">{{ t('eventJourney.organizer') }}: <strong>{{ canalOptions[0]?.name }}</strong></p>
          <FormField v-if="!guided || canalOptions.length > 1 || (canalOptions.length === 1 && form.canal_id !== canalOptions[0]?.id) || (form.canal_id && !canalOptions.length) || errors.canal_id" v-model="form.canal_id" :label="guided ? t('eventJourney.publishAs') : t('events.fields.canal')" :error="errors.canal_id">
            <template #default="{ value, invalid, update }">
              <SearchableSelect :model-value="value ?? null" :options="canalOptions" :source="`/${scope}/canals`" :invalid="invalid" @selected="selectedOrganizer = $event" @update:model-value="update" />
            </template>
          </FormField>
          <FormField v-if="!guided || form.canal_id" v-model="form.venue_id" :label="t('events.fields.venue')" :error="errors.venue_id">
            <template #default="{ value, invalid, update }">
              <div class="grid gap-2">
                <SearchableSelect
                  :model-value="value ?? null"
                  :options="venuesForCanal"
                  :source="`/${scope}/venues`"
                  :params="{ canal_id: form.canal_id, for_select: true }"
                  @selected="selectedPlace = $event"
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

        <section v-if="guided && step === 3" class="edit-card grid gap-3" aria-live="polite">
          <h2 class="field-legend">{{ t('eventJourney.preview') }}</h2>
          <h3 class="text-2xl font-semibold">{{ form.name }}</h3>
          <dl class="grid gap-2 text-sm">
            <div><dt class="text-slate-500">{{ t('events.sections.schedule') }}</dt><dd>{{ fmtRowDateRange(form.start_at, form.end_at) || t('eventJourney.missing') }}</dd></div>
            <div><dt class="text-slate-500">{{ t('events.fields.venue') }}</dt><dd>{{ placeName || t('eventJourney.missing') }}</dd></div>
            <div><dt class="text-slate-500">{{ t('eventJourney.organizer') }}</dt><dd>{{ organizerName || t('eventJourney.missing') }}</dd></div>
          </dl>
          <p class="whitespace-pre-line text-slate-600">{{ descriptionPreview }}</p>
          <p v-if="missingFields.length" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">{{ t('eventJourney.missingHint', { fields: missingFields.join(', ') }) }}</p>
        </section>

        <div v-show="!guided || step === 3" class="edit-card">
          <p class="field-legend">{{ t('events.sections.images') }}</p>
          <ImageManager v-if="fileableId" ref="imageManager" fileable-type="event" :fileable-id="fileableId" />
          <ImagePicker v-else ref="picker" />
          <!-- Obrázky sa neukladajú s formulárom, ale hneď pri každej zmene —
               z rozloženia to poznať nie je, tak to treba povedať. -->
          <p v-if="fileableId" class="mt-3 text-xs text-slate-500">{{ t('events.sections.imagesNote') }}</p>
        </div>

        <!-- Nastavenie vstupeniek žije len v dashboarde (route
             `admin-events-tickets` neexistuje) a je vecou vlastníka kanála,
             nie super-admina — v admin scope sa preto neponúka vôbec. -->
        <FormSection v-if="scope === 'dashboard'" v-show="!guided || step === 3" :title="t('eventJourney.more')" :default-open="!guided" :force-open="hasContactError || Boolean(errors.publish_at)">
          <div class="grid gap-3">
          <template v-if="guided">
            <AiAssistPanel v-model="form.body" kind="event" :scope="scope" :values="readinessValues" :name="form.name" :record-id="fileableId" />
            <FormField v-model="form.website" type="url" :label="t('events.fields.website')" :error="errors.website" />
            <FormField v-model="form.email" type="email" :label="t('events.fields.email')" :error="errors.email" />
            <FormField v-model="form.phone" type="tel" :label="t('events.fields.phone')" :error="errors.phone" />
            <ToggleCard v-model="schedulePublication" :label="t('eventJourney.schedule')" />
            <FormField v-if="schedulePublication" v-model="form.publish_at" type="datetime" :allow-past="false" :label="t('events.fields.publishAt')" :error="errors.publish_at" />
          </template>
          <p class="field-legend mb-0">{{ t('events.sections.tickets') }}</p>
          <!-- Najčastejší prípad — vstup zdarma za registráciu — sa zapína
               priamo tu, bez odchodu do sekcie Lístky. Pod prepínačom je jeden
               bezplatný typ lístka; kto má typov viac alebo platený, ten už
               nastavuje v sekcii a prepínač by mu len zavádzal. -->
          <ToggleCard
            v-if="!hasCustomTickets"
            v-model="freeRegistration"
            :label="t('events.tickets.freeRegistration')"
            :hint="freeRegistrationHint"
          />
          <p v-else class="text-sm text-slate-600">{{ t('events.tickets.customHint', { n: ticketTypes.length }) }}</p>
          <RouterLink v-if="!isCreate" :to="`/dashboard/events/${route.params.id}/tickets`" class="btn btn-secondary justify-self-start">
            {{ t('events.tickets.manage') }}
          </RouterLink>
          <!-- Otázky z publika (Q&A) — skratka za nástenku celého podujatia.
               Ďalšie nastavenia nástenky, workshopy a snímka s QR kódom
               ostávajú v sekcii Otázky. -->
          <ToggleCard
            v-model="questionsEnabled"
            :label="t('events.questions.enable')"
            :hint="questionsHint"
          />
          <RouterLink v-if="!isCreate && questionBoard" :to="`/dashboard/events/${route.params.id}/otazky`" class="btn btn-secondary justify-self-start">
            {{ t('events.questions.manage') }}
          </RouterLink>
          </div>
        </FormSection>

        <!-- Publikovanie je posledné zámerne: je to posledné rozhodnutie nad
             podujatím a Uložiť pod ním uzatvára celý panel. -->
        <div v-if="!guided" class="edit-card grid gap-3">
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

      </aside>
      <div v-if="guided" class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <button v-if="step > 1" type="button" class="btn btn-secondary" @click="goToStep(step - 1)">{{ t('eventJourney.back') }}</button>
        <button type="button" class="btn btn-secondary" :disabled="saving || organizer.saving" @click="saveGuided('draft')">{{ saving ? t('events.form.saving') : t('eventJourney.saveDraft') }}</button>
        <button v-if="step < 3" type="button" class="btn btn-primary ml-auto" :disabled="organizer.saving" @click="goToStep(step + 1)">{{ t('eventJourney.next') }}</button>
        <button v-else type="submit" class="btn btn-primary ml-auto" :disabled="saving || organizer.saving">{{ saving ? t('events.form.saving') : schedulePublication ? t('eventJourney.scheduleEvent') : t('eventJourney.publish') }}</button>
      </div>
      </fieldset>
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
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showEvent, createEvent, updateEvent } from '@/api/events'
import { createVenue } from '@/api/venues'
import { indexTicketTypes, createTicketType, updateTicketType } from '@/api/ticketTypes'
import { indexQuestionBoards, createQuestionBoard, updateQuestionBoard, type QuestionBoardAdmin } from '@/api/questions'
import type { TicketTypeItem } from '@/types'
import { uploadFiles } from '@/api/files'
import { t } from '@/i18n'
import { useToast } from '@/composables/useToast'
import { useFormOptions, type SelectOption } from '@/composables/useFormOptions'
import { provideFormValidation } from '@/composables/useFormValidation'
import { useWebsiteIssue } from '@/composables/useWebsiteIssue'
import { isImageLikeUpload } from '@/utils/uploadFileTypes'
import { scrollToError } from '@/utils/scrollToError'
import { errorBody, isCancelled, withDependencyConsent } from '@/utils/publishFlow'
import { publicEventPath } from '@/utils/publicUrl'
import AiAssistPanel from '@/components/ai/AiAssistPanel.vue'
import AppIcon from '@/components/AppIcon.vue'
import AttributeIssueHint from '@/components/AttributeIssueHint.vue'
import FormField from '@/components/FormField.vue'
import FormSection from '@/components/FormSection.vue'
import ImageManager from '@/components/ImageManager.vue'
import ImagePicker from '@/components/ImagePicker.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import { createCanal } from '@/api/canals'
import http from '@/api/index'
import { fmtRowDateRange } from '@/utils/dateFormat'
import ToggleCard from '@/components/ToggleCard.vue'
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
const guided = computed(() => isCreate.value && scope.value === 'dashboard')
const step = ref(1)
const stepNavigation = ref<HTMLElement | null>(null)
watch(step, async () => {
  await nextTick()
  const activeStep = stepNavigation.value?.querySelector<HTMLButtonElement>('[aria-current="step"]')
  activeStep?.focus({ preventScroll: true })
  stepNavigation.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
})
const schedulePublication = ref(false)
const stepLabels = computed(() => [t('eventJourney.basics'), t('eventJourney.place'), t('eventJourney.review')])
const indexRoute = computed(() => `${prefix.value}/events`)

const savedId = ref<number | null>(null)
const fileableId = computed(() => route.params.id ? Number(route.params.id) : savedId.value)
const picker = ref<InstanceType<typeof ImagePicker> | null>(null)
const imageManager = ref<InstanceType<typeof ImageManager> | null>(null)

// Slug z detailu — je len ozdoba adresy, routuje sa id. Po práve založenom
// evente ho ešte nemáme, `publicEventPath` vtedy dá holé `/akcie/{id}`.
const eventSlug = ref<string | null>(null)
const publicUrl = computed(() => fileableId.value ? publicEventPath({ id: fileableId.value, slug: eventSlug.value }) : '')

const { canals, venues, municipalities, loadCanals, loadMunicipalities } = useFormOptions(scope.value)

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

// Bolo podujatie už niekedy vonku? Server to berie zo vstupu (`status`) aj
// z uloženého `published_at` — editor musí poznať oboje, inak by sa pri
// publikovanom podujatí s minulým termínom rozchádzal s pravidlom.
const publishedAt = ref<string | null>(null)

/**
 * Smie mať podujatie termín v minulosti? Presné zrkadlo
 * App\Rules\EventDatetimeRule::isPublishedEvent() — minulosť patrí len tomu,
 * čo je (alebo bolo) publikované.
 */
const allowPastSchedule = computed(() =>
  form.value.status === 'published' || publishedAt.value !== null
)

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
  if (list.length === 1 && form.value.canal_id === null) {
    form.value.canal_id = list[0].id
  }
})

// Kanál práve upravovaného eventu. `loadCanals()` ťahá len prvú stránku
// (per_page 20) — v admin scope je kanálov aj tisíc, takže ten správny medzi
// možnosťami často nie je vôbec. <select> potom nemá čo označiť a pole vyzerá
// prázdne, hoci event kanál má. Doplníme ho teda z detailu eventu.
const eventCanal = ref<SelectOption | null>(null)

/** Miesto eventu aj s kanálom, do ktorého patrilo pri načítaní — viď `venuesForCanal`. */
const eventVenue = ref<(SelectOption & { canalId: number | null }) | null>(null)

const canalOptions = computed(() => {
  const own = eventCanal.value
  if (!own || canals.value.some(c => c.id === own.id)) return canals.value
  return [own, ...canals.value]
})

// Only offer venues that actually belong to the selected canal — the backend
// rejects an incompatible canal+venue pair (activeCanals, published pivot), so
// showing the rest is misleading. This holds for admins too: even though they
// can manage venues across all canals, an event's venue must live in the event's
// canal. To use a venue from another canal, switch the canal or add a new venue.
const venuesForCanal = computed(() => {
  const canalId = form.value.canal_id
  const list = canalId
    ? venues.value.filter(v => v.canalIds.includes(canalId))
    : venues.value

  // Miesto eventu chýba v zozname z rovnakého dôvodu ako jeho kanál (prvá
  // stránka, 20 položiek). Držíme ho medzi možnosťami, ale len kým je zvolený
  // pôvodný kanál eventu — po prepnutí kanála doň už nepatrí a kontrola nižšie
  // ho má právom zhodiť.
  const own = eventVenue.value
  if (!own || own.canalId !== canalId || list.some(v => v.id === own.id)) return list

  return [own, ...list]
})

watch(() => form.value.canal_id, (id, previous) => {
  if (loadingData.value || id === previous || !form.value.venue_id) return
  if (eventVenue.value?.id === form.value.venue_id && eventVenue.value.canalId === id) return
  form.value.venue_id = null
  toast.info(t('events.fields.venueReset'))
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

// ── Registrácia zdarma ────────────────────────────────────────────────
// Prepínač je skratka za jeden bezplatný typ lístka. Ukladá sa spolu s
// eventom (nie hneď pri kliknutí), aby Zrušiť vrátilo aj túto zmenu.
const ticketTypes = ref<TicketTypeItem[]>([])
const freeRegistration = ref(false)

/** Jediný bezplatný lístok — len ten prepínač ovláda. */
const freeTicketType = computed(() => {
  const [only, ...rest] = ticketTypes.value
  return only && !rest.length && only.kind === 'ticket' && !only.priceAmount ? only : null
})

/** Lístky nastavené v sekcii inak ako prepínačom (platené, viac typov, workshop). */
const hasCustomTickets = computed(() => ticketTypes.value.length > 0 && !freeTicketType.value)

const freeRegistrationHint = computed(() => {
  if (!freeRegistration.value) return t('events.tickets.freeRegistrationOff')
  const sold = freeTicketType.value?.soldCount ?? 0
  return sold > 0
    ? t('events.tickets.freeRegistrationCount', { n: sold })
    : t('events.tickets.freeRegistrationOn')
})

async function loadTicketTypes(eventId: number) {
  ticketTypes.value = await indexTicketTypes(eventId)
  freeRegistration.value = Boolean(freeTicketType.value?.isActive)
}

/** Premietne prepínač do typov lístkov. Volá sa až po uložení eventu. */
async function syncFreeRegistration(eventId: number) {
  if (scope.value !== 'dashboard' || hasCustomTickets.value) return
  const existing = freeTicketType.value
  try {
    if (!existing) {
      if (!freeRegistration.value) return
      await createTicketType(eventId, {
        name: t('tickets.type.templates.free.name'),
        kind: 'ticket',
        price_amount: 0,
        is_active: true,
      })
    } else if (existing.id && existing.isActive !== freeRegistration.value) {
      // Vypnutie lístok len deaktivuje — prihlásení aj história ostávajú.
      await updateTicketType(eventId, existing.id, { is_active: freeRegistration.value })
    } else {
      return
    }
    await loadTicketTypes(eventId)
  } catch {
    // Event je už uložený — zlyhanie lístka nesmie vyzerať ako neuložený formulár.
    toast.error(t('events.tickets.freeRegistrationFailed'))
  }
}

// ── Otázky z publika ──────────────────────────────────────────────────
// Prepínač je skratka za `is_open` nástenky podujatia. Nástenka sa zakladá
// lenivo — až keď ju tu niekto prvýkrát zapne. Ukladá sa spolu s eventom.
const questionBoard = ref<QuestionBoardAdmin | null>(null)
const questionsEnabled = ref(false)

const questionsHint = computed(() => {
  if (!questionsEnabled.value) return t('events.questions.off')
  const n = questionBoard.value?.questionsCount ?? 0
  return n > 0 ? t('events.questions.count', { n }) : t('events.questions.on')
})

async function loadQuestionBoard(eventId: number) {
  const slots = await indexQuestionBoards(eventId)
  questionBoard.value = slots.find(s => s.targetType === 'event')?.board ?? null
  questionsEnabled.value = Boolean(questionBoard.value?.isOpen)
}

/** Premietne prepínač do nástenky. Volá sa až po uložení eventu. */
async function syncQuestionBoard(eventId: number) {
  if (scope.value !== 'dashboard') return
  const board = questionBoard.value
  try {
    if (!board) {
      if (!questionsEnabled.value) return
      // Nová nástenka je otvorená hneď od založenia.
      questionBoard.value = await createQuestionBoard(eventId, 'event', eventId)
    } else if (board.isOpen !== questionsEnabled.value) {
      // Vypnutie nástenku len zavrie — otázky aj odpovede ostávajú.
      questionBoard.value = await updateQuestionBoard(board.id, { is_open: questionsEnabled.value })
    }
  } catch {
    // Event je už uložený — zlyhanie nástenky nesmie vyzerať ako neuložený formulár.
    toast.error(t('events.questions.failed'))
  }
}

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
    selectedPlace.value = { id: created.id, name: created.name }
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
  if (guided.value) await loadOrganizers()
  else loadCanals()

  loadMunicipalities()
  if (!isCreate.value) {
    loadingData.value = true
    try {
      const ev = await showEvent(scope.value, Number(route.params.id))
      eventSlug.value = ev.slug || null
      publishedAt.value = ev.publishedAt ?? null
      eventCanal.value = ev.canalId ? { id: ev.canalId, name: ev.canalName } : null
      eventVenue.value = ev.venue ? { id: ev.venue.id, name: ev.venue.name, canalId: ev.canalId } : null
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
      // Lístky sú doplnok — keď sa nenačítajú, editor musí ísť ďalej.
      if (scope.value === 'dashboard') {
        await Promise.all([
          loadTicketTypes(ev.id).catch(() => {}),
          loadQuestionBoard(ev.id).catch(() => {}),
        ])
      }
    } catch { serverError.value = t('events.form.loadFailed') }
    finally { loadingData.value = false }
  }
})

async function submit() {
  if (saving.value) return
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
      eventSlug.value = ev.slug || null
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
      await syncFreeRegistration(ev.id)
      await syncQuestionBoard(ev.id)
      toast.success(t('events.form.created'))
      router.replace(`${prefix.value}/events/${ev.id}/edit`)
    } else {
      await withDependencyConsent(p => updateEvent(Number(route.params.id), p, scope.value), payload)
      await syncFreeRegistration(Number(route.params.id))
      await syncQuestionBoard(Number(route.params.id))
      toast.success(t('events.form.saved'))
    }
  } catch (e: unknown) {
    // Odmietnuté dopublikovanie závislostí nie je chyba — používateľ sa len
    // rozhodol nechať podujatie tak, ako bolo.
    if (isCancelled(e)) { saving.value = false; return }
    const resp = errorBody(e)
    if (resp?.errors) errors.value = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    serverError.value = resp?.message ?? t('events.form.saveFailed')
    if (guided.value) {
      if (errors.value.name || errors.value.body || errors.value.start_at || errors.value.end_at) step.value = 1
      else if (errors.value.canal_id || errors.value.venue_id) step.value = 2
      else step.value = 3
    }
    await scrollToError(errorBanner)
  } finally { saving.value = false }
}

const selectedOrganizer = ref<SelectOption | null>(auth.canalId ? { id: auth.canalId, name: auth.canalName } : null)
const selectedPlace = ref<SelectOption | null>(null)
const organizerName = computed(() => canalOptions.value.find(c => c.id === form.value.canal_id)?.name || (selectedOrganizer.value?.id === form.value.canal_id ? selectedOrganizer.value.name : ''))
const placeName = computed(() => venuesForCanal.value.find(v => v.id === form.value.venue_id)?.name || (selectedPlace.value?.id === form.value.venue_id ? selectedPlace.value.name : ''))
const descriptionPreview = computed(() => {
  const doc = new DOMParser().parseFromString(form.value.body, 'text/html')
  return doc.body.textContent?.trim() || ''
})
const missingFields = computed(() => [
  !form.value.name.trim() && t('events.fields.name'),
  !form.value.start_at && t('events.fields.startAt'),
  !form.value.canal_id && t('eventJourney.organizer'),
  !form.value.venue_id && t('events.fields.venue'),
].filter((value): value is string => Boolean(value)))

function goToStep(next: number) {
  if (saving.value || organizer.value.saving) return
  if (next > step.value && !form.value.name.trim()) {
    errors.value.name = t('eventJourney.nameRequired')
    serverError.value = errors.value.name
    step.value = 1
    scrollToError(errorBanner)
    return
  }
  errors.value.name = ''
  step.value = next
  serverError.value = null
}

async function saveGuided(action: 'draft' | 'publish') {
  if (saving.value || organizer.value.saving) return
  if (!form.value.name.trim()) {
    errors.value.name = t('eventJourney.nameRequired')
    serverError.value = errors.value.name
    step.value = 1
    await scrollToError(errorBanner)
    return
  }
  if (!form.value.canal_id) {
    step.value = 2
    serverError.value = t('eventJourney.chooseOrganizer')
    await scrollToError(errorBanner)
    return
  }
  if (action === 'publish' && missingFields.value.length) {
    serverError.value = t('eventJourney.missingHint', { fields: missingFields.value.join(', ') })
    await scrollToError(errorBanner)
    return
  }
  form.value.status = action === 'draft' ? 'draft' : schedulePublication.value ? 'scheduled' : 'published'
  await submit()
}

const organizersLoading = ref(true)
const organizersFailed = ref(false)
const organizer = ref({ name: '', municipality_id: null as number | null, saving: false, error: '', errors: {} as Record<string, string> })
async function loadOrganizers() {
  organizersLoading.value = true
  organizersFailed.value = false
  try {
    const { data } = await http.get('/dashboard/canals', { params: { per_page: 20 } })
    canals.value = (data.data ?? data).map((c: SelectOption) => ({ id: c.id, name: c.name }))
  } catch { organizersFailed.value = true }
  finally { organizersLoading.value = false }
}
async function saveOrganizer() {
  if (organizer.value.saving) return
  organizer.value.saving = true
  organizer.value.error = ''
  organizer.value.errors = {}
  try {
    const created = await createCanal({ name: organizer.value.name, municipality_id: organizer.value.municipality_id, identity_mode: 'organization', status: 'draft' }, scope.value)
    canals.value.push({ id: created.id, name: created.name })
    form.value.canal_id = created.id
    toast.success(t('eventJourney.organizerSaved'))
  } catch (e: unknown) {
    const response = errorBody(e)
    if (response?.errors) organizer.value.errors = Object.fromEntries(Object.entries(response.errors).map(([key, values]) => [key, values[0]]))
    organizer.value.error = response?.message ?? t('common.actionFailed')
  } finally { organizer.value.saving = false }
}

</script>
