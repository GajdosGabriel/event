<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <EventTicketsTabs :event-id="eventId" />

    <div class="mb-4">
      <h1 class="text-2xl font-semibold text-slate-900">
        {{ typeId !== null ? t('tickets.type.editTitle') : t('tickets.type.createTitle') }}
      </h1>
      <p v-if="eventName" class="text-sm text-slate-500">{{ eventName }}</p>
    </div>

    <TicketTypeForm
      :event-id="eventId"
      :type-id="typeId"
      @event-loaded="eventName = $event"
      @saved="backToList"
      @cancel="backToList"
    />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { t } from '@/i18n'
import EventTicketsTabs from '@/components/EventTicketsTabs.vue'
import TicketTypeForm from '@/components/TicketTypeForm.vue'

const route = useRoute()
const router = useRouter()

const eventId = Number(route.params.id)
const typeId = route.params.typeId ? Number(route.params.typeId) : null
const eventName = ref('')

function backToList() {
  router.push({ name: 'dashboard-events-tickets', params: { id: eventId } })
}
</script>
