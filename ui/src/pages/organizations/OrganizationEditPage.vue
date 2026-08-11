<template>
  <div class="edit-shell">
    <div class="edit-card">
      <RouterLink :to="indexRoute" class="text-sm text-blue-700 no-underline">{{ t('organizations.form.back') }}</RouterLink>
      <h1 class="mb-1 mt-2 text-3xl font-semibold tracking-tight text-slate-900">
        {{ isCreate ? t('organizations.form.createTitle') : t('organizations.form.editTitle') }}
      </h1>
      <p class="text-sm text-slate-500">
        {{ t('organizations.form.lead') }}
      </p>
      <p v-if="serverError" ref="errorBanner" class="text-red-600 mt-2">{{ serverError }}</p>

      <!-- Chýbajúce fakturačné údaje patria nad formulár: inak si ich všimne
           až ten, kto sa doscrolluje k fakturačnému bloku. -->
      <div v-if="missingBilling.length"
        class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
        {{ t('organizations.billing.missing', { fields: missingBilling.join(', ') }) }}
      </div>

      <form class="grid gap-4 mt-4" @submit.prevent="submit">
        <!-- Typ subjektu rozhoduje, čo sa vôbec pýtame – preto je prvý.
             Binárna voľba nepotrebuje vlastnú sekciu: „Som“ a obe možnosti
             sa zmestia na jeden riadok a čítajú sa ako veta. -->
        <fieldset class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
          <legend class="px-1 text-sm font-semibold text-slate-900">{{ t('organizations.subject.legend') }}</legend>
          <div class="mt-2 flex flex-wrap gap-2">
            <label
              v-for="type in subjectTypes"
              :key="String(type.person)"
              class="cursor-pointer rounded-lg border px-3 py-1.5 text-sm transition"
              :class="form.person === type.person
                ? 'border-blue-500 bg-blue-50/60 font-medium text-slate-900 ring-1 ring-blue-500/20'
                : 'border-slate-200 text-slate-600 hover:border-slate-300'"
            >
              <input v-model="form.person" type="radio" :value="type.person" class="sr-only" />
              {{ type.label }}
            </label>
          </div>
          <p class="mt-2 text-xs text-slate-500">{{ subjectHint }}</p>
        </fieldset>

        <!-- Pri organizácii je IČO prvé: z registra sa ním predvyplní
             zvyšok formulára, takže ručne písať treba čo najmenej. -->
        <FormSection v-if="!isPerson" :title="t('organizations.register.title')"
          :note="account.ico || t('organizations.register.ico')"
          default-open :force-open="hasRegisterError">
          <div class="grid gap-2 sm:flex sm:items-start sm:gap-3">
            <FormField v-model="account.ico" :error="errors['account.ico']" class="sm:w-56"
              :placeholder="t('organizations.register.ico')" @keydown.enter.prevent="runLookup" />
            <button type="button" class="btn btn-primary btn-lg" :disabled="lookingUp || !account.ico" @click="runLookup">
              {{ lookingUp ? t('organizations.register.lookingUp') : t('organizations.register.lookup') }}
            </button>
            <span v-if="lookupMessage" class="text-sm sm:mt-2" :class="lookupOk ? 'text-green-600' : 'text-amber-700'">
              {{ lookupMessage }}
            </span>
          </div>

          <!-- Výsledok načítania patrí sem, k IČU: inak by sa doplnené polia
               skryli v inej zabalenej sekcii a nebolo by ich vidieť. -->
          <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField
              v-model="account.legal_name"
              :label="t('organizations.register.legalName')"
              :error="errors['account.legal_name']"
              :placeholder="form.title || t('organizations.register.legalNamePlaceholder')"
              class="lg:col-span-2"
            />
            <FormField v-model="account.legal_form" type="select" :label="t('organizations.register.legalForm')">
              <option value="">{{ t('organizations.form.unselected') }}</option>
              <option v-for="o in LEGAL_FORMS" :key="o" :value="o">{{ t(`organizations.legalForms.${o}`) }}</option>
            </FormField>
            <FormField v-model="account.dic" :label="t('organizations.register.dic')" :error="errors['account.dic']" />
            <FormField v-model="account.vat_mode" type="select" :label="t('organizations.register.vatMode')">
              <option value="">{{ t('organizations.form.unselected') }}</option>
              <option v-for="o in VAT_MODES" :key="o" :value="o">{{ t(`organizations.vatModes.${o}`) }}</option>
            </FormField>
            <FormField
              v-model="account.ic_dph"
              :label="t('organizations.register.icDph')"
              :error="errors['account.ic_dph']"
              :hint="t('organizations.register.icDphHint')"
              placeholder="SK2020123456"
            />
          </div>

          <h3 class="mt-5 mb-2 text-sm font-semibold text-slate-700">{{ t('organizations.register.seat') }}</h3>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="account.street" :label="t('organizations.address.street')" :error="errors['account.street']" class="lg:col-span-2" />
            <FormField v-model="account.city" :label="t('organizations.address.city')" :error="errors['account.city']" />
            <FormField v-model="account.postal_code" :label="t('organizations.address.postalCode')" :error="errors['account.postal_code']" />
            <FormField v-model="account.country" type="select" :label="t('organizations.address.country')" :error="errors['account.country']">
              <option v-for="c in COUNTRIES" :key="c" :value="c">{{ t(`organizations.countries.${c}`) }}</option>
            </FormField>
          </div>

          <h3 class="mt-5 mb-2 text-sm font-semibold text-slate-700">{{ t('organizations.register.entry') }}</h3>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
            <FormField v-model="account.register_court" :label="t('organizations.register.court')"
              :placeholder="t('organizations.register.courtPlaceholder')" />
            <FormField v-model="account.register_section" :label="t('organizations.register.section')" placeholder="Sro" />
            <FormField v-model="account.register_insert" :label="t('organizations.register.insert')" placeholder="12345/R" />
          </div>
        </FormSection>

        <FormSection :title="t('organizations.profile.title')" :note="form.title || t('organizations.profile.note')"
          default-open :force-open="!!errors['title'] || !!errors['village_id'] || !!errors['status']">
          <p class="mb-3 text-sm text-slate-500">
            {{ t('organizations.profile.lead') }}
          </p>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField
              v-model="form.title"
              :label="isPerson ? t('organizations.profile.personName') : t('organizations.profile.name')"
              required
              :error="errors['title']"
              class="lg:col-span-2"
            />
            <FormField v-model="form.village_id" :label="t('organizations.profile.village')" :error="errors['village_id']">
              <template #default="{ value, invalid, update }">
                <SearchableSelect
                  :model-value="value ?? null"
                  :options="municipalities"
                  :placeholder="t('organizations.profile.villagePlaceholder')"
                  :invalid="invalid"
                  @update:model-value="update"
                />
              </template>
            </FormField>
            <FormField v-model="form.status" type="select" :label="t('organizations.profile.status')" :error="errors['status']">
              <option value="draft">{{ t('organizations.statuses.draft') }}</option>
              <option value="published">{{ t('organizations.statuses.published') }}</option>
              <option value="archived">{{ t('organizations.statuses.archived') }}</option>
            </FormField>
            <FormField :label="t('organizations.profile.description')" class="lg:col-span-2">
              <HtmlEditor v-model="form.description" min-height="130px" />
            </FormField>
          </div>
        </FormSection>

        <!-- Verejný kontakt na organizátora. S fakturačným e-mailom nemá nič
             spoločné — ten drží Account a chodia naň doklady. -->
        <FormSection :title="t('organizations.contact.title')" :note="contactNote"
          :force-open="!!errors['email'] || !!errors['phone'] || !!errors['website']">
          <p class="mb-3 text-sm text-slate-500">
            {{ t('organizations.contact.lead') }}
          </p>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="form.email" type="email" :label="t('organizations.contact.email')" :error="errors['email']" />
            <FormField v-model="form.phone" type="tel" :label="t('organizations.contact.phone')" :error="errors['phone']" />
            <FormField v-model="form.website" type="url" :label="t('organizations.contact.website')" :error="errors['website']" placeholder="https://" class="lg:col-span-2">
              <template #footer>
                <AttributeIssueHint :issue="websiteIssue" :label="t('organizations.contact.websiteIssueLabel')" />
              </template>
            </FormField>
          </div>
        </FormSection>

        <!-- ── Fakturačné údaje ──────────────────────────────────────────── -->
        <FormSection :title="t('organizations.billing.title')" :note="billingNote" :force-open="hasBillingError">
          <p class="mb-3 text-sm text-slate-500">
            {{ t('organizations.billing.lead') }}
          </p>

          <div v-if="accountLine" class="mb-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
            {{ t('organizations.billing.linked') }} <span class="font-medium text-slate-800">{{ accountLine }}</span>
          </div>

          <!-- Firemné údaje aj sídlo sú pri IČE, s ktorým sa načítali. Súkromná
               osoba tú sekciu nemá, preto sa jej adresa pýta tu. -->
          <template v-if="isPerson">
            <h3 class="mt-1 mb-2 text-sm font-semibold text-slate-700">{{ t('organizations.address.title') }}</h3>
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
              <FormField v-model="account.street" :label="t('organizations.address.street')" :error="errors['account.street']" class="lg:col-span-2" />
              <FormField v-model="account.city" :label="t('organizations.address.city')" :error="errors['account.city']" />
              <FormField v-model="account.postal_code" :label="t('organizations.address.postalCode')" :error="errors['account.postal_code']" />
              <FormField v-model="account.country" type="select" :label="t('organizations.address.country')" :error="errors['account.country']">
                <option v-for="c in COUNTRIES" :key="c" :value="c">{{ t(`organizations.countries.${c}`) }}</option>
              </FormField>
            </div>
          </template>

          <h3 class="mt-5 mb-2 text-sm font-semibold text-slate-700">{{ t('organizations.billing.heading') }}</h3>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="account.billing_email" type="email" :label="t('organizations.billing.email')" :error="errors['account.billing_email']">
              <!-- Neoverená adresa nie je chyba formulára – potvrdenie príde
                   až po uložení, keď zákazník klikne na odkaz v e-maile. -->
              <template #footer>
                <span v-if="!errors['account.billing_email'] && billingEmailState" class="mt-1 flex items-center gap-1.5 text-xs"
                  :class="billingEmailState.verified ? 'text-green-600' : 'text-amber-700'">
                  <span class="inline-block h-1.5 w-1.5 rounded-full"
                    :class="billingEmailState.verified ? 'bg-green-500' : 'bg-amber-500'"></span>
                  {{ billingEmailState.label }}
                </span>
              </template>
            </FormField>
            <FormField v-model="account.bank_name" :label="t('organizations.billing.bank')" />
            <FormField v-model="account.iban" :label="t('organizations.billing.iban')" :error="errors['account.iban']"
              placeholder="SK00 0000 0000 0000 0000 0000" />
            <FormField v-model="account.swift" :label="t('organizations.billing.swift')" :error="errors['account.swift']" />
          </div>
        </FormSection>

        <div class="flex items-center gap-3">
          <button type="submit" class="btn btn-primary btn-lg" :disabled="saving">
            {{ saving ? t('organizations.form.saving') : t('organizations.form.save') }}
          </button>
          <RouterLink :to="indexRoute" class="btn btn-secondary">{{ t('organizations.form.cancel') }}</RouterLink>
        </div>
      </form>

      <!-- Komu firma patrí. Ľudia pod ňou nevisia priamo — členom sa je vždy
           v konkrétnom kanáli a tam platí aj rola. Firma je len fakturačná
           strecha nad kanálmi, preto sa tu pripája a odpája kanál. -->
      <FormSection v-if="!isCreate" :title="t('organizations.canals.title')" :note="canalsNote" class="mt-6">
        <p class="mb-3 text-sm text-slate-500">
          {{ t('organizations.canals.lead') }}
        </p>

        <p v-if="canalsError" class="mb-3 text-red-600 text-sm">{{ canalsError }}</p>

        <ul v-if="canals.length" class="grid gap-2">
          <li v-for="canal in canals" :key="canal.id" class="rounded-lg border border-slate-200 p-3">
            <div class="flex flex-wrap items-center gap-3">
              <RouterLink :to="`${prefix}/canals/${canal.id}/edit`"
                class="flex-1 min-w-40 font-medium text-slate-900 no-underline hover:text-blue-700">
                {{ canal.name }}
              </RouterLink>
              <span class="text-xs text-slate-500">{{ plural('organizations.counts.members', canal.members.length) }}</span>
              <button type="button" class="action-btn action-btn-danger" :disabled="detaching === canal.id"
                @click="detach(canal)">
                {{ detaching === canal.id ? t('organizations.canals.detaching') : t('organizations.canals.detach') }}
              </button>
            </div>

            <ul v-if="canal.members.length" class="mt-2 grid gap-1 pl-1">
              <li v-for="member in canal.members" :key="member.id"
                class="flex items-center gap-2 text-sm text-slate-600">
                <span>{{ member.name }}</span>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ roleLabel(member.role) }}</span>
              </li>
            </ul>
            <p v-else class="mt-2 text-sm text-slate-400">{{ t('organizations.canals.noMembers') }}</p>
          </li>
        </ul>
        <p v-else class="text-sm text-slate-500">
          {{ t('organizations.canals.empty') }}
        </p>

        <div class="mt-4 grid gap-2 sm:flex sm:items-end sm:gap-3">
          <FormField v-model="canalToAttach" type="select" :label="t('organizations.canals.attachLabel')" class="sm:w-72">
            <option :value="null">{{ t('organizations.canals.attachPlaceholder') }}</option>
            <option v-for="canal in attachableCanals" :key="canal.id" :value="canal.id">{{ canal.name }}</option>
          </FormField>
          <button type="button" class="btn btn-secondary" :disabled="!canalToAttach || attaching" @click="attach">
            {{ attaching ? t('organizations.canals.attaching') : t('organizations.canals.attach') }}
          </button>
        </div>
      </FormSection>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  showOrganization,
  createOrganization,
  updateOrganization,
  lookupIco,
  accountToForm,
  attachCanalToOrganization,
  detachCanalFromOrganization,
} from '@/api/organizations'
import { indexCanals } from '@/api/canals'
import type { CanalItem, OrganizationAccountData, OrganizationCanal } from '@/types'
import { t, plural } from '@/i18n'
import { useToast } from '@/composables/useToast'
import { useFormOptions } from '@/composables/useFormOptions'
import { provideFormValidation } from '@/composables/useFormValidation'
import { useWebsiteIssue } from '@/composables/useWebsiteIssue'
import { scrollToError } from '@/utils/scrollToError'
import AttributeIssueHint from '@/components/AttributeIssueHint.vue'
import FormField from '@/components/FormField.vue'
import FormSection from '@/components/FormSection.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import HtmlEditor from '@/components/HtmlEditor.vue'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute(); const router = useRouter(); const toast = useToast()
const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const isCreate = computed(() => !route.params['id'])
const indexRoute = computed(() => `${prefix.value}/organizations`)
const organizationId = computed(() => route.params['id'] ? Number(route.params['id']) : null)

