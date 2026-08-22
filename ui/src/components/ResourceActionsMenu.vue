<template>
  <!--
    Jedno menu akcií pre výpis aj detail kanála, miesta a podujatia. Položky
    riadi výhradne `permissions` z backendu, takže obe miesta ponúkajú presne
    to isté a nikde sa nedá kliknúť na akciu, ktorú by policy odmietla.
  -->
  <RowActions>
    <RouterLink v-if="showView" :to="base" class="row-menu-item">{{ t('common.view') }}</RouterLink>
    <RouterLink v-if="item.permissions?.update" :to="`${base}/edit`" class="row-menu-item">{{ t('common.edit') }}</RouterLink>
    <button
      v-else-if="resource === 'event' && item.permissions?.duplicate"
      class="row-menu-item"
      @click="duplicate"
    >{{ t('common.copy') }}</button>
    <button
      v-if="showPublish && (item.permissions?.publish || item.permissions?.unpublish)"
      class="row-menu-item"
      @click="togglePublish"
    >{{ item.permissions?.unpublish ? t('common.unpublish') : t('common.publish') }}</button>
    <!-- Archivované podujatie sa inak nedá ani upraviť, ani zmazať; toto je
         jediná cesta späť, keď ho archivoval preklep v termíne. Ponúka sa len
         tomu, na kom nevisia vydané lístky — rozhoduje backend. -->
    <button
      v-if="item.permissions?.unarchive"
      class="row-menu-item"
      @click="unarchive"
    >{{ t('common.unarchive') }}</button>
    <!-- Zablokované mazanie sa neskrýva, ale zošedne a povie prečo —
         tichý zmiznutý button je horší než vysvetlenie. -->
    <button
      v-if="(item.permissions?.delete || item.deleteBlockedReason) && !item.deletedAt"
      class="row-menu-item row-menu-item-danger"
      :disabled="Boolean(item.deleteBlockedReason)"
      :title="item.deleteBlockedReason ?? undefined"
      @click="remove"
    >{{ t('common.remove') }}</button>
    <button
      v-if="item.permissions?.restore && item.deletedAt"
      class="row-menu-item"
      @click="restore"
    >{{ t('common.restore') }}</button>
  </RowActions>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import http from '@/api/index'
import RowActions from '@/components/RowActions.vue'
import { useToast } from '@/composables/useToast'
import { isCancelled, publishRequest, serverMessage } from '@/utils/publishFlow'
import { useI18n } from '@/i18n'
import type { ModelPermissions } from '@/types'

/** Toľko zo záznamu, koľko menu potrebuje — výpis aj detail to majú. */
interface ActionsMenuItem {
  id: number
  permissions?: ModelPermissions
  deletedAt?: string | null
  /** Prečo sa záznam nedá zmazať — počíta backend zo vzťahov, nie zo stavu. */
  deleteBlockedReason?: string | null
}

const props = withDefaults(defineProps<{
  resource: 'canal' | 'venue' | 'event'
  scope: 'dashboard' | 'admin'
  item: ActionsMenuItem
  /** Detail sám na seba neodkazuje — „Zobraziť" má zmysel len vo výpise. */
  showView?: boolean
  /** Detail podujatia publikuje cez formulár, nie z menu. */
  showPublish?: boolean
}>(), { showView: true, showPublish: true })

/**
 * `changed` = záznam ostal, len sa zmenil (publikovanie, obnova).
 * `removed` = zmizol; výpis sa prekreslí, detail sa nemá kam vrátiť.
 */
const emit = defineEmits<{ changed: []; removed: [] }>()

const router = useRouter()
const toast = useToast()
const { t } = useI18n()

const API_SLUGS = { canal: 'canals', venue: 'venues', event: 'events' } as const

/** Cesta v routeri aj v API je tá istá — `/dashboard/canals/5`. */
const base = computed(() => `/${props.scope}/${API_SLUGS[props.resource]}/${props.item.id}`)

async function togglePublish() {
  // Smer určuje právo, nie published_at — publikované podujatie má `unpublish`,
  // koncept `publish`. Endpoint je ten istý, líši sa len príznakom.
  const publishing = !props.item.permissions?.unpublish
  try {
    await publishRequest(`${base.value}/publish`, publishing)
    toast.success(publishing ? t('common.published') : t('common.unpublished'))
    emit('changed')
  } catch (e) {
    if (!isCancelled(e)) toast.error(serverMessage(e) ?? t('common.actionFailed'))
  }
}

async function remove() {
  if (!confirm(t('common.removeConfirm'))) return
  try {
    await http.delete(base.value)
    toast.success(t('common.removed'))
    emit('removed')
  } catch (e) {
    // Backend vysvetľuje, prečo mazanie neprešlo (miesto používa podujatie,
    // kanál má členov…). Generický fallback ostáva len pre výpadok siete.
    toast.error(serverMessage(e) ?? t('common.removeFailed'))
  }
}

async function unarchive() {
  // Potvrdenie je namieste — záznam tým zmizne z verejného výpisu, a to je pri
  // minuloročnej akcii s odkazmi zvonku viditeľná zmena.
  if (!confirm(t('common.unarchiveConfirm'))) return
  try {
    await http.post(`${base.value}/unarchive`)
    toast.success(t('common.unarchived'))
    emit('changed')
  } catch (e) {
    toast.error(serverMessage(e) ?? t('common.unarchiveFailed'))
  }
}

async function restore() {
  try {
    await http.post(`${base.value}/restore`)
    toast.success(t('common.restored'))
    emit('changed')
  } catch { toast.error(t('common.restoreFailed')) }
}

async function duplicate() {
  try {
    const { data } = await http.post(`${base.value}/duplicate`)
    const newId = (data.data ?? data).id
    toast.success(t('events.copy.created'))
    router.push(`/${props.scope}/events/${newId}/edit`)
  } catch { toast.error(t('events.copy.failed')) }
}
</script>
