<template>
  <!--
    Koncept = stiahnutie z výpisu. Záznam, na ktorý odkazuje podujatie, sa
    stiahnuť nesmie — voľba zošedne a povie prečo, nech to nekončí až chybou
    po uložení. Spoločné pre kanál a miesto.
  -->
  <FormField v-model="status" type="select" :label="t(key('fields.status'))" :error="error"
    :hint="blockedReason ?? undefined">
    <option value="draft" :disabled="Boolean(blockedReason)">{{ t(key('statuses.draft')) }}</option>
    <option value="published">{{ t(key('statuses.published')) }}</option>
    <option value="archived">{{ t(key('statuses.archived')) }}</option>
  </FormField>
</template>

<script setup lang="ts">
import FormField from '@/components/FormField.vue'
import { t, type MessageKey } from '@/i18n'

const props = defineProps<{
  /** Preklady žijú pod `canals.*` / `venues.*`. */
  kind: 'canals' | 'venues'
  error?: string
  /** Prečo sa záznam nedá stiahnuť do konceptu (prázdne = dá sa). */
  blockedReason?: string | null
}>()

const status = defineModel<string>({ required: true })

const key = (path: string) => `${props.kind}.${path}` as MessageKey
</script>
