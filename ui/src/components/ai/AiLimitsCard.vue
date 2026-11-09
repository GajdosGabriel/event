<template>
  <section class="panel-card grid gap-4">
    <div>
      <h2 class="font-semibold text-slate-900">{{ t('aiUsage.limits.title') }}</h2>
      <p class="max-w-3xl text-sm text-slate-600">{{ t('aiUsage.limits.intro') }}</p>
    </div>

    <template v-if="limits">
      <p v-if="!limits.apiKey" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ t('aiUsage.limits.noApiKey') }}</p>

      <!-- Stav rozpočtu: minuté oproti uvoľnenému k dnešku a celému mesiacu -->
      <div class="grid gap-2">
        <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 text-sm">
          <span>
            <span class="text-slate-500">{{ t('aiUsage.limits.spent') }}:</span>
            <strong class="ml-1 tabular-nums text-slate-900">{{ usd(budget.spentUsd) }}</strong>
            <span class="tabular-nums text-slate-500"> / {{ budget.limitUsd > 0 ? usd(budget.limitUsd) : t('aiUsage.limits.unlimited') }}</span>
          </span>
          <span v-if="budget.limitUsd > 0">
            <span class="text-slate-500">{{ t('aiUsage.limits.released') }}:</span>
            <strong class="ml-1 tabular-nums text-slate-900">{{ usd(budget.releasedUsd) }}</strong>
          </span>
        </div>
        <div v-if="budget.limitUsd > 0" class="relative h-2 overflow-hidden rounded-full bg-slate-100">
          <div class="absolute inset-y-0 left-0 bg-slate-300" :style="{ width: percent(budget.releasedUsd) }" />
          <div
            class="absolute inset-y-0 left-0"
            :class="budget.available ? 'bg-amber-400' : 'bg-red-500'"
            :style="{ width: percent(budget.spentUsd) }"
          />
        </div>
        <p v-if="!budget.available" class="text-sm text-red-700">{{ t('aiUsage.limits.exhausted') }}</p>
      </div>

      <form class="grid gap-3" @submit.prevent="save">
        <FormField v-model="draft.profileEnrichment" type="checkbox" :label="t('aiUsage.limits.profileEnrichment')" />
        <FormField
          v-model="draft.emailDiscoveryAi"
          type="checkbox"
          :label="t('aiUsage.limits.emailDiscoveryAi')"
          :hint="t('aiUsage.limits.emailDiscoveryAiHint')"
        />
        <div class="max-w-xs">
          <FormField
            v-model="draft.monthlyLimitUsd"
            type="number"
            min="0.01"
            max="1000"
            step="0.01"
            required
            :label="t('aiUsage.limits.monthlyLimit')"
            :hint="t('aiUsage.limits.monthlyLimitHint')"
            :error="errors.monthlyLimitUsd"
          />
        </div>
        <div>
          <button type="submit" class="btn btn-primary" :disabled="saving || !dirty">{{ t('aiUsage.limits.save') }}</button>
        </div>
      </form>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { fetchAiLimits, updateAiLimits, type AiLimits, type AiLimitsInput } from '@/api/aiUsage'
import FormField from '@/components/FormField.vue'
import { useToast } from '@/composables/useToast'
import { localeTag, useI18n } from '@/i18n'
import { fieldErrors } from '@/utils/formErrors'

const { t } = useI18n()
const toast = useToast()

const limits = ref<AiLimits | null>(null)
const draft = reactive<AiLimitsInput>({ profileEnrichment: true, emailDiscoveryAi: true, monthlyLimitUsd: 1 })
const errors = ref<Record<string, string>>({})
const saving = ref(false)

const budget = computed(() => limits.value!.budget)

const dirty = computed(() => !!limits.value && (
  draft.profileEnrichment !== limits.value.profileEnrichment
  || draft.emailDiscoveryAi !== limits.value.emailDiscoveryAi
  || draft.monthlyLimitUsd !== limits.value.monthlyLimitUsd
))

onMounted(async () => {
  try {
    apply(await fetchAiLimits())
  } catch {
    toast.error(t('aiUsage.limits.loadFailed'))
  }
})

function apply(next: AiLimits) {
  limits.value = next
  draft.profileEnrichment = next.profileEnrichment
  draft.emailDiscoveryAi = next.emailDiscoveryAi
  draft.monthlyLimitUsd = next.monthlyLimitUsd
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    apply(await updateAiLimits({ ...draft }))
    toast.success(t('aiUsage.limits.saved'))
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.error(t('aiUsage.limits.saveFailed'))
  } finally {
    saving.value = false
  }
}

function percent(value: number): string {
  const limit = budget.value.limitUsd
  return `${limit > 0 ? Math.min(100, (value / limit) * 100) : 0}%`
}

function usd(value: number): string {
  const digits = value > 0 && value < 1 ? 4 : 2
  return `$${value.toLocaleString(localeTag(), { minimumFractionDigits: digits, maximumFractionDigits: digits })}`
}
</script>
