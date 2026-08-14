<template>
  <article class="legal-shell">
    <header class="legal-head">
      <h1 class="m-0 text-2xl md:text-3xl">{{ doc.title }}</h1>
      <p class="m-0 text-sm text-slate-600">{{ doc.perex }}</p>
    </header>

    <section v-for="section in doc.sections" :key="section.heading" class="legal-section">
      <h2 class="legal-heading">{{ section.heading }}</h2>
      <p v-for="(paragraph, i) in section.paragraphs" :key="i" class="legal-paragraph">{{ paragraph }}</p>
    </section>

    <footer class="legal-foot">
      <span>{{ t('legal.alsoSee') }}</span>
      <RouterLink :to="counterpart.to">{{ counterpart.label }}</RouterLink>
    </footer>
  </article>
</template>

<script setup lang="ts">
/**
 * Obchodné podmienky a ochrana osobných údajov. Jeden komponent pre oba
 * dokumenty — líšia sa len obsahom, ktorý si vypýta podľa `kind` (pozri routy
 * `legal-terms` a `legal-privacy`).
 */
import { computed } from 'vue'
import { useHead } from '@vueuse/head'
import { legalDocument, type LegalKind } from '@/content/legal'
import { useI18n } from '@/i18n'

const props = defineProps<{ kind: LegalKind }>()

const { t, locale } = useI18n()

// `locale` v závislosti zabezpečí, že sa dokument prekreslí po prepnutí jazyka.
const doc = computed(() => legalDocument(props.kind, locale.value))

const counterpart = computed(() =>
  props.kind === 'terms'
    ? { to: '/ochrana-osobnych-udajov', label: t('legal.privacy') }
    : { to: '/obchodne-podmienky', label: t('legal.terms') },
)

useHead(computed(() => ({
  title: `${doc.value.title} | Event`,
  meta: [
    { name: 'description', content: doc.value.perex },
    // Právne dokumenty nepotrebujú návštevnosť z vyhľadávania a v indexe by
    // konkurovali podujatiam; odkaz z pätičky a z registrácie stačí.
    { name: 'robots', content: 'noindex, follow' },
  ],
})))
</script>

<style scoped>
@reference "tailwindcss";

.legal-shell { @apply mx-auto my-5 grid w-full max-w-[52rem] gap-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:p-8; }
.legal-head { @apply grid gap-2 border-b border-slate-200 pb-4; }
.legal-section { @apply grid gap-2; }
.legal-heading { @apply m-0 text-base font-semibold text-slate-900; }
/* Dlhé odstavce sa čítajú lepšie s väčším riadkovaním než zvyšok aplikácie. */
.legal-paragraph { @apply m-0 text-sm leading-relaxed text-slate-700; }
.legal-foot { @apply flex flex-wrap gap-1 border-t border-slate-200 pt-4 text-sm text-slate-600; }
</style>
