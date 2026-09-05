<template>
  <div ref="rootEl" class="embed-root">
    <RouterView />

    <!-- Podpis späť na portál. Je to jediná protihodnota za widget: organizátor
         dostane program na vlastný web, my dostaneme odkaz a návštevu. -->
    <p class="embed-footer">
      <a :href="portalUrl" target="_blank" rel="noopener">{{ t('embed.poweredBy') }}</a>
    </p>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { t } from '@/i18n'
import { absoluteUrl } from '@/utils/publicUrl'

/**
 * Layout pre vloženie na cudzí web (`/embed/...`).
 *
 * Bez hlavičky, navigácie a pätičky portálu — v iframe by zaberali celú výšku
 * a návštevník organizátorovho webu ich nečaká. Zostáva len obsah a jeden
 * odkaz späť.
 */
const rootEl = ref<HTMLElement | null>(null)
const portalUrl = absoluteUrl('/')

let observer: ResizeObserver | null = null

/**
 * Iframe si vlastnú výšku nastaviť nevie, takže ju hlásime rodičovi a ten ju
 * dopasuje (`ui/public/embed.js`). Bez toho by mal widget buď pevnú výšku
 * s vnútorným scrollom, alebo prázdne miesto pod obsahom.
 *
 * `targetOrigin` je `*` zámerne: doménu organizátorovho webu nepoznáme a poznať
 * ju nepotrebujeme. Správa nesie jediné číslo — výšku v pixeloch — takže nie je
 * čo vyzradiť.
 */
function postHeight() {
  if (!rootEl.value || window.parent === window) return

  window.parent.postMessage(
    { type: 'event-embed:height', height: Math.ceil(rootEl.value.getBoundingClientRect().height) },
    '*',
  )
}

onMounted(() => {
  postHeight()

  if (rootEl.value && 'ResizeObserver' in window) {
    observer = new ResizeObserver(postHeight)
    observer.observe(rootEl.value)
  }
})

onBeforeUnmount(() => {
  observer?.disconnect()
  observer = null
})
</script>

<style scoped>
@reference "tailwindcss";

/* Vlastné pozadie zámerne nie je: widget má sadnúť na farbu stránky, do ktorej
   je vložený. Preto ani okraje — tie si dá organizátor sám okolo iframe. */
.embed-root {
  @apply p-1;
}

.embed-footer {
  @apply mt-3 text-right text-[0.7rem] text-slate-400;
}

.embed-footer a {
  @apply text-slate-400 no-underline hover:text-slate-600 hover:underline;
}
</style>
