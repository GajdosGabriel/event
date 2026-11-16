<template>
  <!--
    Spodná lišta editora podujatia, kanála a miesta: stav záznamu + tlačidlá.
    Nahrádza výberové pole „Stav“: noví používatelia ho prehliadli, klikli
    Uložiť a mysleli si, že je záznam na webe. Tu sa o zverejnení rozhoduje
    tlačidlom a stav (ako je uložený, nie ako je rozklikaný) je stále na očiach.

    Obe hlavné tlačidlá sú `submit`: click nastaví cieľový stav skôr, než
    prehliadač zvaliduje a odošle formulár. Enter v poli spustí prvé submit
    tlačidlo v DOM — preto je vľavo to neškodné (Uložiť koncept / Uložiť zmeny).
  -->
  <div class="publish-bar sticky bottom-3 z-20 flex flex-wrap items-center gap-x-4 gap-y-3 rounded-xl border bg-white/95 p-3 shadow-lg backdrop-blur"
    :class="tone.border">
    <div class="flex min-w-0 flex-1 basis-64 items-start gap-2" role="status">
      <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" :class="tone.dot" aria-hidden="true" />
      <p class="m-0 text-sm leading-snug" :title="stateText">
        <strong class="text-slate-900">{{ t(`publishBar.state.${state}.title`) }}</strong>
        <span class="publish-bar-text text-slate-600"> · {{ stateText }}</span>
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <template v-if="state === 'new' || state === 'draft'">
        <button type="submit" class="btn btn-secondary" :disabled="busy" @click="status = 'draft'">
          {{ busyLabel ?? t('publishBar.saveDraft') }}
        </button>
        <button type="submit" class="btn btn-primary" :disabled="busy" @click="status = publishTarget">
          {{ publishTarget === 'scheduled' ? t('publishBar.schedule') : t('publishBar.publish') }}
        </button>
      </template>

      <template v-else-if="state === 'scheduled'">
        <button type="submit" class="btn btn-secondary" :disabled="busy" @click="status = publishTarget">
          {{ busyLabel ?? (publishTarget === 'scheduled' ? t('publishBar.saveChanges') : t('publishBar.publishNow')) }}
        </button>
        <button type="submit" class="btn btn-ghost" :disabled="busy" @click="status = 'draft'">{{ t('publishBar.unpublish') }}</button>
      </template>

      <template v-else-if="state === 'published'">
        <button type="submit" class="btn btn-primary" :disabled="busy" @click="status = 'published'">
          {{ busyLabel ?? t('publishBar.saveChanges') }}
        </button>
        <!-- Záznam, na ktorý odkazuje zverejnené podujatie, sa stiahnuť nesmie.
             Tlačidlo nie je `disabled`: to by na mobile nepovedalo prečo. -->
        <button v-if="blockedReason" type="button" class="btn btn-ghost opacity-60" :aria-disabled="true"
          :title="blockedReason" @click="toast.info(blockedReason)">{{ t('publishBar.unpublish') }}</button>
        <button v-else type="submit" class="btn btn-ghost" :disabled="busy" @click="status = 'draft'">{{ t('publishBar.unpublish') }}</button>
      </template>

      <template v-else>
        <button type="submit" class="btn btn-primary" :disabled="busy" @click="status = 'archived'">
          {{ busyLabel ?? t('publishBar.save') }}
        </button>
      </template>

      <button v-if="archivable && (state === 'draft' || state === 'published')" type="submit" class="btn btn-ghost"
        :disabled="busy" @click="archive">{{ t('publishBar.archive') }}</button>
      <RouterLink :to="cancelTo" class="btn btn-ghost">{{ t('publishBar.cancel') }}</RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { RouteLocationRaw } from 'vue-router'
import { t, localeTag } from '@/i18n'
import { useToast } from '@/composables/useToast'

const props = withDefaults(defineProps<{
  /** Stav, ako je uložený na serveri; null = záznam ešte neexistuje. */
  savedStatus: string | null
  saving: boolean
  cancelTo: RouteLocationRaw
  /** Prečo sa záznam nedá stiahnuť do konceptu (prázdne = dá sa). */
  blockedReason?: string | null
  /** Hlavné tlačidlo naplánuje namiesto okamžitého zverejnenia (podujatie). */
  scheduleLater?: boolean
  /** Uložený termín naplánovaného zverejnenia. */
  publishAt?: string | null
  /** Ponúknuť archiváciu (podujatie ju má jednosmernú, preto potvrdenie). */
  archivable?: boolean
  archiveConfirm?: string
}>(), { blockedReason: null, scheduleLater: false, publishAt: null, archivable: true, archiveConfirm: undefined })

/** Cieľový stav pre odoslanie — nastaví ho kliknuté tlačidlo. */
const status = defineModel<string>({ required: true })

const toast = useToast()

type State = 'new' | 'draft' | 'scheduled' | 'published' | 'archived'

const state = computed<State>(() => {
  const s = props.savedStatus
  return s === 'draft' || s === 'scheduled' || s === 'published' || s === 'archived' ? s : s === null ? 'new' : 'draft'
})

const publishTarget = computed(() => props.scheduleLater ? 'scheduled' : 'published')

const busy = computed(() => props.saving)
const busyLabel = computed(() => props.saving ? t('publishBar.saving') : null)

const stateText = computed(() => {
  if (state.value === 'scheduled' && props.publishAt) {
    const date = new Date(props.publishAt)
    if (!Number.isNaN(date.getTime())) {
      return t('publishBar.state.scheduled.textAt', {
        date: date.toLocaleString(localeTag(), { day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }),
      })
    }
  }
  return t(`publishBar.state.${state.value}.text`)
})

const tone = computed(() => ({
  new: { border: 'border-amber-300', dot: 'bg-amber-400' },
  draft: { border: 'border-amber-300', dot: 'bg-amber-400' },
  scheduled: { border: 'border-sky-300', dot: 'bg-sky-500' },
  published: { border: 'border-emerald-300', dot: 'bg-emerald-500' },
  archived: { border: 'border-slate-300', dot: 'bg-slate-400' },
}[state.value]))

function archive(e: MouseEvent) {
  if (!confirm(props.archiveConfirm ?? t('publishBar.archiveConfirm'))) {
    e.preventDefault()
    return
  }
  status.value = 'archived'
}
</script>

<style scoped>
/* Mobil: admin aj dashboard majú dole pevnú lištu sekcií (~4 rem) — bez
   posunu by tlačidlá skončili pod ňou. Vysvetlivka stavu sa tam nezmestí,
   nadpis stavu a farba stačia; plné znenie je v title. */
@media (max-width: 767px) {
  .publish-bar { bottom: 4.5rem; padding: 0.5rem; }
  .publish-bar-text { display: none; }
  .publish-bar :deep(.btn) { height: 2.25rem; padding: 0 0.75rem; font-size: 0.875rem; }
}
</style>
