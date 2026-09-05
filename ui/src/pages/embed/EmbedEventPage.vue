<template>
  <div>
    <p v-if="loading" class="embed-note">{{ t('common.loading') }}</p>
    <p v-else-if="!event" class="embed-note">{{ t('embed.loadFailed') }}</p>

    <template v-else>
      <a
        :href="absoluteUrl(publicEventPath(event))"
        target="_blank"
        rel="noopener"
        class="block no-underline"
      >
        <img
          v-if="showImages && event.imageUrl"
          :src="event.imageUrl"
          :srcset="srcset"
          :alt="event.name"
          loading="lazy"
          decoding="async"
          class="mb-2 block h-32 w-full rounded-lg object-cover"
        />
        <h1 class="text-base font-semibold text-slate-900">{{ event.name }}</h1>
      </a>

      <p v-if="event.dateRangeLabel" class="mt-0.5 text-sm text-slate-600">{{ event.dateRangeLabel }}</p>
      <p v-if="venueLabel" class="text-sm text-slate-500">{{ venueLabel }}</p>

      <!-- Registrácia priamo vo widgete. Je to celý zmysel embedu: návštevník
           organizátorovho webu sa prihlási bez toho, aby ho museli posielať
           inam — a tým, kto registráciu spracuje, ostávame my. -->
      <div v-if="types.length" class="mt-3 border-t border-slate-100 pt-3">
        <TicketRequestForm
          :event-id="event.id"
          :types="types"
          :registration-deadline-at="event.registrationDeadlineAt"
          :end-at="event.endAt"
          :viewer-registered="viewerRegistered"
          @changed="loadTypes"
        />
      </div>

      <!-- Bez typov lístkov sa registrovať nedá — vtedy je jediná zmysluplná
           akcia odkaz na plný detail. -->
      <a
        v-else
        :href="absoluteUrl(publicEventPath(event))"
        target="_blank"
        rel="noopener"
        class="mt-3 inline-block rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-semibold text-white no-underline hover:bg-blue-700"
      >
        {{ t('embed.detail') }}
      </a>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { showPublicEvent } from '@/api/events'
import { publicTicketTypes } from '@/api/ticketTypes'
import type { EventItem, TicketTypeItem } from '@/types'
import { t } from '@/i18n'
import { absoluteUrl, idFromRouteParam, publicEventPath } from '@/utils/publicUrl'
import TicketRequestForm from '@/components/TicketRequestForm.vue'

const route = useRoute()
const eventId = Number(idFromRouteParam(route.params.slugId as string))

const showImages = computed(() => route.query.images !== '0')

const event = ref<EventItem | null>(null)
const types = ref<TicketTypeItem[]>([])
const viewerRegistered = ref(false)
const loading = ref(true)

const venueLabel = computed(() => event.value?.venue?.name ?? event.value?.locationName ?? null)

const srcset = computed(() => {
  const item = event.value
  if (!item?.imageUrl || !item.imageUrlLarge || item.imageUrlLarge === item.imageUrl) return undefined
  return `${item.imageUrl} 320w, ${item.imageUrlLarge} 1280w`
})

async function loadTypes() {
  try {
    const result = await publicTicketTypes(eventId)
    types.value = result.types
    viewerRegistered.value = result.viewerRegistered
  } catch {
    types.value = []
  }
}

async function load() {
  try {
    event.value = await showPublicEvent(eventId)
    await loadTypes()
  } catch {
    event.value = null
  } finally {
    loading.value = false
  }
}

load()
</script>
