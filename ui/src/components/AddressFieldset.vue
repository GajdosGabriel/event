<template>
  <fieldset class="field-group">
    <legend class="field-legend">{{ title ?? t('address.section') }}</legend>
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
      <FormField
        v-model="street"
        :label="t('address.street')"
        :placeholder="t('address.streetPlaceholder')"
        :error="errors?.['street']"
        autocomplete="street-address"
        class="lg:col-span-2"
      />
      <FormField
        v-model="municipalityId"
        :label="t('address.municipality')"
        :required="required"
        :error="errors?.[municipalityKey]"
      >
        <template #default="{ value, invalid, update }">
          <SearchableSelect
            :model-value="(value as number | null) ?? null"
            :options="municipalities"
            :placeholder="t('address.municipalityPlaceholder')"
            :invalid="invalid"
            @update:model-value="update"
          />
        </template>
      </FormField>
      <FormField
        v-model="postcode"
        :label="t('address.postcode')"
        :error="errors?.['postcode']"
        autocomplete="postal-code"
      />
      <FormField
        v-model="country"
        :label="t('address.country')"
        :placeholder="t('address.countryPlaceholder')"
        :error="errors?.['country']"
        autocomplete="country-name"
        class="lg:col-span-2"
      />
      <p v-if="searching" class="lg:col-span-2 text-xs text-slate-500">{{ t('address.searching') }}</p>
    </div>
  </fieldset>
</template>

<script setup lang="ts">
/**
 * Editor adresy — spoločný pre miesto aj kanál.
 *
 * Robí tri veci, ktoré si predtým každý formulár riešil sám:
 *   • PSČ dopĺňa z číselníka obcí, nech sa neťuká ručne,
 *   • po zmene obce alebo ulice posunie polohu (geokóder na API),
 *   • drží presnosť polohy (`coordinatesSource`), aby sa stred obce navonok
 *     nevydával za presnú adresu.
 *
 * Poloha sa dopĺňa len po zásahu používateľa. Načítanie uloženého záznamu ide
 * cez `v-model` zvonku, nie cez tieto polia — inak by geokóder prepísal presné
 * súradnice hneď po otvorení formulára.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { geocodeAddress } from '@/api/address'
import FormField from '@/components/FormField.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import { useFormOptions } from '@/composables/useFormOptions'
import { t } from '@/i18n'
import type { AddressModel } from '@/types'

const props = withDefaults(defineProps<{
  scope: 'dashboard' | 'admin'
  /** Chyby z validácie na serveri, kľúčované názvom stĺpca. */
  errors?: Record<string, string>
  /** Ako sa obec volá v cieľovej tabuľke — kvôli kľúču chyby zo servera. */
  municipalityKey?: 'village_id' | 'municipality_id'
  required?: boolean
  title?: string
}>(), {
  errors: undefined,
  municipalityKey: 'municipality_id',
  required: true,
  title: undefined,
})

const model = defineModel<AddressModel>({ required: true })

const { municipalities, loadMunicipalities } = useFormOptions(props.scope)

onMounted(loadMunicipalities)

function patch(next: Partial<AddressModel>) {
  model.value = { ...model.value, ...next }
}

const street = computed({
  get: () => model.value.street,
  set: (value: string) => { patch({ street: value ?? '' }); scheduleGeocode() },
})

const postcode = computed({
  get: () => model.value.postcode,
  set: (value: string) => patch({ postcode: value ?? '' }),
})

const country = computed({
  get: () => model.value.country,
  set: (value: string) => patch({ country: value ?? '' }),
})

const municipalityId = computed<number | null>({
  get: () => model.value.municipalityId,
  set: (value) => selectMunicipality(value),
})

function zipOf(id: number | null): string | null {
  return id == null ? null : (municipalities.value.find(m => m.id === id)?.zip ?? null)
}

/** PSČ obce je v číselníku. Vyplnené pole prepíšeme len vtedy, keď v ňom sedí
 *  PSČ predošlej obce — ručne dopísané PSČ nikdy nezmizne. */
function selectMunicipality(id: number | null) {
  const zip = zipOf(id)
  const previousZip = zipOf(model.value.municipalityId)
  const keepPostcode = model.value.postcode && model.value.postcode !== previousZip

  patch({
    municipalityId: id,
    postcode: zip && !keepPostcode ? zip : model.value.postcode,
  })

  scheduleGeocode()
}

const searching = ref(false)
let timer: ReturnType<typeof setTimeout> | null = null
/** Odpoveď na starší dopyt už nie je pravda — mohla by prebiť ten čerstvejší. */
let request = 0

function scheduleGeocode() {
  if (timer) clearTimeout(timer)
  timer = setTimeout(runGeocode, 600)
}

async function runGeocode() {
  const id = model.value.municipalityId
  if (id == null) return

  const ticket = ++request
  searching.value = true

  try {
    const res = await geocodeAddress(props.scope, {
      municipality_id: id,
      street: model.value.street || null,
      postcode: model.value.postcode || null,
      country: model.value.country || null,
    })

    if (ticket !== request) return
    if (res.latitude == null || res.longitude == null) return

    patch({ latitude: res.latitude, longitude: res.longitude, coordinatesSource: res.source })
  } catch {
    /* mapa ostane, kde bola — geokóder nesmie zhodiť formulár */
  } finally {
    if (ticket === request) searching.value = false
  }
}

onBeforeUnmount(() => { if (timer) clearTimeout(timer) })

/** Po hromadnom vyplnení zvonku (AI detekcia) sa poloha dohľadá na požiadanie. */
defineExpose({ geocode: runGeocode })
</script>