/** Po uložení sa upozornenie na web načíta znova — adresa sa mohla zmeniť. */
async function reloadWebsiteIssue() {
  if (organizationId.value === null) return
  try {
    applyWebsiteIssue(await showOrganization(scope.value, organizationId.value))
  } catch { /* upozornenie nie je kritické — formulár funguje aj bez neho */ }
}

// Číselníky sú v Accounte (App\Enums), tu ostávajú len hodnoty — popisky
// sa hľadajú v slovníku pod tým istým kľúčom. Hodnoty musia sedieť na enum,
// Account inak požiadavku odmietne.
const LEGAL_FORMS = ['sro', 'zivnost', 'as', 'ks', 'vos', 'druzstvo', 'nezisk', 'fyzicka', 'ine'] as const

const VAT_MODES = ['non_payer', 'payer', 'reg_7', 'reg_7a'] as const

const COUNTRIES = ['SK', 'CZ', 'AT', 'HU', 'PL'] as const

// Nie každý platiaci je firma. Od občana sa IČO ani zápis v registri
// pýtať nedá – nikdy ich mať nebude.
const subjectTypes = computed(() => [
  { person: false, label: t('organizations.subject.company'), hint: t('organizations.subject.companyHint') },
  { person: true, label: t('organizations.subject.person'), hint: t('organizations.subject.personHint') },
])

