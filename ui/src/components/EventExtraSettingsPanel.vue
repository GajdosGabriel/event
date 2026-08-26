<template>
  <!-- Doplnkové nastavenia podujatia. Nie sú súčasťou typov lístkov ani skenera,
       preto majú vlastné tlačidlo Uložiť — inak by sa uložili „samé od seba“. -->
  <section class="rounded-2xl border border-slate-200 bg-white p-5">
    <h2 class="mb-1 text-lg font-semibold text-slate-800">{{ t('tickets.settings.heading') }}</h2>
    <p class="mb-3 text-xs text-slate-500">{{ t('tickets.settings.headingLead') }}</p>

    <p v-if="loading" class="text-sm text-slate-500">{{ t('tickets.settings.loading') }}</p>
    <p v-else-if="loadError" class="text-sm text-red-600">{{ loadError }}</p>

    <template v-else>
      <FormField
        v-model="settings.workshop_lock_on_start"
        type="checkbox"
        :label="t('tickets.settings.workshopLock')"
        :hint="t('tickets.settings.workshopLockHint')"
        class="mb-3"
      />
      <FormField v-model="settings.reminder_hours_before" type="select" :label="t('tickets.settings.reminder')">
        <option :value="null">{{ t('tickets.settings.reminderNone') }}</option>
        <option :value="2">{{ t('tickets.settings.reminder2h') }}</option>
        <option :value="24">{{ t('tickets.settings.reminder24h') }}</option>
        <option :value="48">{{ t('tickets.settings.reminder48h') }}</option>
        <option :value="168">{{ t('tickets.settings.reminder168h') }}</option>
        <template #footer>
          <span class="form-hint">
            {{ t('tickets.settings.reminderHint') }}
            <template v-if="reminderSentAt"> {{ t('tickets.settings.reminderSent', { date: reminderSentAt }) }}</template>
          </span>
        </template>
      </FormField>

      <div class="mt-4">
        <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
          {{ saving ? t('tickets.settings.saving') : t('tickets.settings.save') }}
        </button>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { showEvent } from '@/api/events'
import { updateTicketingSettings } from '@/api/ticketTypes'
import { currentLocale, t } from '@/i18n'
import { useToast } from '@/composables/useToast'
import FormField from '@/components/FormField.vue'

const props = defineProps<{ eventId: number }>()

const toast = useToast()

const loading = ref(true)
const loadError = ref<string | null>(null)
const saving = ref(false)
const reminderSentAt = ref<string | null>(null)

const settings = reactive({
  workshop_lock_on_start: true,
  reminder_hours_before: null as number | null,
})

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const ev = await showEvent('dashboard', props.eventId)
    settings.workshop_lock_on_start = ev.workshopLockOnStart ?? true
    settings.reminder_hours_before = ev.reminderHoursBefore
    reminderSentAt.value = ev.reminderSentAt
      ? new Date(ev.reminderSentAt).toLocaleString(currentLocale(), { day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' })
      : null
  } catch {
    loadError.value = t('tickets.settings.loadFailed')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    await updateTicketingSettings(props.eventId, {
      workshop_lock_on_start: settings.workshop_lock_on_start,
      reminder_hours_before: settings.reminder_hours_before,
    })
    toast.success(t('tickets.settings.saved'))
  } catch {
    toast.error(t('tickets.settings.saveFailed'))
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
