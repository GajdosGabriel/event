<template>
  <fieldset class="field-group">
    <legend class="field-legend">{{ t(key('sections.contact')) }}</legend>
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
      <FormField v-model="email" type="email" :label="t(key('fields.email'))" :error="errors.email" />
      <FormField v-model="phone" type="tel" :label="t(key('fields.phone'))" :error="errors.phone ?? phoneError" />
      <FormField v-model="website" type="url" :label="t(key('fields.website'))" :error="errors.website">
        <template #footer>
          <AttributeIssueHint :issue="websiteIssue" :label="t(key('fields.websiteIssueLabel'))" />
        </template>
      </FormField>
    </div>
  </fieldset>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import AttributeIssueHint from '@/components/AttributeIssueHint.vue'
import FormField from '@/components/FormField.vue'
import { t, type MessageKey } from '@/i18n'
import type { AttributeIssue } from '@/types'
import { isValidPhone } from '@/utils/contact'

/** E-mail, telefón a web — kontakt, ktorý majú rovnako kanál aj miesto. */
const props = defineProps<{
  kind: 'canals' | 'venues'
  errors: Record<string, string>
  /** Upozornenie na neodpovedajúci web (viď useWebsiteIssue). */
  websiteIssue?: AttributeIssue | null
}>()

const email = defineModel<string>('email', { required: true })
const phone = defineModel<string>('phone', { required: true })
const website = defineModel<string>('website', { required: true })

const phoneError = computed(() => (isValidPhone(phone.value) ? null : t('common.phoneInvalid')))

const key = (path: string) => `${props.kind}.${path}` as MessageKey
</script>
