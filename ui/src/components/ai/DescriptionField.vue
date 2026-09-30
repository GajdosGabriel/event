<template>
  <!--
    Popis záznamu s pomocníkom: editor a pod ním AiAssistPanel. Jedno miesto pre
    to, čo mali kanál a miesto rovnako skopírované — panel si sám rozhodne, čo
    z neho ukázať (poznámky z kontroly, ukazovateľ pripravenosti, AI pomocník).
  -->
  <div class="grid gap-3">
    <FormField :label="label" :error="error">
      <HtmlEditor v-model="body" :placeholder="placeholder" :min-height="minHeight" />
    </FormField>

    <AiAssistPanel v-model="body" :kind="kind" :scope="scope" :values="values"
      :name="name" :context="context" :record-id="recordId" />
  </div>
</template>

<script setup lang="ts">
import FormField from '@/components/FormField.vue'
import HtmlEditor from '@/components/HtmlEditor.vue'
import AiAssistPanel from '@/components/ai/AiAssistPanel.vue'

defineProps<{
  label: string
  error?: string
  placeholder?: string
  minHeight?: string
  /** Druh záznamu pre AI a kontrolu obsahu — `canal`, `venue`. */
  kind: 'canal' | 'venue'
  scope: 'admin' | 'dashboard'
  /** Hodnoty formulára pod menami z `config/content_review.php`. */
  values: Record<string, unknown>
  name?: string
  /** Obec ako kontext pre AI — bez nej model o polohe radšej nepíše. */
  context?: string
  /** Id uloženého záznamu. Bez neho sa posudok nemá čím vypýtať. */
  recordId?: number | null
}>()

const body = defineModel<string>({ required: true })
</script>
