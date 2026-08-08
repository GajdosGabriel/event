<template>
  <div class="edit-shell">
    <div class="edit-card">
      <RouterLink :to="indexRoute" class="text-sm text-blue-700 no-underline">← Späť</RouterLink>
      <h1 class="my-2 text-2xl text-slate-900">{{ isCreate ? 'Nová organizácia' : 'Upraviť organizáciu' }}</h1>
      <p v-if="serverError" ref="errorBanner" class="text-red-600 mt-2">{{ serverError }}</p>

      <form class="grid gap-4 mt-4" @submit.prevent="submit">
        <!-- Typ subjektu rozhoduje, čo sa vôbec pýtame – preto je prvý. -->
        <fieldset class="field-group">
          <legend class="field-legend">Kto to je</legend>
          <div class="grid gap-3 sm:grid-cols-2">
            <label
              v-for="type in SUBJECT_TYPES"
              :key="type.label"
              class="flex cursor-pointer gap-3 rounded-xl border p-4 transition"
              :class="form.person === type.person
                ? 'border-blue-500 bg-blue-50/60 ring-1 ring-blue-500/20'
                : 'border-slate-200 hover:border-slate-300'"
            >
              <input v-model="form.person" type="radio" :value="type.person" class="mt-1" />
              <span class="min-w-0">
                <span class="block text-sm font-medium text-slate-900">{{ type.label }}</span>
                <span class="mt-0.5 block text-xs text-slate-500">{{ type.hint }}</span>
              </span>
            </label>
          </div>
        </fieldset>

        <!-- Pri organizácii je IČO prvé: z registra sa ním predvyplní
             zvyšok formulára, takže ručne písať treba čo najmenej. -->
        <fieldset v-if="!isPerson" class="field-group">
          <legend class="field-legend">IČO</legend>
          <p class="mb-3 text-sm text-slate-500">
            Zadaj IČO a načítaj údaje z obchodného registra — názov, adresu aj zápis
            v registri doplníme za teba.
          </p>
          <div class="grid gap-2 sm:flex sm:items-end sm:gap-3">
            <FormField v-model="account.ico" label="IČO" :error="errors['account.ico']" class="sm:w-56"
              placeholder="14287315" @keydown.enter.prevent="runLookup" />
            <button type="button" class="btn btn-primary" :disabled="lookingUp || !account.ico" @click="runLookup">
              {{ lookingUp ? 'Hľadám…' : 'Načítať z registra' }}
            </button>
            <span v-if="lookupMessage" class="text-sm" :class="lookupOk ? 'text-green-600' : 'text-amber-700'">
              {{ lookupMessage }}
            </span>
          </div>
        </fieldset>

        <fieldset class="field-group">
          <legend class="field-legend">Profil organizátora</legend>
          <p class="mb-3 text-sm text-slate-500">
            Toto vidia návštevníci portálu.
          </p>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField
              v-model="form.title"
              :label="isPerson ? 'Meno a priezvisko' : 'Názov'"
              required
              :error="errors['title']"
              class="lg:col-span-2"
            />
            <FormField v-model="form.village_id" label="Obec / Mesto" :error="errors['village_id']">
              <template #default="{ value, invalid, update }">
                <SearchableSelect
                  :model-value="value ?? null"
                  :options="municipalities"
                  placeholder="— vyberte obec —"
                  :invalid="invalid"
                  @update:model-value="update"
                />
              </template>
            </FormField>
            <FormField v-model="form.status" type="select" label="Stav" :error="errors['status']">
              <option value="draft">Koncept</option>
              <option value="published">Publikovaná</option>
              <option value="archived">Archivovaná</option>
            </FormField>
            <FormField v-model="form.email" type="email" label="E-mail" :error="errors['email']" />
            <FormField v-model="form.phone" type="tel" label="Telefón" :error="errors['phone']" />
            <FormField v-model="form.website" type="url" label="Web" :error="errors['website']" placeholder="https://" class="lg:col-span-2" />
            <FormField label="Popis" class="lg:col-span-2">
              <HtmlEditor v-model="form.description" min-height="130px" />
            </FormField>
          </div>
        </fieldset>

        <!-- ── Fakturačné údaje ──────────────────────────────────────────── -->
        <fieldset class="field-group">
          <legend class="field-legend">Fakturačné údaje</legend>

          <p class="mb-3 text-sm text-slate-500">
            Ukladajú sa do Accountu — centrálnej evidencie firiem. Ak tú istú
            firmu už eviduje iný projekt, Event sa na ňu podľa IČO naviaže
            a údaje sa nebudú zadávať druhýkrát.
          </p>

          <div v-if="accountLine" class="mb-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
            Naviazané na Account: <span class="font-medium text-slate-800">{{ accountLine }}</span>
          </div>

          <div v-if="missingBilling.length" class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Na vystavenie faktúry ešte chýba: {{ missingBilling.join(', ') }}.
          </div>

          <div v-if="!isPerson" class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField
              v-model="account.legal_name"
              label="Obchodné meno"
              :error="errors['account.legal_name']"
              :placeholder="form.title || 'Ako je zapísané v registri'"
              class="lg:col-span-2"
            />
            <FormField v-model="account.legal_form" type="select" label="Právna forma">
              <option value="">— nevybrané —</option>
              <option v-for="o in LEGAL_FORMS" :key="o.value" :value="o.value">{{ o.label }}</option>
            </FormField>
            <FormField v-model="account.dic" label="DIČ" :error="errors['account.dic']" />
            <FormField v-model="account.vat_mode" type="select" label="Vzťah k DPH">
              <option value="">— nevybrané —</option>
              <option v-for="o in VAT_MODES" :key="o.value" :value="o.value">{{ o.label }}</option>
            </FormField>
            <FormField
              v-model="account.ic_dph"
              label="IČ DPH"
              :error="errors['account.ic_dph']"
              hint="Overuje sa proti európskemu registru VIES."
              placeholder="SK2020123456"
            />
          </div>

          <h3 class="mt-5 mb-2 text-sm font-semibold text-slate-700">{{ isPerson ? 'Adresa' : 'Sídlo' }}</h3>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="account.street" label="Ulica a číslo" :error="errors['account.street']" class="lg:col-span-2" />
            <FormField v-model="account.city" label="Mesto" :error="errors['account.city']" />
            <FormField v-model="account.postal_code" label="PSČ" :error="errors['account.postal_code']" />
            <FormField v-model="account.country" type="select" label="Krajina" :error="errors['account.country']">
              <option value="SK">Slovensko</option>
              <option value="CZ">Česko</option>
              <option value="AT">Rakúsko</option>
              <option value="HU">Maďarsko</option>
              <option value="PL">Poľsko</option>
            </FormField>
          </div>

          <h3 v-if="!isPerson" class="mt-5 mb-2 text-sm font-semibold text-slate-700">Zápis v registri</h3>
          <div v-if="!isPerson" class="grid grid-cols-1 gap-3 lg:grid-cols-3">
            <FormField v-model="account.register_court" label="Súd" placeholder="napr. OS Trenčín" />
            <FormField v-model="account.register_section" label="Oddiel" placeholder="Sro" />
            <FormField v-model="account.register_insert" label="Vložka" placeholder="12345/R" />
          </div>

          <h3 class="mt-5 mb-2 text-sm font-semibold text-slate-700">Fakturácia a banka</h3>
          <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <FormField v-model="account.billing_email" type="email" label="E-mail na faktúry" :error="errors['account.billing_email']">
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
            <FormField v-model="account.bank_name" label="Banka" />
            <FormField v-model="account.iban" label="IBAN" :error="errors['account.iban']"
              placeholder="SK00 0000 0000 0000 0000 0000" />
            <FormField v-model="account.swift" label="SWIFT / BIC" :error="errors['account.swift']" />
          </div>
        </fieldset>

        <div class="flex items-center gap-3">
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? 'Ukladám…' : 'Uložiť a odoslať do Accountu' }}
          </button>
          <RouterLink :to="indexRoute" class="btn btn-secondary">Zrušiť</RouterLink>
        </div>
      </form>
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
} from '@/api/organizations'
import type { OrganizationAccountData } from '@/types'
import { useToast } from '@/composables/useToast'
import { useFormOptions } from '@/composables/useFormOptions'
import { provideFormValidation } from '@/composables/useFormValidation'
import { scrollToError } from '@/utils/scrollToError'
import FormField from '@/components/FormField.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import HtmlEditor from '@/components/HtmlEditor.vue'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute(); const router = useRouter(); const toast = useToast()
const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const isCreate = computed(() => !route.params['id'])
const indexRoute = computed(() => `${prefix.value}/organizations`)

