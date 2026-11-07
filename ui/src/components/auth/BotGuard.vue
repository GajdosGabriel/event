<template>
  <!-- Honeypot: človek pole nevidí a nevyplní, bot áno. Backend potom
       odpovie falošným úspechom a nič nepošle (GuardAgainstBots). -->
  <div class="bot-guard-trap" aria-hidden="true">
    <label>
      Website
      <input v-model="model.website" type="text" name="website" tabindex="-1" autocomplete="off" />
    </label>
  </div>
  <div v-if="siteKey" ref="host" class="flex justify-center min-h-[65px]"></div>
</template>

<script setup lang="ts">
/**
 * Ochrana formulárov, ktoré posielajú e-mail na zadanú adresu (registrácia,
 * opätovné overenie, zabudnuté heslo): honeypot + Cloudflare Turnstile.
 * Polia `website` a `turnstile_token` idú v tele požiadavky vedľa formulára.
 *
 * Token je jednorazový — po každom odoslaní (aj neúspešnom) treba zavolať
 * `reset()`, inak by ďalší pokus poslal už použitý token.
 */
import { onBeforeUnmount, onMounted, useTemplateRef } from 'vue'
import { loadTurnstile, turnstileSiteKey } from '@/composables/useTurnstile'
import { currentLocale } from '@/i18n'
import type { BotGuardPayload } from '@/types'

const model = defineModel<BotGuardPayload>({ required: true })

const siteKey = turnstileSiteKey()
const host = useTemplateRef<HTMLElement>('host')
let api: TurnstileApi | null = null
let widgetId: string | null = null

onMounted(async () => {
  if (!siteKey || !host.value) return
  try {
    api = await loadTurnstile()
    widgetId = api.render(host.value, {
      sitekey: siteKey,
      language: currentLocale(),
      callback: (token) => { model.value.turnstile_token = token },
      'expired-callback': () => { model.value.turnstile_token = '' },
      'error-callback': () => { model.value.turnstile_token = '' },
    })
  } catch {
    // Skript sa nenačítal (blokovač, výpadok). Formulár ostane použiteľný,
    // backend odmietne odoslanie zrozumiteľnou hláškou.
  }
})

onBeforeUnmount(() => {
  if (api && widgetId) api.remove(widgetId)
})

function reset() {
  model.value.turnstile_token = ''
  if (api && widgetId) api.reset(widgetId)
}

defineExpose({ reset })
</script>

<style scoped>
/* Mimo obrazovky, nie display:none — niektoré boty skryté polia vynechávajú. */
.bot-guard-trap {
  position: absolute;
  left: -10000px;
  width: 1px;
  height: 1px;
  overflow: hidden;
}
</style>
