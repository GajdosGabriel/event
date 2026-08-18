<template>
  <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
    <!--
      Náhľad je priamo tá snímka, ktorá sa stiahne — ten istý endpoint, tie isté
      parametre. Nič sa nekreslí dvakrát, takže sa náhľad a výsledok nemôžu
      rozísť. Server ju negeneruje zadarmo (okolo pol sekundy), preto sa adresa
      prepočíta až po krátkom oneskorení, keď človek preklikáva motívy.
    -->
    <figure class="overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
      <img
        :key="previewUrl"
        :src="previewUrl"
        :alt="t('questions.dashboard.materials.preview')"
        class="block w-full"
        :class="variant === 'square' ? 'aspect-square object-contain' : 'aspect-video'"
      />
    </figure>

    <div class="space-y-4">
      <div>
        <p class="form-label-text">{{ t('questions.dashboard.materials.theme') }}</p>
        <div class="mt-1 flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1">
          <button
            v-for="option in themes"
            :key="option.value"
            type="button"
            class="studio-toggle"
            :class="theme === option.value ? 'studio-toggle-active' : ''"
            @click="theme = option.value"
          >{{ option.label }}</button>
        </div>
      </div>

      <div>
        <p class="form-label-text">{{ t('questions.dashboard.materials.variant') }}</p>
        <div class="mt-1 flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1">
          <button
            v-for="option in variants"
            :key="option.value"
            type="button"
            class="studio-toggle"
            :class="variant === option.value ? 'studio-toggle-active' : ''"
            @click="variant = option.value"
          >{{ option.label }}</button>
        </div>
      </div>

      <!-- Sťahovanie je obyčajný odkaz: endpoint je verejný na token, takže
           netreba blob ani autorizačnú hlavičku. -->
      <div class="flex flex-col gap-2">
        <a :href="downloadUrl" class="btn btn-primary" download>
          {{ t('questions.dashboard.materials.downloadPng') }}
        </a>
        <a :href="pptxUrl" class="btn btn-secondary" download>
          {{ t('questions.dashboard.materials.downloadPptx') }}
        </a>
      </div>

      <div class="rounded-xl border border-slate-200 p-3">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
          {{ t('questions.dashboard.materials.link') }}
        </p>
        <p class="mt-1 break-all text-sm text-slate-800">{{ board.publicUrl }}</p>
        <p class="mt-2 text-lg font-bold tracking-wider text-slate-900">{{ board.code }}</p>
        <button type="button" class="action-btn mt-2 w-full" @click="copyLink">
          {{ copied ? t('questions.dashboard.materials.copied') : t('questions.dashboard.materials.copy') }}
        </button>
      </div>

      <a :href="board.wallUrl" target="_blank" rel="noopener" class="btn btn-secondary w-full">
        {{ t('questions.dashboard.materials.openWall') }}
      </a>

      <button type="button" class="w-full text-sm text-red-600 hover:underline" @click="$emit('rotate')">
        {{ t('questions.dashboard.materials.rotate') }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { currentLocale, useI18n } from '@/i18n'
import { slidePngUrl, slidePptxUrl, type QuestionBoardAdmin } from '@/api/questions'

const props = defineProps<{ board: QuestionBoardAdmin }>()
defineEmits<{ (e: 'rotate'): void }>()

const { t } = useI18n()

type Theme = 'dark' | 'light' | 'bold'
type Variant = 'slide' | 'square'

const theme = ref<Theme>('dark')
const variant = ref<Variant>('slide')
const copied = ref(false)

const themes = computed<{ value: Theme; label: string }[]>(() => [
  { value: 'dark', label: t('questions.dashboard.materials.themeDark') },
  { value: 'light', label: t('questions.dashboard.materials.themeLight') },
  { value: 'bold', label: t('questions.dashboard.materials.themeBold') },
])

const variants = computed<{ value: Variant; label: string }[]>(() => [
  { value: 'slide', label: t('questions.dashboard.materials.variantSlide') },
  { value: 'square', label: t('questions.dashboard.materials.variantSquare') },
])

/**
 * Jazyk ide do adresy, nie hlavičkou: `<img>` a `<a download>` idú mimo axiosu,
 * takže by `X-Locale` neposlali, a stiahnutý súbor by mal texty v predvolenom
 * jazyku bez ohľadu na to, čo má organizátor prepnuté.
 */
const params = computed(() => ({
  theme: theme.value,
  variant: variant.value,
  lang: currentLocale(),
}))

const downloadUrl = computed(() => slidePngUrl(props.board.token, params.value))
const pptxUrl = computed(() => slidePptxUrl(props.board.token, { theme: theme.value, lang: currentLocale() }))

function preview(extra: Record<string, string> = {}): string {
  return slidePngUrl(props.board.token, { ...params.value, inline: '1', ...extra })
}

// Náhľad sa nemení pri každom kliku okamžite — preklikávanie sem a tam by inak
// spustilo tri vykreslenia po pol sekunde za sebou.
const previewUrl = ref(preview())
let debounce: number | undefined

watch(params, () => {
  window.clearTimeout(debounce)
  debounce = window.setTimeout(() => { previewUrl.value = preview() }, 250)
})

// Po obnovení odkazu je adresa iná, takže sa prehliadač nemá o čo oprieť —
// ale keby náhodou, `t` cache obíde.
watch(() => props.board.token, () => { previewUrl.value = preview({ t: String(Date.now()) }) })

async function copyLink() {
  try {
    await navigator.clipboard.writeText(props.board.publicUrl)
    copied.value = true
    window.setTimeout(() => { copied.value = false }, 2000)
  } catch {
    window.prompt(t('questions.dashboard.materials.link'), props.board.publicUrl)
  }
}
</script>

<style scoped>
@reference "tailwindcss";

.studio-toggle {
  @apply rounded-md px-3 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:text-slate-900;
}

.studio-toggle-active {
  @apply bg-white text-blue-700 shadow-sm;
}

.form-label-text {
  @apply text-sm font-medium text-slate-700;
}
</style>
