<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[600] flex items-center justify-center bg-black/40 p-4" @keydown.esc="!saving && emit('close')">
      <section role="dialog" aria-modal="true" :aria-label="t('roadmap.merge')" class="grid w-full max-w-lg gap-4 rounded-xl bg-white p-5">
        <h2>{{ t('roadmap.merge') }} {{ source.name ?? `#${source.id}` }}</h2>
        <label>{{ t('roadmap.target') }}</label>
        <SearchableSelect v-model="targetId" :options="[]" :source="`/admin/${resource}s`" @selected="targetName = $event.name" />
        <p v-if="targetId">{{ t('roadmap.mergeConfirm', { source: source.name ?? `#${source.id}`, target: `${targetName} (#${targetId})` }) }}</p>
        <p v-if="error" role="alert" class="text-red-600">{{ error }}</p>
        <div class="flex gap-2">
          <button type="button" class="btn btn-primary" :disabled="!targetId || targetId === source.id || saving" @click="merge">{{ t('roadmap.merge') }}</button>
          <button type="button" class="btn btn-secondary" :disabled="saving" @click="emit('close')">{{ t('common.cancel') }}</button>
        </div>
      </section>
    </div>
  </Teleport>
</template>
<script setup lang="ts">
import { ref } from 'vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import http from '@/api/index'
import { useI18n } from '@/i18n'
import { serverMessage } from '@/utils/publishFlow'
import { useToast } from '@/composables/useToast'
const props = defineProps<{ resource: 'canal' | 'venue'; source: { id: number; name?: string } }>()
const emit = defineEmits<{ close: []; merged: [] }>()
const { t } = useI18n()
const toast = useToast()
const targetName = ref('')
const targetId = ref<number | null>(null)
const saving = ref(false)
const error = ref('')
async function merge() {
  if (!targetId.value || saving.value || targetId.value === props.source.id) return
  saving.value = true
  error.value = ''
  try {
    await http.post(`/admin/${props.resource}s/${props.source.id}/merge`, { target_id: targetId.value })
    toast.success(t('roadmap.merged'))
    emit('merged')
  } catch (e) { error.value = serverMessage(e) ?? t('common.actionFailed') }
  finally { saving.value = false }
}
</script>
