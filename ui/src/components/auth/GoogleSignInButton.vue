<template>
  <div class="grid gap-1">
    <div ref="host" class="google-btn-host min-h-[40px]"></div>
    <p v-if="failed" class="text-sm text-red-700">{{ t('auth.social.unavailable') }}</p>
  </div>
</template>

<script setup lang="ts">
/**
 * Oficiálne tlačidlo „Prihlásiť cez Google" (Google Identity Services).
 * Google si text lokalizuje sám; my dostaneme len ID token cez `credential`.
 */
import { onMounted, ref, useTemplateRef } from 'vue'
import { loadGoogleIdentity, googleClientId } from '@/composables/useGoogleIdentity'
import { currentLocale, t } from '@/i18n'

const props = withDefaults(defineProps<{ context?: 'signin' | 'signup' }>(), { context: 'signin' })
const emit = defineEmits<{ credential: [value: string] }>()

const host = useTemplateRef<HTMLElement>('host')
const failed = ref(false)

onMounted(async () => {
  const clientId = googleClientId()
  if (!clientId || !host.value) {
    failed.value = true
    return
  }

  try {
    const id = await loadGoogleIdentity()
    id.initialize({
      client_id: clientId,
      callback: (res) => {
        if (res.credential) emit('credential', res.credential)
      },
      ux_mode: 'popup',
      context: props.context,
      cancel_on_tap_outside: true,
    })
    id.renderButton(host.value, {
      theme: 'outline',
      size: 'large',
      text: props.context === 'signup' ? 'signup_with' : 'signin_with',
      shape: 'pill',
      logo_alignment: 'center',
      width: Math.min(380, Math.max(240, host.value.clientWidth || 360)),
      locale: currentLocale(),
    })
  } catch {
    failed.value = true
  }
})
</script>

<style scoped>
@reference "tailwindcss";

.google-btn-host {
  @apply flex justify-center;
}
</style>
