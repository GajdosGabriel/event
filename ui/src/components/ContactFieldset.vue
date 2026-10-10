<template>
  <fieldset class="field-group">
    <legend class="field-legend">{{ t(key('sections.contact')) }}</legend>
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
      <ContactListField v-if="additionalEmails" v-model:primary="email" v-model:additional="additionalEmails" type="email"
        :label="t(key('fields.email'))" :add-label="t('common.contactList.addEmail')" :maxlength="150"
        :error="emailsError" :hint="emailsHint" :badges="emailBadges" />
      <FormField v-else v-model="email" type="email" :label="t(key('fields.email'))" :maxlength="kind === 'canals' ? 150 : 100" :error="errors.email" />
      <FormField v-model="phone" type="tel" :label="t(key('fields.phone'))" maxlength="20" :error="errors.phone" :live-error="phoneError" />
      <FormField v-model="website" type="url" :label="t(key('fields.website'))" maxlength="150" :error="errors.website">
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
import ContactListField, { type ContactBadge } from '@/components/ContactListField.vue'
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
  /** Text pod zoznamom e-mailov a stav uložených adries (len so zoznamom). */
  emailsHint?: string
  emailBadges?: Record<string, ContactBadge>
}>()

const email = defineModel<string>('email', { required: true })
/** Ďalšie e-maily — keď ich rodič posiela, pole e-mailu je zoznam s primárnou adresou. */
const additionalEmails = defineModel<string[]>('additionalEmails')

const emailsError = computed(() => {
  const found = Object.keys(props.errors).find(k => k === 'email' || k === 'additional_emails' || k.startsWith('additional_emails.'))
  return found ? props.errors[found] : undefined
})
const phone = defineModel<string>('phone', { required: true })
const website = defineModel<string>('website', { required: true })

const phoneError = computed(() => (isValidPhone(phone.value) ? null : t('common.phoneInvalid')))

const key = (path: string) => `${props.kind}.${path}` as MessageKey
</script>