const { municipalities, loadMunicipalities } = useFormOptions(scope.value)

const validation = provideFormValidation()

const form = ref({
  title: '',
  person: false,
  village_id: null as number | null,
  email: '',
  phone: '',
  website: '',
  description: '',
  status: 'draft',
})

const isPerson = computed(() => form.value.person)

/** Vysvetlivka k vybranej možnosti — nahrádza popisy pri oboch kartách. */
const subjectHint = computed(() =>
  subjectTypes.value.find(type => type.person === form.value.person)?.hint ?? ''
)

const account = ref(accountToForm(null))
const accountData = ref<OrganizationAccountData | null>(null)

/* Kanály firmy a ich tímy. Ľudia sa k firme nepriraďujú priamo — členom
   sa je v kanáli, preto sa tu pripája a odpája kanál, nie používateľ. */
const canals = ref<OrganizationCanal[]>([])
const canalOptions = ref<CanalItem[]>([])
const canalToAttach = ref<number | null>(null)
const canalsError = ref<string | null>(null)
const detaching = ref<number | null>(null)
const attaching = ref(false)

const ROLES = ['owner', 'editor', 'checkin'] as const

/** Neznámu rolu zo servera ukážeme tak, ako prišla — radšej než prázdno. */
function roleLabel(role: string | null) {
  if (!role) return t('organizations.roles.member')

  return ROLES.includes(role as typeof ROLES[number])
    ? t(`organizations.roles.${role as typeof ROLES[number]}`)
    : role
}

