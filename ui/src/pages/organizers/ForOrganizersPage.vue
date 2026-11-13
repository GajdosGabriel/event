<template>
  <div class="mx-auto grid w-full max-w-[1100px] gap-10 pb-6">
    <!-- Úvod. Rovnaká tmavá scéna ako na úvodnej stránke, aby organizátor
         prechádzajúci z homepage necítil, že odišiel inam. -->
    <section class="relative isolate overflow-clip rounded-3xl bg-slate-950 px-5 py-10 text-white shadow-xl shadow-slate-900/10 sm:rounded-[2rem] sm:px-10 sm:py-14">
      <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        <div class="absolute -top-32 -left-24 h-96 w-96 rounded-full bg-rose-500/30 blur-3xl"></div>
        <div class="absolute -right-24 -bottom-40 h-[26rem] w-[26rem] rounded-full bg-indigo-500/35 blur-3xl"></div>
      </div>
      <p class="m-0 text-xs font-semibold tracking-wider text-emerald-300 uppercase">{{ page.hero.eyebrow }}</p>
      <h1 class="mt-3 max-w-3xl text-3xl leading-[1.1] font-extrabold tracking-tight text-balance sm:text-5xl">
        {{ page.hero.title }}<span class="bg-linear-to-r from-amber-200 via-rose-300 to-fuchsia-300 bg-clip-text text-transparent">{{ page.hero.titleAccent }}</span>
      </h1>
      <p class="mt-4 max-w-2xl text-base leading-relaxed text-slate-300 sm:text-lg">{{ page.hero.lead }}</p>
      <div class="mt-6 flex flex-wrap gap-2">
        <RouterLink :to="target('poster')" class="inline-flex h-11 items-center gap-2 rounded-xl bg-white px-5 text-sm font-bold text-slate-900 no-underline shadow-lg transition hover:bg-emerald-300">
          <AppIcon name="upload" class="h-4 w-4" />
          {{ page.hero.primary }}
        </RouterLink>
        <RouterLink v-if="!auth.isAuthenticated" :to="target('register')" class="inline-flex h-11 items-center rounded-xl px-4 text-sm font-semibold text-white no-underline ring-1 ring-inset ring-white/30 transition hover:bg-white/10">
          {{ page.hero.secondary }}
        </RouterLink>
      </div>
      <ul class="m-0 mt-8 flex list-none flex-wrap gap-x-6 gap-y-2 p-0 text-sm text-slate-200">
        <li v-for="promise in page.promises" :key="promise" class="flex items-center gap-2">
          <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-400/20 text-emerald-300" aria-hidden="true">✓</span>
          {{ promise }}
        </li>
      </ul>
    </section>

    <!-- Funkcie -->
    <section aria-labelledby="org-features">
      <h2 id="org-features" class="m-0 text-2xl font-bold text-slate-900 sm:text-3xl">{{ page.featuresTitle }}</h2>
      <p class="mt-2 max-w-2xl text-slate-600">{{ page.featuresLead }}</p>
      <ul class="m-0 mt-6 grid list-none gap-4 p-0 sm:grid-cols-2 lg:grid-cols-3">
        <li v-for="feature in page.features" :key="feature.title" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
          <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
            <AppIcon :name="feature.icon" class="h-5 w-5" />
          </span>
          <h3 class="mt-3 mb-1 text-base font-semibold text-slate-900">{{ feature.title }}</h3>
          <p class="m-0 text-sm leading-relaxed text-slate-600">{{ feature.text }}</p>
        </li>
      </ul>
    </section>

    <!-- Ako začať: tri vstupy podľa toho, kde organizátor práve je. -->
    <section aria-labelledby="org-start">
      <h2 id="org-start" class="m-0 text-2xl font-bold text-slate-900 sm:text-3xl">{{ page.startTitle }}</h2>
      <ol class="m-0 mt-6 grid list-none gap-4 p-0 md:grid-cols-3">
        <li v-for="(step, index) in page.steps" :key="step.title" class="flex flex-col rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-sm font-bold text-white">{{ index + 1 }}</span>
          <h3 class="mt-3 mb-1 text-base font-semibold text-slate-900">{{ step.title }}</h3>
          <p class="m-0 flex-1 text-sm leading-relaxed text-slate-600">{{ step.text }}</p>
          <RouterLink :to="target(step.action)" class="mt-4 inline-flex w-fit items-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white no-underline hover:bg-blue-700">
            {{ step.cta }}
          </RouterLink>
        </li>
      </ol>
      <p class="mt-4 rounded-2xl bg-blue-50/70 p-4 text-sm text-slate-700 ring-1 ring-inset ring-blue-100">{{ page.claimNote }}</p>
    </section>

    <!-- Časté otázky. Natívne <details> — bez JS, klávesnica aj čítačka
         ich obslúžia samy. -->
    <section aria-labelledby="org-faq">
      <h2 id="org-faq" class="m-0 text-2xl font-bold text-slate-900 sm:text-3xl">{{ page.faqTitle }}</h2>
      <div class="mt-6 grid gap-3">
        <details v-for="item in page.faq" :key="item.q" class="group rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
          <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold text-slate-900">
            {{ item.q }}
            <AppIcon name="chevronDown" class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-open:rotate-180" />
          </summary>
          <p class="mt-3 mb-0 text-sm leading-relaxed text-slate-600">{{ item.a }}</p>
        </details>
      </div>
    </section>

    <!-- Záver -->
    <section class="rounded-3xl bg-slate-900 px-5 py-8 text-center text-white sm:px-10">
      <h2 class="m-0 text-2xl font-bold">{{ page.cta.title }}</h2>
      <p class="mt-2 text-slate-300">{{ page.cta.lead }}</p>
      <div class="mt-5 flex flex-wrap justify-center gap-2">
        <RouterLink :to="target('poster')" class="inline-flex h-11 items-center gap-2 rounded-xl bg-white px-5 text-sm font-bold text-slate-900 no-underline hover:bg-emerald-300">
          <AppIcon name="upload" class="h-4 w-4" />
          {{ page.cta.primary }}
        </RouterLink>
        <RouterLink v-if="!auth.isAuthenticated" :to="target('register')" class="inline-flex h-11 items-center rounded-xl px-4 text-sm font-semibold text-white no-underline ring-1 ring-inset ring-white/30 hover:bg-white/10">
          {{ page.cta.secondary }}
        </RouterLink>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