// Číselníky sú v Accounte (App\Enums), tu ich stačí zobraziť. Hodnoty musia
// sedieť na enum — Account inak požiadavku odmietne.
const LEGAL_FORMS = [
  { value: 'sro', label: 'Spoločnosť s ručením obmedzeným' },
  { value: 'zivnost', label: 'Živnosť' },
  { value: 'as', label: 'Akciová spoločnosť' },
  { value: 'ks', label: 'Komanditná spoločnosť' },
  { value: 'vos', label: 'Verejná obchodná spoločnosť' },
  { value: 'druzstvo', label: 'Družstvo' },
  { value: 'nezisk', label: 'Nezisková organizácia' },
  { value: 'fyzicka', label: 'Fyzická osoba' },
  { value: 'ine', label: 'Iné' },
]

const VAT_MODES = [
  { value: 'non_payer', label: 'Neplatiteľ DPH' },
  { value: 'payer', label: 'Platiteľ DPH (§ 4)' },
  { value: 'reg_7', label: 'Registrovaný podľa § 7' },
  { value: 'reg_7a', label: 'Registrovaný podľa § 7a' },
]

// Nie každý platiaci je firma. Od občana sa IČO ani zápis v registri
// pýtať nedá – nikdy ich mať nebude.
const SUBJECT_TYPES = [
  { person: false, label: 'Organizácia', hint: 'Firma, živnostník alebo nezisková organizácia s IČO.' },
  { person: true, label: 'Súkromná osoba', hint: 'Občan bez IČO. Stačí meno a adresa.' },
]

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