/** Ponúkame len kanály, ktoré pod firmou ešte nefakturujú. */
const attachableCanals = computed(() => {
  const attached = new Set(canals.value.map(c => c.id))
  return canalOptions.value.filter(c => !attached.has(c.id))
})

const errors = ref<Record<string, string>>({})

// Upozornenie na neodpovedajúcu webovú adresu (overuje sa na pozadí,
// viď App\Services\Attributes na backende).
const { apply: applyWebsiteIssue, issue: websiteIssue } = useWebsiteIssue(() => form.value.website)
const serverError = ref<string | null>(null)
const errorBanner = ref<HTMLElement | null>(null)
const saving = ref(false)

const lookingUp = ref(false)
const lookupMessage = ref<string | null>(null)
const lookupOk = ref(false)

const accountLine = computed(() => {
  const a = accountData.value
  if (!a) return null
  return [a.name, a.identifiers?.ico ? `IČO ${a.identifiers.ico}` : null].filter(Boolean).join(' · ')
})

const missingBilling = computed(() => accountData.value?.billing?.missing ?? [])

/* Zhrnutia do hlavičiek zabalených sekcií — kým je sekcia zavretá, toto je
   jediné, čo o nej človek vidí. */
const contactNote = computed(() =>
  [form.value.email, form.value.phone, form.value.website].filter(Boolean).join(' · ')
  || t('organizations.contact.empty')
)