/**
 * „Pre organizátorov" — čo portál ponúka a ako začať. Odkazujú sem pätička,
 * hlavná navigácia, výzvy na prevzatie kanála a e-maily organizátorom
 * (`mail.common.for_organizers`, premenná kampaní `{odkaz_pre_organizatorov}`).
 */
import { computed } from 'vue'
import { useHead } from '@vueuse/head'
import type { RouteLocationRaw } from 'vue-router'
import AppIcon from '@/components/AppIcon.vue'
import { FOR_ORGANIZERS_PATH, organizersPage, type OrganizersAction } from '@/content/organizers'
import { useI18n } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import { PUBLIC_EVENTS, absoluteUrl } from '@/utils/publicUrl'

const { locale } = useI18n()
const auth = useAuthStore()

// `locale` v závislosti: po prepnutí jazyka sa obsah prekreslí.
const page = computed(() => organizersPage(locale.value))

/**
 * Prihlásený organizátor účet nepotrebuje — „Založiť účet" ho pošle rovno
 * pridať akciu do prehľadu.
 */
function target(action: OrganizersAction): RouteLocationRaw {
  switch (action) {
    case 'poster': return '/nahrat-plagat'
    case 'events': return PUBLIC_EVENTS
    case 'register': return auth.isAuthenticated ? '/dashboard/events/create' : '/register'
  }
}

useHead(computed(() => ({
  title: `${page.value.metaTitle} | Tixio`,
  link: [{ rel: 'canonical', href: absoluteUrl(FOR_ORGANIZERS_PATH) }],
  meta: [
    { name: 'description', content: page.value.metaDescription },
    { property: 'og:type', content: 'website' },
    { property: 'og:title', content: page.value.metaTitle },
    { property: 'og:description', content: page.value.metaDescription },
    { property: 'og:url', content: absoluteUrl(FOR_ORGANIZERS_PATH) },
  ],
})))
</script>
