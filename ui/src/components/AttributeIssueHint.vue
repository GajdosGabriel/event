<template>
  <p v-if="issue" class="mt-1 flex items-start gap-1.5 text-sm text-amber-700">
    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
    </svg>
    <span>
      {{ text }}
      <span v-if="checkedLabel" class="text-amber-600/80">({{ checkedLabel }})</span>
    </span>
  </p>
</template>

<script setup lang="ts">
/**
 * Upozornenie pod poľom formulára: túto hodnotu sme skúšali a neodpovedala.
 *
 * Zámerne oranžové, nie červené. Červená v tomto formulári znamená „takto to
 * neuložíš" — a to je nepravda: adresa sa uloží vždy, overuje sa až potom.
 * Toto je informácia, nie prekážka; pole ostáva platné.
 */
import { computed } from 'vue'
import type { AttributeIssue } from '@/types'

const props = defineProps<{
  issue?: AttributeIssue | null
  /** Popis údaja do vety („Táto adresa…"). */
  label?: string
}>()

/**
 * Dôvody v jazyku majiteľa. Kľúče sú tie isté, aké posiela sonda na serveri
 * (App\Services\Attributes) — a tie isté, aké používa e-mail s upozornením,
 * nech si človek prečíta dvakrát to isté a nie dve rôzne verzie.
 */
const REASONS: Record<string, string> = {
  dns: 'Doména sa nenašla — skontrolujte, či v adrese nie je preklep.',
  not_found: 'Stránka na tejto adrese už neexistuje.',
  server_error: 'Server na tejto adrese hlási chybu.',
  http_error: 'Server na tejto adrese odpovedal chybou.',
  timeout: 'Server na tejto adrese neodpovedal včas.',
  ssl: 'Zabezpečené spojenie zlyhalo — pravdepodobne neplatný certifikát.',
  unreachable: 'Na tejto adrese sa nepodarilo spojiť so serverom.',
  redirect: 'Adresa presmerúva na miesto, ktoré sa nedá otvoriť.',
  redirect_loop: 'Adresa sa presmerúva dokola.',
  blocked: 'Túto adresu nevieme overiť.',
  invalid: 'Adresa nemá platný tvar.',
}

const text = computed(() => {
  const reason = REASONS[props.issue?.reason ?? ''] ?? 'Túto hodnotu sa nepodarilo overiť.'
  const status = props.issue?.httpStatus ? ` (${props.issue.httpStatus})` : ''

  return `${props.label ?? 'Táto hodnota'} nám neodpovedá. ${reason}${status}`
})

const checkedLabel = computed(() => {
  if (!props.issue?.checkedAt) return ''

  return `naposledy overené ${new Date(props.issue.checkedAt).toLocaleDateString('sk-SK')}`
})
</script>