const billingNote = computed(() => {
  if (missingBilling.value.length) {
    return t('organizations.billing.missingShort', { fields: missingBilling.value.join(', ') })
  }

  return accountLine.value ?? t('organizations.billing.note')
})

const canalsNote = computed(() => {
  if (!canals.value.length) return t('organizations.canals.note')

  const members = canals.value.reduce((n, c) => n + c.members.length, 0)

  return `${plural('organizations.counts.canals', canals.value.length)} · ${plural('organizations.counts.members', members)}`
})

/* Chyba zo servera v zabalenej sekcii by ostala neviditeľná — sekcia sa preto
   otvorí sama. Adresa patrí firme k IČU a súkromnej osobe k fakturácii,
   tak podľa toho otvárame aj tú správnu. */
const ADDRESS_FIELDS = ['street', 'city', 'postal_code', 'country']
const REGISTER_FIELDS = [
  'ico', 'legal_name', 'dic', 'ic_dph', ...ADDRESS_FIELDS,
  'register_court', 'register_section', 'register_insert',
]
const BILLING_FIELDS = ['billing_email', 'bank_name', 'iban', 'swift']

function hasAccountError(fields: string[]) {
  return fields.some(field => !!errors.value[`account.${field}`])
}

const hasRegisterError = computed(() => !isPerson.value && hasAccountError(REGISTER_FIELDS))

const hasBillingError = computed(() =>
  hasAccountError(BILLING_FIELDS) || (isPerson.value && hasAccountError(ADDRESS_FIELDS))
)

/** Stav overenia fakturačného e-mailu. Vypĺňa ho Account, Event ho len ukazuje. */
const billingEmailState = computed(() => {
  const contact = accountData.value?.contact
  if (!contact?.billing_email_effective) return null

  return contact.billing_email_verified
    ? { verified: true, label: t('organizations.billing.emailVerified') }
    : { verified: false, label: t('organizations.billing.emailUnverified') }
})

/** Predvyplnenie z RPO/ARES. Prepisujeme len prázdne polia — ručne zadané
 *  údaje sú spravidla presnejšie než historizovaný výpis z registra. */
