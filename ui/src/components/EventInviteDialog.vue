<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[600] grid place-items-center overflow-y-auto bg-slate-900/40 p-4" @click.self="emit('close')">
      <section role="dialog" aria-modal="true" :aria-labelledby="titleId" class="grid w-full max-w-4xl gap-5 rounded-2xl bg-white p-5 md:grid-cols-[minmax(0,1fr)_18rem]">
        <!-- Náhľad je presne ten obrázok, ktorý sa stiahne — ten istý endpoint
             aj parametre, takže sa nemôžu rozísť (rovnako ako SlideStudio). -->
        <figure class="m-0 grid place-items-center overflow-hidden rounded-xl border border-slate-200 bg-slate-100 p-2">
          <img
            :key="previewUrl"
            :src="previewUrl"
            :alt="t('events.promotion.invite.preview')"
            class="block max-h-[70vh] w-auto max-w-full object-contain"
          />
        </figure>

        <div class="grid content-start gap-4">
          <div class="flex items-start justify-between gap-2">
            <h2 :id="titleId" class="text-lg font-semibold text-slate-900">{{ t('events.promotion.invite.title') }}</h2>
            <button ref="closeButton" type="button" class="action-btn" :aria-label="t('common.close')" @click="emit('close')">✕</button>
          </div>

          <div>
            <p class="text-sm font-medium text-slate-700">{{ t('events.promotion.invite.variant') }}</p>
            <div class="nav-tabs mt-1">
              <button
                v-for="option in variants"
                :key="option.value"
                type="button"
                class="nav-tab"
                :class="{ active: variant === option.value }"
                :aria-pressed="variant === option.value"
                @click="variant = option.value"
              >{{ option.label }}</button>
            </div>
            <p class="form-hint mt-1">{{ variantHint }}</p>
          </div>

          <div>
            <p class="text-sm font-medium text-slate-700">{{ t('events.promotion.invite.theme') }}</p>
            <div class="nav-tabs mt-1">
              <button
                v-for="option in themes"
                :key="option.value"
                type="button"
                class="nav-tab"
                :class="{ active: theme === option.value }"
                :aria-pressed="theme === option.value"
                @click="theme = option.value"
              >{{ option.label }}</button>
            </div>
          </div>

          <a :href="downloadUrl" download class="btn btn-primary">{{ t('events.promotion.invite.download') }}</a>
          <p class="form-hint m-0">{{ t('events.promotion.invite.hint') }}</p>
        </div>
      </section>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { eventInvitePngUrl, type EventInviteTheme, type EventInviteVariant } from '@/api/events'
import { currentLocale, useI18n } from '@/i18n'

const props = defineProps<{ eventId: number }>()
const emit = defineEmits<{ close: [] }>()
const { t } = useI18n()

const titleId = `invite-title-${props.eventId}`
const closeButton = ref<HTMLButtonElement | null>(null)

const variant = ref<EventInviteVariant>('square')
const theme = ref<EventInviteTheme>('dark')

const variants = computed<{ value: EventInviteVariant; label: string }[]>(() => [
  { value: 'square', label: t('events.promotion.invite.variantSquare') },
  { value: 'story', label: t('events.promotion.invite.variantStory') },
  { value: 'slide', label: t('events.promotion.invite.variantSlide') },
  { value: 'poster', label: t('events.promotion.invite.variantPoster') },
])

const themes = computed<{ value: EventInviteTheme; label: string }[]>(() => [
  { value: 'dark', label: t('events.promotion.invite.themeDark') },
  { value: 'light', label: t('events.promotion.invite.themeLight') },
  { value: 'bold', label: t('events.promotion.invite.themeBold') },
])

const variantHint = computed(() => ({
  square: t('events.promotion.invite.hintSquare'),
  story: t('events.promotion.invite.hintStory'),
  slide: t('events.promotion.invite.hintSlide'),
  poster: t('events.promotion.invite.hintPoster'),
}[variant.value]))

const params = computed(() => ({ variant: variant.value, theme: theme.value, lang: currentLocale() }))
const downloadUrl = computed(() => eventInvitePngUrl(props.eventId, { ...params.value, download: true }))

// Vykreslenie stojí server okolo sekundy, takže preklikávanie motívov sa
// zbiera krátkym oneskorením — inak by každý klik spustil ďalšie (a narazil
// na limiter `render`).
const previewUrl = ref(eventInvitePngUrl(props.eventId, params.value))
let debounce: number | undefined

watch(params, () => {
  window.clearTimeout(debounce)
  debounce = window.setTimeout(() => { previewUrl.value = eventInvitePngUrl(props.eventId, params.value) }, 250)
})

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') emit('close')
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
  closeButton.value?.focus()
})

onBeforeUnmount(() => {
  window.clearTimeout(debounce)
  document.removeEventListener('keydown', onKeydown)
})
</script>
