<template>
  <FormField v-model="model" type="checkbox" required :validated="validated" :error="error">
    <template #label>
      {{ t('auth.register.termsPrefix')
      }}<RouterLink to="/obchodne-podmienky" target="_blank" class="underline">{{ t('auth.register.termsLink') }}</RouterLink>{{
        t('auth.register.termsMiddle')
      }}<RouterLink to="/ochrana-osobnych-udajov" target="_blank" class="underline">{{ t('auth.register.privacyLink') }}</RouterLink>{{
        t('auth.register.termsSuffix')
      }}<span class="form-required" aria-hidden="true">*</span>
    </template>
  </FormField>
</template>

<script setup lang="ts">
/**
 * Súhlas s obchodnými podmienkami — všade, kde vzniká účet (registrácia,
 * sprievodca plagátom). Jedno miesto, lebo znenie súhlasu aj adresy
 * dokumentov musia byť na všetkých vstupoch rovnaké.
 *
 * Zámerne nezaškrtnuté: predvyplnený súhlas nie je súhlas.
 *
 * Odkazy sa otvárajú v novej karte, aby si človek pri čítaní podmienok
 * nezmazal rozpísaný formulár.
 */
import FormField from '@/components/FormField.vue'
import { t } from '@/i18n'

const model = defineModel<boolean>({ required: true })

defineProps<{
  /** Prebehla už validácia formulára? Pozri FormField. */
  validated?: boolean
  /** Chyba zo servera, ak by sa políčko obišlo. */
  error?: string | null
}>()
</script>
