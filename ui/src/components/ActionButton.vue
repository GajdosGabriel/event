<template>
  <component :is="tag" v-bind="bound" :class="classes">
    <AppIcon v-if="icon" :name="icon" class="h-4 w-4 shrink-0" />
    <slot>{{ label }}</slot>
    <!-- Šípka „otvorí sa inde" stojí za textom, nie pred ním: vodiaca ikona
         vľavo hovorí, kam odkaz vedie, táto len to, že odíde z aplikácie. -->
    <AppIcon v-if="external" name="externalLink" class="h-4 w-4 shrink-0" />
  </component>
</template>

<script setup lang="ts">
/**
 * Tlačidlo v pruhu nad záznamom — jedno pre celý dashboard.
 *
 * Ten istý pruh („Späť", „Lístky", „Check-in", „Otázky", „Verejná stránka",
 * karty sekcie lístkov) bol dovtedy poskladaný ručne na každej obrazovke: raz
 * `RouterLink.action-btn`, inde `a.action-btn.action-btn-feature`, inde
 * `RouterLink.nav-tab` — a keďže triedy sa opisovali zakaždým nanovo, tlačidlá
 * vedľa seba sa rozišli vzhľadom aj poradím ikony. Odteraz o tom rozhoduje
 * jediný komponent a volajúci povie len `variant`.
 *
 * Značka sa vyberá podľa toho, čo tlačidlo robí, nie podľa prepínača:
 * `to` → `RouterLink` (navigácia v aplikácii), `href` → `<a target="_blank">`
 * (odchod mimo aplikácie), inak `<button type="button">` (akcia na mieste).
 * Zablokovaný odkaz sa rovnako stane `<button disabled>` — `RouterLink`, na
 * ktorý sa dá kliknúť, by sľuboval navigáciu, ktorá sa nemá stať.
 */
import { computed, useAttrs } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon, { type IconName } from '@/components/AppIcon.vue'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  /** Cesta v aplikácii — vykreslí `RouterLink`. */
  to?: string
  /** Adresa mimo aplikácie — vykreslí `<a target="_blank">` so šípkou. */
  href?: string
  /** Ikona pred textom (register v `AppIcon`). */
  icon?: IconName
  /** Popis, keď sa nepoužije slot. */
  label?: string
  /**
   * `plain` — navigácia a bežné akcie (šedé).
   * `feature` — zapnutá funkcia podujatia (modré, väčšie písmo).
   * `tab` — položka prepínača kariet `.nav-tabs`.
   * `danger` — akcia, ktorá niečo ruší.
   */
  variant?: 'plain' | 'feature' | 'tab' | 'danger'
  /** Zvýraznená karta prepínača (platí pre `variant="tab"`). */
  active?: boolean
  disabled?: boolean
}>(), { variant: 'plain' })

const attrs = useAttrs()

// Zablokované tlačidlo nikam nevedie — inak by `RouterLink` navigoval napriek
// tomu, že vyzerá vypnuto (`pointer-events` sa na klávesnicu nevzťahujú).
const isLink = computed(() => !props.disabled && Boolean(props.to || props.href))
const external = computed(() => isLink.value && Boolean(props.href))

const tag = computed(() => {
  if (!isLink.value) return 'button'

  return props.href ? 'a' : RouterLink
})

const attrsFor = computed<Record<string, unknown>>(() => {
  if (!isLink.value) return { type: 'button', disabled: props.disabled || undefined }
  if (props.href) return { href: props.href, target: '_blank', rel: 'noopener' }

  return { to: props.to }
})

const bound = computed(() => ({ ...attrs, ...attrsFor.value }))

const classes = computed(() => {
  if (props.variant === 'tab') return ['nav-tab', { active: props.active, 'opacity-60': props.disabled }]

  return [
    'action-btn',
    {
      'action-btn-feature': props.variant === 'feature',
      'action-btn-danger': props.variant === 'danger',
      'opacity-60': props.disabled,
    },
  ]
})
</script>
