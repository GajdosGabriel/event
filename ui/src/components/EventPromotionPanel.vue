<template>
  <!-- Propagácia zverejnenej akcie. Dve cesty podľa toho, kam pozvánka ide:
       do správy patrí odkaz (dá sa naň kliknúť a náhľad ukáže obrázok),
       na papier a plátno QR kód — mobilom sa nedá naskenovať ten istý mobil. -->
  <section ref="root" class="show-card grid scroll-mt-24 gap-4" :class="{ 'ring-2 ring-green-500': justPublished }" aria-labelledby="promotion-title">
    <!-- Privítanie hneď po zverejnení: na mobile je bočný panel až pod
         popisom, tak sa k nemu stránka sama posunie. -->
    <div v-if="justPublished" role="status" class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800">
      <p class="m-0 font-semibold">{{ t('events.promotion.published') }}</p>
      <p class="m-0">{{ t('events.promotion.publishedLead') }}</p>
    </div>
    <div>
      <h2 id="promotion-title" class="text-base font-semibold text-slate-800">{{ t('events.promotion.title') }}</h2>
      <p class="mt-1 text-sm text-slate-500">{{ t('events.promotion.lead') }}</p>
    </div>

    <div class="grid gap-2">
      <p class="field-legend mb-0">{{ t('events.promotion.link') }}</p>
      <ShareButtons :url="pageUrl" :title="event.name" :text="detailLine" />
    </div>

    <div class="grid gap-2">
      <div class="flex items-center justify-between gap-2">
        <p class="field-legend mb-0">{{ t('events.promotion.message') }}</p>
        <button type="button" class="text-xs font-medium text-blue-600 hover:underline" @click="copyMessage">
          {{ messageCopied ? t('events.promotion.copied') : t('events.promotion.copyMessage') }}
        </button>
      </div>
      <textarea
        :value="message"
        readonly
        rows="5"
        class="w-full resize-none rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
        :aria-label="t('events.promotion.message')"
        @focus="($event.target as HTMLTextAreaElement).select()"
      />
      <p class="form-hint m-0">{{ t('events.promotion.messageHint') }}</p>
    </div>

    <!-- Hotová pozvánka (fotka, údaje a QR v jednom obrázku) — na status,
         nástenku alebo plátno. Výber formátu a motívu potrebuje miesto,
         ktoré úzky bočný panel nemá, preto vlastné okno. -->
    <div class="grid gap-2">
      <p class="field-legend mb-0">{{ t('events.promotion.invite.title') }}</p>
      <p class="m-0 text-sm text-slate-600">{{ t('events.promotion.invite.lead') }}</p>
      <button type="button" class="btn btn-primary btn-sm justify-self-start" @click="inviteOpen = true">
        {{ t('events.promotion.invite.open') }}
      </button>
    </div>
    <EventInviteDialog v-if="inviteOpen" :event-id="event.id" @close="inviteOpen = false" />

    <div class="grid gap-2">
      <p class="field-legend mb-0">{{ t('events.promotion.qr') }}</p>
      <div class="flex items-start gap-3">
        <img
          :src="previewSrc"
          :alt="t('events.promotion.qrAlt', { name: event.name })"
          width="112"
          height="112"
          class="h-28 w-28 shrink-0 rounded-lg border border-slate-200 bg-white"
        />
        <div class="grid min-w-0 gap-1.5">
          <p class="m-0 text-sm text-slate-600">
            {{ event.reservable ? t('events.promotion.qrLeadsToSignup') : t('events.promotion.qrLeadsToPage') }}
          </p>
          <!-- Adresa aj ako text: kto kód vytlačí, môže ju pripísať pod neho. -->
          <p class="m-0 break-all font-mono text-xs text-slate-500">{{ inviteUrl }}</p>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <a :href="pngDownload" download class="btn btn-secondary btn-sm">{{ t('events.promotion.downloadPng') }}</a>
        <a :href="svgDownload" download class="btn btn-secondary btn-sm">{{ t('events.promotion.downloadSvg') }}</a>
      </div>
      <p class="form-hint m-0">{{ t('events.promotion.qrHint') }}</p>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import ShareButtons from '@/components/ShareButtons.vue'
import EventInviteDialog from '@/components/EventInviteDialog.vue'
import { eventQrPngUrl, eventQrSvgUrl } from '@/api/events'
import { useI18n } from '@/i18n'
import { absoluteUrl, publicEventInviteUrl, publicEventPath } from '@/utils/publicUrl'
import type { EventItem } from '@/types'

const props = defineProps<{ event: EventItem; justPublished?: boolean }>()
const { t } = useI18n()

const root = ref<HTMLElement | null>(null)

onMounted(() => {
  if (props.justPublished) root.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
})

/** Do správ kanonická adresa so slugom — v náhľade a v texte vyzerá lepšie. */
// `?z=odkaz` povie štatistike v správe akcie, koľko ľudí prišlo cez odkaz
// z tohto panela (ViewSource::Link); detail akcie ho z adresy hneď odstráni.
const pageUrl = computed(() => `${absoluteUrl(publicEventPath(props.event))}?z=odkaz`)
const inviteUrl = computed(() => publicEventInviteUrl(props.event))

const placeLabel = computed(() => {
  const e = props.event
  const place = e.venue?.name ?? e.locationName ?? null
  const town = e.municipality?.name ?? null
  if (place && town && place.toLowerCase().includes(town.toLowerCase())) return place
  return [place, town].filter(Boolean).join(', ') || null
})

const detailLine = computed(() => [props.event.dateRangeLabel, placeLabel.value].filter(Boolean).join(' · ') || null)

const message = computed(() => {
  const e = props.event
  const cta = e.reservable ? t('events.promotion.signupHere') : t('events.promotion.moreInfo')
  return [e.name, e.dateRangeLabel, placeLabel.value].filter(Boolean).join('\n') + `\n\n${cta} ${e.reservable ? `${pageUrl.value}#registracia` : pageUrl.value}`
})

// Náhľad v menšej veľkosti; na stiahnutie ide plné rozlíšenie na tlač.
const previewSrc = computed(() => eventQrPngUrl(props.event.id, { size: 336 }))
const pngDownload = computed(() => eventQrPngUrl(props.event.id, { download: true }))
const svgDownload = computed(() => eventQrSvgUrl(props.event.id))

const inviteOpen = ref(false)
const messageCopied = ref(false)
let copiedTimer: ReturnType<typeof setTimeout> | null = null

async function copyMessage() {
  try {
    await navigator.clipboard.writeText(message.value)
    messageCopied.value = true
    if (copiedTimer) clearTimeout(copiedTimer)
    copiedTimer = setTimeout(() => { messageCopied.value = false }, 2000)
  } catch {
    // Bez schránky (http, starý prehliadač) ostáva text označiteľný v poli.
  }
}

onBeforeUnmount(() => {
  if (copiedTimer) clearTimeout(copiedTimer)
})
</script>