async function runLookup() {
  lookupMessage.value = null
  lookingUp.value = true
  try {
    const res = await lookupIco(scope.value, account.value.ico, account.value.country?.toLowerCase() || 'sk')

    if (!res.found) {
      lookupOk.value = false
      // `error` skladá Account, a už v jazyku požiadavky — vlastnú vetu
      // použijeme len vtedy, keď žiadnu neposlal.
      lookupMessage.value = res.error ?? t('organizations.register.notFound')
      return
    }

    const street = [res.street, res.street_no].filter(Boolean).join(' ')

    fill('legal_name', res.legal_name ?? res.name)
    fill('legal_form', res.legal_form)
    fill('dic', res.dic)
    fill('ic_dph', res.ic_dph)
    fill('street', street)
    fill('city', res.city)
    fill('postal_code', res.postal_code)
    fill('country', res.country)
    fill('register_court', res.register_court)
    fill('register_section', res.register_section)
    fill('register_insert', res.register_insert)

    if (!form.value.title && res.name) form.value.title = res.name

    lookupOk.value = true
    lookupMessage.value = t('organizations.register.loaded', { name: res.name ?? '' }).trim()
  } catch (e: unknown) {
    // Nepodmienené „register je nedostupný" tu už raz stálo hodinu hľadania:
    // odmietnuté právo aj prekročený limit vyzerali ako výpadok registra.
    const response = (e as { response?: { status?: number; data?: { message?: string } } })?.response

    lookupOk.value = false
    lookupMessage.value = response?.status === 429
      ? t('organizations.register.tooManyAttempts')
      : response?.data?.message ?? t('organizations.register.failed')
  } finally {
    lookingUp.value = false
  }
}

function fill(key: keyof typeof account.value, value: string | null | undefined) {
  if (value && !account.value[key]) account.value[key] = value
}

onMounted(async () => {
  loadMunicipalities()
  if (isCreate.value) return

  try {
    const o = await showOrganization(scope.value, Number(route.params['id']))
    form.value = {
      title: o.title,
      person: o.person,
      village_id: o.villageId,
      email: o.email ?? '',
      phone: o.phone ?? '',
      website: o.website ?? '',
      description: o.description ?? '',
      status: o.status,
    }
    accountData.value = o.account
    account.value = accountToForm(o.account)
    canals.value = o.canals
    applyWebsiteIssue(o)
  } catch {
    serverError.value = t('organizations.form.loadFailed')
  }

  // Zoznam pre „Priradiť kanál". Zlyhanie tu nesmie zhodiť celú stránku —
  // formulár firmy funguje aj bez neho.
  try {
    canalOptions.value = (await indexCanals(scope.value, { per_page: 100 })).data
  } catch {
    canalOptions.value = []
  }
})

/** Znovu načíta kanály firmy po zmene väzby — server je zdroj pravdy. */
async function reloadCanals() {
  const o = await showOrganization(scope.value, Number(route.params['id']))
  canals.value = o.canals
}

async function attach() {
  if (!canalToAttach.value) return

  canalsError.value = null
  attaching.value = true
  try {
    await attachCanalToOrganization(scope.value, Number(route.params['id']), Number(canalToAttach.value))
    canalToAttach.value = null
    await reloadCanals()
    toast.success(t('organizations.canals.attached'))
  } catch (e: unknown) {
    canalsError.value = apiMessage(e) ?? t('organizations.canals.attachFailed')
  } finally {
    attaching.value = false
  }
}

async function detach(canal: OrganizationCanal) {
  if (!confirm(t('organizations.canals.detachConfirm', { name: canal.name }))) return

  canalsError.value = null
  detaching.value = canal.id
  try {
    await detachCanalFromOrganization(scope.value, Number(route.params['id']), canal.id)
    await reloadCanals()
    toast.success(t('organizations.canals.detached'))
  } catch (e: unknown) {
    // Server bráni odpojiť poslednú väzbu — bez nej by sa používateľ
    // k firme v dashboarde už nedostal. Hlášku ukazujeme jeho slovami.
    canalsError.value = apiMessage(e) ?? t('organizations.canals.detachFailed')
  } finally {
    detaching.value = null
  }
}

function apiMessage(e: unknown): string | null {
  const data = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })?.response?.data
  return data?.errors?.['canal_id']?.[0] ?? data?.message ?? null
}

async function submit() {
  validation.markValidated()
  errors.value = {}; serverError.value = null; saving.value = true

  const payload = { ...form.value, account: account.value }

  try {
    if (isCreate.value) {
      const o = await createOrganization(scope.value, payload)
      toast.success(t(o.accountUuid ? 'organizations.form.createdAndSent' : 'organizations.form.created'))
      router.replace(`${prefix.value}/organizations/${o.id}/edit`)
    } else {
      const o = await updateOrganization(scope.value, Number(route.params['id']), payload)
      accountData.value = o.account
      toast.success(t('organizations.form.saved'))
      await reloadWebsiteIssue()
    }
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    if (resp?.errors) errors.value = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    serverError.value = resp?.message ?? t('organizations.form.saveFailed')
    await scrollToError(errorBanner)
  } finally {
    saving.value = false
  }
}
</script>
