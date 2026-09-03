<template>
  <!--
    Jedno menu akcií pre výpis aj detail kanála, miesta a podujatia. Položky
    riadi výhradne `permissions` z backendu, takže obe miesta ponúkajú presne
    to isté a v menu nestojí nič, čo by policy odmietla.

    Neuskutočniteľná akcia sa neponúka ani zošednutá. Zošednuté „Zmazať"
    s tooltipom vysvetľovalo dôvod, ale zároveň vyzeralo ako porucha —
    a na dotyku, kde tooltip neexistuje, nevysvetlilo nič. Dôvod ostáva tam,
    kde ho používateľ hľadá: vo formulári pri voľbe stavu (zamknutá voľba
    „Koncept" s hintom) a v hláške z API, keď akciu spustí inou cestou.
  -->
  <RowActions v-if="hasAnyAction">
    <RouterLink v-if="canView" :to="base" class="row-menu-item">{{ t('common.view') }}</RouterLink>
    <RouterLink v-if="canUpdate" :to="`${base}/edit`" class="row-menu-item">{{ t('common.edit') }}</RouterLink>
    <button
      v-else-if="canDuplicate"
      class="row-menu-item"
      @click="duplicate"
    >{{ t('common.copy') }}</button>
    <button
      v-if="canPublish || canUnpublish"
      class="row-menu-item"
      @click="togglePublish"
    >{{ canUnpublish ? t('common.unpublish') : t('common.publish') }}</button>
    <!-- Archivované podujatie sa inak nedá ani upraviť, ani zmazať; toto je
         jediná cesta späť, keď ho archivoval preklep v termíne. Ponúka sa len
         tomu, na kom nevisia vydané lístky — rozhoduje backend. -->
    <button
      v-if="canUnarchive"
      class="row-menu-item"
      @click="unarchive"
    >{{ t('common.unarchive') }}</button>
    <button
      v-if="canDelete"
      class="row-menu-item row-menu-item-danger"
      @click="remove"
    >{{ t('common.remove') }}</button>
    <button
      v-if="canRestore"
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

const perms = computed<ModelPermissions | undefined>(() => props.item.permissions)

// Každá položka menu = jedno právo z policy. Stav záznamu (publikované,
// archivované, v koši) sa tu nedopočítava — to už spravil backend, keď právo
// počítal, a dve nezávislé úvahy o tom istom sa vždy rozídu.
const canView = computed(() => props.showView && Boolean(perms.value?.view))
const canUpdate = computed(() => Boolean(perms.value?.update))
const canDuplicate = computed(() => props.resource === 'event' && Boolean(perms.value?.duplicate))
const canPublish = computed(() => props.showPublish && Boolean(perms.value?.publish))
const canUnpublish = computed(() => props.showPublish && Boolean(perms.value?.unpublish))
const canUnarchive = computed(() => Boolean(perms.value?.unarchive))
const canDelete = computed(() => Boolean(perms.value?.delete))
const canRestore = computed(() => Boolean(perms.value?.restore))

/** Bez jedinej položky by ostalo visieť prázdne menu s troma bodkami. */
const hasAnyAction = computed(() => canView.value
  || canUpdate.value
  || canDuplicate.value
  || canPublish.value
  || canUnpublish.value
  || canUnarchive.value
  || canDelete.value
  || canRestore.value)

async function togglePublish() {
  // Smer určuje právo, nie published_at — publikované podujatie má `unpublish`,
  // koncept `publish`. Endpoint je ten istý, líši sa len príznakom.
  const publishing = !canUnpublish.value
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