const account = ref(accountToForm(null))
const accountData = ref<OrganizationAccountData | null>(null)

const errors = ref<Record<string, string>>({})
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

/** Stav overenia fakturačného e-mailu. Vypĺňa ho Account, Event ho len ukazuje. */
const billingEmailState = computed(() => {
  const contact = accountData.value?.contact
  if (!contact?.billing_email_effective) return null

  return contact.billing_email_verified
    ? { verified: true, label: 'E-mail je potvrdený.' }
    : { verified: false, label: 'Neoverený — na adresu sme poslali žiadosť o potvrdenie.' }
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
      lookupMessage.value = res.error ?? 'Firma sa v registri nenašla.'
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
    lookupMessage.value = `Načítané z registra: ${res.name ?? ''}`.trim()
  } catch (e: unknown) {
    // Nepodmienené „register je nedostupný" tu už raz stálo hodinu hľadania:
    // odmietnuté právo aj prekročený limit vyzerali ako výpadok registra.
    const response = (e as { response?: { status?: number; data?: { message?: string } } })?.response

    lookupOk.value = false
    lookupMessage.value = response?.status === 429
      ? 'Priveľa pokusov po sebe. Skús to o chvíľu znova.'
      : response?.data?.message ?? 'Vyhľadanie zlyhalo. Skús to znova alebo údaje vyplň ručne.'
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
  } catch {
    serverError.value = 'Organizáciu sa nepodarilo načítať.'
  }
})

async function submit() {
  validation.markValidated()
  errors.value = {}; serverError.value = null; saving.value = true

  const payload = { ...form.value, account: account.value }

  try {
    if (isCreate.value) {
      const o = await createOrganization(scope.value, payload)
      toast.success(o.accountUuid ? 'Organizácia vytvorená a odoslaná do Accountu.' : 'Organizácia vytvorená.')
      router.replace(`${prefix.value}/organizations/${o.id}/edit`)
    } else {
      const o = await updateOrganization(scope.value, Number(route.params['id']), payload)
      accountData.value = o.account
      toast.success('Uložené.')
    }
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    if (resp?.errors) errors.value = Object.fromEntries(Object.entries(resp.errors).map(([k, v]) => [k, v[0]]))
    serverError.value = resp?.message ?? 'Uloženie zlyhalo.'
    await scrollToError(errorBanner)
  } finally {
    saving.value = false
  }
}
</script>
