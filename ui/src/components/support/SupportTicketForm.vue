<template>
  <form class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 md:p-5" @submit.prevent="submit">
    <div>
      <span class="mb-2 block text-sm font-semibold text-slate-700">{{ t('support.form.category') }}</span>
      <div class="flex flex-wrap gap-2" role="radiogroup">
        <button v-for="c in categories" :key="c.value" type="button" role="radio"
          :aria-checked="form.category === c.value"
          class="rounded-full border px-3 py-1.5 text-sm transition-colors"
          :class="form.category === c.value
            ? 'border-teal-600 bg-teal-50 font-medium text-teal-800'
            : 'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50'"
          @click="selectCategory(c.value)">
          {{ c.label }}
        </button>
      </div>
    </div>

    <label class="block">
      <span class="mb-1 block text-sm font-semibold text-slate-700">{{ t('support.form.subject') }}</span>
      <input v-model="form.subject" type="text" maxlength="150" required class="support-input"
        :placeholder="t('support.form.subjectPlaceholder')" />
    </label>

    <label class="block">
      <span class="mb-1 block text-sm font-semibold text-slate-700">{{ t('support.form.body') }}</span>
      <textarea v-model="form.body" rows="6" maxlength="5000" required class="support-input resize-y"
        :placeholder="t(`support.form.placeholder.${form.category}`)" />
      <span class="mt-1 flex justify-between text-xs text-slate-400">
        <span>{{ t('support.form.minLength') }}</span>
        <span>{{ form.body.length }} / 5000</span>
      </span>
    </label>

    <!-- Čo sa pošle navyše — nech človeka neprekvapí, že podpora vie, kde bol. -->
    <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
      {{ auth.canalName
        ? t('support.form.context', { canal: auth.canalName, page: form.page_url || '—' })
        : t('support.form.contextNoCanal', { page: form.page_url || '—' }) }}
    </p>

    <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

    <div class="flex items-center gap-3">
      <button type="submit" class="btn btn-primary"
        :disabled="sending || form.subject.trim().length < 3 || form.body.trim().length < 10">
        {{ sending ? t('support.form.sending') : t('support.form.submit') }}
      </button>
      <button v-if="cancellable" type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="emit('cancel')">
        {{ t('support.form.cancel') }}
      </button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { createSupportTicket, type SupportCategory, type SupportPage, type SupportTicket } from '@/api/support'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from '@/i18n'

const props = defineProps<{
  categories: SupportPage['meta']['categories']
  /** Odkiaľ človek prišiel (odkaz „Pomoc" posiela `from`) — podpora tak vidí obrazovku. */
  fromPage?: string | null
  initialCategory?: SupportCategory | null
  cancellable?: boolean
}>()
const emit = defineEmits<{ created: [ticket: SupportTicket]; cancel: [] }>()

const { t } = useI18n()
const auth = useAuthStore()

const form = reactive({
  category: (props.initialCategory ?? 'question') as SupportCategory,
  subject: '',
  body: '',
  page_url: props.fromPage?.slice(0, 500) ?? '',
})
const sending = ref(false)
const error = ref<string | null>(null)

function labelOf(value: SupportCategory) {
  return props.categories.find((c) => c.value === value)?.label ?? ''
}

// Predmet predvyplníme názvom kategórie — ale len kým ho človek sám neprepísal.
function selectCategory(value: SupportCategory) {
  const subject = form.subject.trim()
  const untouched = !subject || props.categories.some((c) => c.label === subject)
  form.category = value
  if (untouched) form.subject = labelOf(value)
}

watch(() => props.categories, () => {
  if (!form.subject) form.subject = labelOf(form.category)
}, { immediate: true })

async function submit() {
  sending.value = true
  error.value = null
  try {
    emit('created', await createSupportTicket({
      category: form.category,
      subject: form.subject.trim(),
      body: form.body.trim(),
      page_url: form.page_url || null,
      canal_id: auth.canalId,
    }))
  } catch (e: unknown) {
    const resp = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })?.response?.data
    error.value = resp?.errors ? Object.values(resp.errors).flat().join(' ') : (resp?.message ?? t('support.sendFailed'))
  } finally {
    sending.value = false
  }
}
</script>

<style scoped>
@reference "tailwindcss";

.support-input {
  @apply w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/30;
}
</style>
