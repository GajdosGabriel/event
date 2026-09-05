<template>
  <div class="show-card">
    <h2 class="mb-1 text-base font-semibold text-slate-800">{{ t('embed.panel.title') }}</h2>
    <p class="mb-3 text-sm text-slate-500">{{ t('embed.panel.lead') }}</p>

    <div class="mb-3 grid gap-2 sm:grid-cols-2">
      <label class="text-xs text-slate-600">
        {{ t('embed.panel.count') }}
        <select v-model.number="limit" class="mt-1 h-8 w-full rounded-lg border border-slate-200 px-2 text-sm">
          <option v-for="option in [3, 5, 10, 20]" :key="option" :value="option">{{ option }}</option>
        </select>
      </label>

      <div class="grid content-end gap-1 text-xs text-slate-600">
        <label class="flex items-center gap-2">
          <input v-model="withTitle" type="checkbox" class="form-checkbox" />
          {{ t('embed.panel.withTitle') }}
        </label>
        <label class="flex items-center gap-2">
          <input v-model="withImages" type="checkbox" class="form-checkbox" />
          {{ t('embed.panel.withImages') }}
        </label>
      </div>
    </div>

    <pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs leading-relaxed text-slate-100"><code>{{ snippet }}</code></pre>

    <div class="mt-2 flex flex-wrap items-center gap-2">
      <button type="button" class="action-btn" @click="copy">
        {{ copied ? t('embed.panel.copied') : t('embed.panel.copy') }}
      </button>
      <a :href="previewUrl" target="_blank" rel="noopener" class="text-xs text-blue-600 hover:underline">
        {{ t('embed.panel.preview') }}
      </a>
    </div>

    <p class="mt-3 text-xs text-slate-500">{{ t('embed.panel.note') }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { t } from '@/i18n'
import { useToast } from '@/composables/useToast'
import { absoluteUrl } from '@/utils/publicUrl'

const props = defineProps<{
  canalId: number
  canalSlug?: string | null
}>()

const toast = useToast()

const limit = ref(5)
const withTitle = ref(true)
const withImages = ref(true)
const copied = ref(false)

/** `{slug}-{id}`, rovnako ako verejné adresy — routuje sa aj tak len id. */
const target = computed(() => (props.canalSlug ? `${props.canalSlug}-${props.canalId}` : String(props.canalId)))

const snippet = computed(() => {
  const attrs = [
    `src="${absoluteUrl('/embed.js')}"`,
    `data-canal="${target.value}"`,
    `data-limit="${limit.value}"`,
  ]

  if (!withTitle.value) attrs.push('data-title="0"')
  if (!withImages.value) attrs.push('data-images="0"')

  // Uzatváracia značka je poskladaná z kúskov zámerne: napísaná celá by
  // ukončila `<script setup>` bloku tohto komponentu už pri parsovaní SFC.
  return `<script ${attrs.join('\n        ')}>${'<'}/script>`
})

const previewUrl = computed(() => {
  const params = new URLSearchParams({ limit: String(limit.value) })
  if (!withTitle.value) params.set('title', '0')
  if (!withImages.value) params.set('images', '0')

  return absoluteUrl(`/embed/organizator/${target.value}?${params.toString()}`)
})

async function copy() {
  try {
    await navigator.clipboard.writeText(snippet.value)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch {
    // Schránka je bez HTTPS a v starších prehliadačoch zakázaná — kód je
    // aj tak na obrazovke, takže si ho vie človek označiť sám.
    toast.error(t('embed.panel.copyFailed'))
  }
}
</script>
