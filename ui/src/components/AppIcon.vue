<template>
  <svg
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
  >
    <path v-for="(d, i) in paths" :key="i" :d="d" />
  </svg>
</template>

<script setup lang="ts">
/**
 * Jedna ikona z jedného zoznamu.
 *
 * Ikony boli dovtedy písané ako `<svg>` priamo v šablónach, takže tá istá
 * ikona žila v troch podobách na troch miestach a pri zmene sa menila len tam,
 * kam si niekto spomenul. Register nižšie je jediné miesto, kde sa kreslia —
 * `<AppIcon name="ticket" />` vyzerá odteraz všade rovnako a prekreslí sa
 * všade naraz.
 *
 * Veľkosť ani farbu komponent neurčuje: dedia sa z triedy volajúceho
 * (`class="h-4 w-4 text-slate-400"`) a `currentColor`, takže tá istá ikona
 * sedí v tmavom pruhu aj vo svetlej karte.
 *
 * Cesty sú z Heroicons (outline, 24×24, MIT) — kreslené jedným ťahom, takže
 * `stroke-width` funguje rovnako pri všetkých.
 */
import { computed } from 'vue'

const ICONS = {
  /** Lístky a ich nastavenia. */
  ticket: [
    'M15 5v2m0 4v2m0 4v2M5 5h14a2 2 0 012 2v3a2 2 0 000 4v3a2 2 0 01-2 2H5a2 2 0 01-2-2v-3a2 2 0 000-4V7a2 2 0 012-2z',
  ],
  /** Prihlásení / účastníci. */
  users: [
    'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
  ],
  /** Check-in pri vchode — odškrtnutie v krúžku. */
  checkin: [
    'M9 12l2 2 4-4',
    'M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  ],
  /** Otázky z publika. */
  question: [
    'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01',
    'M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  ],
  /** Nastavenia. */
  settings: [
    'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
    'M15 12a3 3 0 11-6 0 3 3 0 016 0z',
  ],
  /** Miesto konania. */
  mapPin: [
    'M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7z',
    'M12 11a2 2 0 110-4 2 2 0 010 4z',
  ],
  /** Rozbalenie sekcie. */
  chevronDown: [
    'M19 9l-7 7-7-7',
  ],
} as const

export type IconName = keyof typeof ICONS

const props = defineProps<{ name: IconName }>()

const paths = computed<readonly string[]>(() => ICONS[props.name] ?? [])
</script>
