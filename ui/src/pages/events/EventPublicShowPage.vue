<template>
  <div class="pb-20 lg:pb-0">
    <!-- Načítavanie: kostra v tvare výslednej stránky. Spinner na prázdnej ploche
         pôsobil pomalšie, než stránka v skutočnosti je, a po dobehnutí skákal obsah. -->
    <div v-if="loading" class="animate-pulse">
      <div class="h-72 w-full bg-slate-200 md:h-96" />
      <div class="mx-auto w-full max-w-300 px-4 py-8">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_340px]">
          <div class="space-y-6">
            <div class="h-56 rounded-2xl bg-slate-200" />
            <div class="h-40 rounded-2xl bg-slate-200" />
          </div>
          <div class="space-y-4">
            <div class="h-36 rounded-2xl bg-slate-200" />
            <div class="h-28 rounded-2xl bg-slate-200" />
          </div>
        </div>
      </div>
      <span class="sr-only">Načítavam podujatie…</span>
    </div>

    <div v-else-if="error" class="mx-auto w-full max-w-300 px-4 py-16">
      <div class="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center">
        <p class="mb-1 text-lg font-semibold text-slate-900">
          {{ notFound ? 'Podujatie sa nenašlo' : 'Podujatie sa nepodarilo načítať' }}
        </p>
        <p class="mb-5 text-sm text-slate-500">
          {{ notFound
            ? 'Odkaz je zrejme neplatný alebo bolo podujatie stiahnuté.'
            : 'Skús to o chvíľu znova — spojenie so serverom zlyhalo.' }}
        </p>
        <div class="flex flex-wrap justify-center gap-2">
          <button
            v-if="!notFound"
            type="button"
            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            @click="load"
          >Skúsiť znova</button>
          <RouterLink
            :to="PUBLIC_EVENTS"
            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 no-underline hover:bg-slate-50"
          >Všetky podujatia</RouterLink>
        </div>
      </div>
    </div>

    <template v-else-if="event">
      <!-- Hero -->
      <div v-if="heroImage" class="relative h-72 w-full overflow-hidden md:h-96">
        <img
          :src="heroImage"
          :srcset="heroSrcset"
          sizes="100vw"
          :alt="event.name"
          fetchpriority="high"
          decoding="async"
          class="h-full w-full object-cover"
        />
        <!-- Dvojitý prechod: samotné 60 % čiernej nestačilo na svetlých plagátoch
             a biely nadpis sa na nich strácal. -->
        <div class="absolute inset-0 bg-linear-to-t from-black/85 via-black/40 to-transparent" />
        <div class="absolute inset-x-0 bottom-0 px-4 pb-6 text-white sm:px-6">
          <div class="mx-auto max-w-300">
            <div class="mb-2 flex flex-wrap items-center gap-2">
              <span
                v-if="event.dateRangeLabel"
                class="inline-flex items-center gap-1 rounded-md bg-white/15 px-2 py-0.5 text-xs font-medium backdrop-blur-sm"
              >
                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
                {{ event.dateRangeLabel }}
              </span>
              <span
                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold"
                :class="event.priceAmount ? 'bg-white/15 backdrop-blur-sm' : 'bg-green-500/90'"
              >{{ priceLabel }}</span>
            </div>
            <h1 class="text-3xl font-bold leading-tight drop-shadow-sm md:text-4xl">{{ event.name }}</h1>
            <p v-if="placeLabel" class="mt-1 text-sm text-white/80">{{ placeLabel }}</p>
          </div>
        </div>
      </div>

      <div class="mx-auto w-full max-w-300 px-4 py-6">
        <BreadcrumbNav :items="breadcrumbs" class="mb-5" />

        <!-- Bez fotky nesie nadpis vlastný blok; prázdny biely priestor navrchu
             pôsobil, akoby sa stránka nedonačítala. -->
        <div v-if="!heroImage" class="mb-6 rounded-2xl bg-linear-to-br from-slate-800 to-slate-600 px-6 py-8 text-white">
          <div class="mb-2 flex flex-wrap items-center gap-2">
            <span
              v-if="event.dateRangeLabel"
              class="inline-flex items-center rounded-md bg-white/15 px-2 py-0.5 text-xs font-medium"
            >{{ event.dateRangeLabel }}</span>
            <span
              class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold"
              :class="event.priceAmount ? 'bg-white/15' : 'bg-green-500/90'"
            >{{ priceLabel }}</span>
          </div>
          <h1 class="text-3xl font-bold leading-tight md:text-4xl">{{ event.name }}</h1>
          <p v-if="placeLabel" class="mt-1 text-sm text-white/80">{{ placeLabel }}</p>
        </div>

        <!-- Štítky vedú do tematických výpisov — pre návštevníka je to cesta
             „chcem ešte niečo podobné", pre vyhľadávač interné prelinkovanie. -->
        <div v-if="event.tags.length" class="mb-5 flex flex-wrap gap-1.5">
          <RouterLink
            v-for="tag in event.tags"
            :key="tag.id"
            :to="publicTagPath(tag.slug)"
            class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-700 no-underline ring-1 ring-inset ring-violet-200 transition-colors hover:bg-violet-100"
          >
            <span v-if="tag.emoji">{{ tag.emoji }}</span>
            {{ tag.name }}
          </RouterLink>
        </div>

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[1fr_340px]">
          <!-- Hlavný stĺpec -->
          <div class="space-y-6">
            <!-- Popis -->
            <div v-if="event.body" class="rounded-2xl border border-slate-200 bg-white p-6">
              <div class="prose prose-slate max-w-none leading-relaxed text-slate-700" v-html="event.body" />
            </div>

            <!-- Workshopy (sub-akcie v rámci eventu) -->
            <section v-if="workshops.length" class="rounded-2xl border border-slate-200 bg-white p-6">
              <div class="mb-4 flex items-center gap-2">
                <svg class="h-4 w-4 text-violet-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                <h2 class="text-base font-semibold text-slate-800">Workshopy</h2>
              </div>
              <p class="mb-3 text-sm text-slate-500">
                Sprievodné workshopy v rámci podujatia. Prihlásiť sa na ne môžu účastníci registrovaní na podujatie.
              </p>
              <p v-if="workshopError" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ workshopError }}</p>
              <EventWorkshops
                :workshops="workshops"
                joinable
                :authenticated="auth.isAuthenticated"
                :viewer-registered="viewerRegistered"
                :standalone="standaloneWorkshops"
                :locked="workshopChangesLocked"
                :busy-id="workshopBusyId"
                @join="onJoinWorkshop"
                @leave="onLeaveWorkshop"
              />
            </section>

            <!-- Galéria -->
            <section v-if="event.uploadedImages.length" class="rounded-2xl border border-slate-200 bg-white p-6">
              <h2 class="mb-4 text-base font-semibold text-slate-800">Fotografie</h2>
              <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                <!-- Button, nie div: lightbox sa musí dať otvoriť aj klávesnicou. -->
                <button
                  v-for="(img, idx) in event.uploadedImages"
                  :key="idx"
                  type="button"
                  :aria-label="`Zobraziť fotografiu ${idx + 1} z ${event.uploadedImages.length}`"
                  class="group relative aspect-square cursor-zoom-in overflow-hidden rounded-xl bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                  @click="lightboxIdx = idx"
                >
                  <img
                    :src="img.thumb || img.large"
                    :alt="`${event.name} — fotografia ${idx + 1}`"
                    loading="lazy" decoding="async"
                    class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                  />
                </button>
              </div>
            </section>

            <!-- Mapa -->
            <section v-if="mapCoords" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
              <div class="flex items-center gap-2 px-6 pb-3 pt-5">
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 9a2 2 0 110-4 2 2 0 010 4z"/>
                </svg>
                <h2 class="text-base font-semibold text-slate-800">Mapa</h2>
              </div>
              <!-- `lazy`: mapa je pod zlomom a iframe z cudzej domény inak
                   predlžuje načítanie stránky aj tým, kto na ňu nikdy nedoscrolluje. -->
              <iframe
                :src="mapUrl" width="100%" height="320" loading="lazy"
                frameborder="0" scrolling="no" class="block" title="Mapa miesta konania"
              />
              <div class="flex flex-wrap gap-3 px-6 py-2 text-xs">
                <a
                  :href="`https://www.google.com/maps?q=${mapCoords.lat},${mapCoords.lng}`"
                  target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline"
                >Otvoriť v Google Maps ↗</a>
                <a
                  :href="`https://www.google.com/maps/dir/?api=1&destination=${mapCoords.lat},${mapCoords.lng}`"
                  target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline"
                >Navigovať ↗</a>
              </div>
            </section>
          </div>

          <!-- Sidebar. `sticky` drží termín a registráciu na očiach aj pri dlhom
               popise — na mobile ju zastupuje spodná lišta. -->
          <aside class="space-y-4 lg:sticky lg:top-4">
            <!-- Termín -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
              <h2 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <rect x="3" y="4" width="18" height="18" rx="2"/><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18"/>
                </svg>
                Termín
              </h2>
              <EventDateRange :start-at="event.startAt" :end-at="event.endAt" />
              <a v-if="event.startAt" :href="eventCalendarUrl(event.id)" download
                class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 no-underline hover:underline">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <rect x="3" y="4" width="18" height="18" rx="2"/><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18M12 14v4m0 0l-2-2m2 2l2-2"/>
                </svg>
                Pridať do kalendára
              </a>
              <div v-if="event.registrationDeadlineAt" class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                Registrácia do: <strong>{{ fmtDateLong(event.registrationDeadlineAt) }}</strong>
                <span v-if="deadlineCountdown" class="mt-0.5 block font-semibold">{{ deadlineCountdown }}</span>
              </div>
            </section>

            <!-- Lístok / registrácia -->
            <section v-if="event.ticketsEnabled" id="registracia" class="scroll-mt-4 rounded-2xl border border-slate-200 bg-white p-5">
              <h2 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M5 5h14a2 2 0 012 2v3a2 2 0 000 4v3a2 2 0 01-2 2H5a2 2 0 01-2-2v-3a2 2 0 000-4V7a2 2 0 012-2z"/>
                </svg>
                Registrácia
              </h2>
              <TicketRequestForm
                :event-id="event.id"
                :types="ticketTypes"
                :registration-deadline-at="event.registrationDeadlineAt"
                :end-at="event.endAt"
                :viewer-registered="viewerRegistered"
                @changed="onRegistrationChanged"
              />
            </section>

            <!-- Miesto -->
            <section v-if="event.venue || event.locationName || event.street || event.municipality"
              class="rounded-2xl border border-slate-200 bg-white p-5">
              <h2 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 9a2 2 0 110-4 2 2 0 010 4z"/>
                </svg>
                Miesto
              </h2>
              <RouterLink v-if="event.venue?.id" :to="publicVenuePath({ id: event.venue.id })"
                class="font-semibold text-slate-900 no-underline hover:text-blue-600">{{ event.venue.name }}</RouterLink>
              <p v-else-if="event.locationName" class="font-semibold text-slate-900">{{ event.locationName }}</p>
              <p v-if="event.venue?.street || event.venue?.postcode" class="mt-0.5 text-sm text-slate-500">
                <span v-if="event.venue?.street">{{ event.venue.street }}, </span>
                <span v-if="event.venue?.postcode">{{ event.venue.postcode }}</span>
              </p>
              <p v-else-if="event.street" class="mt-0.5 text-sm text-slate-500">
                {{ event.street }}<span v-if="event.postcode">, {{ event.postcode }}</span>
              </p>
              <p v-if="event.municipality" class="mt-0.5 text-sm text-slate-500">
                {{ event.municipality.fullname ?? event.municipality.name }}
              </p>
              <div class="mt-1 flex flex-wrap gap-2 text-sm">
                <a v-if="event.venue?.phone" :href="`tel:${event.venue.phone}`" class="text-blue-600">{{ event.venue.phone }}</a>
                <ExternalLink v-if="event.venue?.website" :href="event.venue.website" target="venue"
                  :target-id="event.venue.id" class="text-blue-600 hover:underline">web ↗</ExternalLink>
              </div>
              <template v-if="venueOpeningHours.length">
                <div class="mt-3 border-t border-slate-100 pt-3">
                  <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Otváracie hodiny</p>
                  <dl class="grid grid-cols-2 gap-x-6 gap-y-0.5 text-sm">
                    <template v-for="row in venueOpeningHours" :key="row.day">
                      <dt class="font-medium text-slate-600">{{ row.day }}</dt>
                      <dd class="text-slate-900">{{ row.hours }}</dd>
                    </template>
                  </dl>
                </div>
              </template>
            </section>

            <!-- Organizátor -->
            <section v-if="event.canal" class="rounded-2xl border border-slate-200 bg-white p-5">
              <h2 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                </svg>
                Organizátor
              </h2>
              <RouterLink :to="publicCanalPath({ id: event.canal.id })"
                class="font-semibold text-slate-900 no-underline hover:text-blue-600">{{ event.canal.name }}</RouterLink>
              <ExternalLink v-if="event.canal.website" :href="event.canal.website" target="canal"
                :target-id="event.canal.id" class="ml-2 text-sm text-blue-600 hover:underline">web ↗</ExternalLink>
            </section>

            <!-- Kontakt -->
            <section v-if="event.phone || event.website || event.contactable" class="rounded-2xl border border-slate-200 bg-white p-5">
              <h2 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                Kontakt
              </h2>
              <div class="space-y-2 text-sm">
                <a v-if="event.phone" :href="`tel:${event.phone}`" class="flex items-center gap-2 text-slate-700 hover:text-blue-600">
                  {{ event.phone }}
                </a>
                <ExternalLink v-if="event.website" :href="event.website" target="event" :target-id="event.id"
                  class="flex items-center gap-2 truncate text-blue-600 hover:underline" />
              </div>
              <ContactButton v-if="event.contactable" target-type="event" :target-id="event.id" :target-name="event.name"
                :class="{ 'mt-3': event.phone || event.website }" />
            </section>

            <!-- Zdieľanie -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
              <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Zdieľať</h2>
              <ShareButtons :url="canonicalUrl" :title="event.name" :text="shareText" />
            </section>
          </aside>
        </div>

        <!-- Súvisiace podujatia. Bez nich končí detail slepou uličkou —
             návštevník, ktorému termín nevyhovuje, nemá kam pokračovať. -->
        <section v-if="relatedEvents.length" class="mt-10">
          <div class="mb-3 flex items-end justify-between gap-3">
            <h2 class="text-base font-semibold text-slate-800">
              Ďalšie podujatia{{ event.municipality ? ` v okolí (${event.municipality.name})` : '' }}
            </h2>
            <RouterLink :to="PUBLIC_EVENTS" class="shrink-0 text-sm text-blue-600 no-underline hover:underline">
              Všetky →
            </RouterLink>
          </div>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4">
            <EventCard
              v-for="item in relatedEvents"
              :key="item.id"
              :id="item.id"
              :name="item.name"
              :slug="item.slug"
              :image-url="item.imageUrl"
              :image-url-large="item.imageUrlLarge"
              :date-label="item.dateRangeLabel"
              :canal-name="item.canalName"
              :venue-name="item.venue?.name ?? null"
              :tags="item.tags"
            />
          </div>
        </section>
      </div>

      <!-- Mobilná lišta: registrácia bola na telefóne až pod popisom, workshopmi,
           galériou a mapou. Na `lg` ju nahrádza sticky sidebar. -->
      <div
        v-if="showMobileCta"
        class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur-sm lg:hidden"
      >
        <div class="mx-auto flex max-w-300 items-center gap-3">
          <div class="min-w-0 flex-1">
            <p class="truncate text-xs text-slate-500">{{ event.dateRangeLabel || 'Termín podľa programu' }}</p>
            <p class="truncate text-sm font-semibold text-slate-900">{{ priceLabel }}</p>
          </div>
          <button
            type="button"
            class="shrink-0 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
            @click="scrollToRegistration"
          >Registrovať sa</button>
        </div>
      </div>
    </template>

    <!-- Lightbox -->
    <ImageLightbox v-model:index="lightboxIdx" :images="lightboxImages" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@vueuse/head'
import { showPublicEvent, eventCalendarUrl, indexEvents } from '@/api/events'
import { publicTicketTypes, joinWorkshop, leaveWorkshop } from '@/api/ticketTypes'
import { useAuthStore } from '@/stores/auth'
import type { EventItem, TicketTypeItem } from '@/types'
import ImageLightbox from '@/components/ImageLightbox.vue'
import EventDateRange from '@/components/EventDateRange.vue'
import EventWorkshops from '@/components/EventWorkshops.vue'
import ContactButton from '@/components/ContactButton.vue'
import ExternalLink from '@/components/ExternalLink.vue'
import TicketRequestForm from '@/components/TicketRequestForm.vue'
import ShareButtons from '@/components/ShareButtons.vue'
import EventCard from '@/components/EventCard.vue'
import BreadcrumbNav, { type BreadcrumbItem } from '@/components/BreadcrumbNav.vue'
import { fmtDateLong, daysUntil } from '@/utils/dateFormat'
import { formatPriceOrFree } from '@/utils/money'
import {
  absoluteUrl,
  idFromRouteParam,
  publicEventPath,
  publicCanalPath,
  publicVenuePath,
  publicTagPath,
  PUBLIC_EVENTS,
} from '@/utils/publicUrl'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const event = ref<EventItem | null>(null)
const ticketTypes = ref<TicketTypeItem[]>([])
const relatedEvents = ref<EventItem[]>([])
const viewerRegistered = ref(false)
const workshopChangesLocked = ref(false)
const workshopBusyId = ref<number | null>(null)
const workshopError = ref<string | null>(null)
const loading = ref(false)
const error = ref(false)
const notFound = ref(false)
const lightboxIdx = ref<number | null>(null)

/** Koľko súvisiacich podujatí sa vojde do jedného radu mriežky. */
const RELATED_LIMIT = 4

// Ovládanie klávesnicou (Esc, šípky) aj priblíženie rieši ImageLightbox.
const lightboxImages = computed(() => (event.value?.uploadedImages ?? []).map(img => ({
  src: img.large || img.thumb,
  zoomSrc: img.original || undefined,
  alt: event.value?.name,
})))

const workshops = computed(() => ticketTypes.value.filter(t => t.kind === 'workshop'))

// Podujatie bez hlavného typu vstupenky (len workshopy) → workshop je samostatná
// registrácia a dá sa naň prihlásiť priamo, bez vstupenky na podujatie.
const standaloneWorkshops = computed(
  () => workshops.value.length > 0 && ticketTypes.value.every(t => t.kind === 'workshop'),
)

// Hero berie veľký variant (1280px); `imageUrl` je thumb 320px a na šírku
// obrazovky bol rozmazaný. Zostáva v srcset ako lacná voľba pre úzke displeje.
const heroImage = computed(() => event.value?.imageUrlLarge ?? event.value?.imageUrl ?? null)
const heroSrcset = computed(() => {
  const e = event.value
  if (!e?.imageUrl || !e.imageUrlLarge || e.imageUrl === e.imageUrlLarge) return undefined
  return `${e.imageUrl} 320w, ${e.imageUrlLarge} 1280w`
})

const priceLabel = computed(() => formatPriceOrFree(event.value?.priceAmount, event.value?.priceCurrency))

const placeLabel = computed(() => {
  const e = event.value
  if (!e) return ''
  const place = e.venue?.name ?? e.locationName ?? null
  const town = e.municipality?.name ?? null
  return [place, town].filter(Boolean).join(' · ')
})

const shareText = computed(() => {
  const e = event.value
  if (!e) return null
  return [e.dateRangeLabel, placeLabel.value].filter(Boolean).join(' · ') || null
})

const canonicalUrl = computed(() => (event.value ? absoluteUrl(publicEventPath(event.value)) : ''))

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
  const e = event.value
  if (!e) return []
  return [
    { label: 'Domov', to: '/' },
    { label: 'Podujatia', to: PUBLIC_EVENTS },
    { label: e.name },
  ]
})

/** „Zostávajú 3 dni" pri blížiacej sa uzávierke; ďaleký termín netlačí. */
const deadlineCountdown = computed(() => {
  const deadline = event.value?.registrationDeadlineAt
  if (!deadline) return null
  const days = daysUntil(deadline)
  if (days > 14) return null
  if (days === 0) return 'Dnes je posledný deň'
  if (days === 1) return 'Zostáva posledný deň'
  if (days < 5) return `Zostávajú ${days} dni`
  return `Zostáva ${days} dní`
})

// Lišta má zmysel len tam, kde sa dá niečo urobiť: registrácia je zapnutá
// a návštevník ešte prihlásený nie je.
const showMobileCta = computed(() => Boolean(event.value?.ticketsEnabled) && !viewerRegistered.value)

function scrollToRegistration() {
  document.getElementById('registracia')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

async function loadTicketTypes(eventId: number) {
  const result = await publicTicketTypes(eventId)
  ticketTypes.value = result.types
  viewerRegistered.value = result.viewerRegistered
  workshopChangesLocked.value = result.workshopChangesLocked
}

/**
 * Ďalšie podujatia v tej istej obci. Nepodstatné pre stránku samotnú, takže
 * chyba sa ticho prehltne — sekcia sa jednoducho nevykreslí.
 */
async function loadRelated(current: EventItem) {
  if (!current.municipalityId) return
  try {
    const { data } = await indexEvents('public', {
      municipality: current.municipalityId,
      list: 'upcoming',
      per_page: RELATED_LIMIT + 1,
    })
    relatedEvents.value = data.filter(e => e.id !== current.id).slice(0, RELATED_LIMIT)
  } catch { /* nepodstatné pre detail */ }
}

/** Po zmene znovu načítame typy — obnoví viewerJoined aj voľné kapacity. */
async function runWorkshopAction(workshop: TicketTypeItem, action: (eventId: number, typeId: number) => Promise<void>) {
  if (!event.value || !workshop.id) return
  workshopBusyId.value = workshop.id
  workshopError.value = null
  try {
    await action(event.value.id, workshop.id)
    // Paralelne — obe volania čítajú stav po tej istej zmene a nezávisia od seba.
    const [, refreshed] = await Promise.all([
      loadTicketTypes(event.value.id),
      showPublicEvent(String(event.value.id)),
    ])
    event.value = refreshed
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    workshopError.value = err.response?.data?.message ?? 'Akciu sa nepodarilo dokončiť.'
  } finally {
    workshopBusyId.value = null
  }
}

const onJoinWorkshop = (w: TicketTypeItem) => runWorkshopAction(w, joinWorkshop)
const onLeaveWorkshop = (w: TicketTypeItem) => runWorkshopAction(w, leaveWorkshop)

/** Po zmene registrácie (zrušenie) znovu načítame typy aj event — obnoví
 *  viewerRegistered aj voľné kapacity. */
async function onRegistrationChanged() {
  if (!event.value) return
  const [, refreshed] = await Promise.all([
    loadTicketTypes(event.value.id),
    showPublicEvent(String(event.value.id)),
  ])
  event.value = refreshed
}

const OH_DAYS: Record<string, string> = {
  monday: 'Pondelok', tuesday: 'Utorok', wednesday: 'Streda',
  thursday: 'Štvrtok', friday: 'Piatok', saturday: 'Sobota', sunday: 'Nedeľa',
}

const venueOpeningHours = computed(() => {
  const oh = event.value?.venue?.openingHours
  if (!oh || typeof oh !== 'object' || Array.isArray(oh)) return []
  return Object.entries(oh as Record<string, string | null>)
    .filter(([, hours]) => hours)
    .map(([day, hours]) => ({ day: OH_DAYS[day] ?? day, hours: hours as string }))
})

// Use event's own coords first, fall back to venue coords
const mapCoords = computed(() => {
  const ev = event.value
  if (!ev) return null
  if (ev.latitude && ev.longitude) return { lat: +ev.latitude, lng: +ev.longitude }
  const vLat = ev.venue?.latitude ? parseFloat(ev.venue.latitude) : null
  const vLng = ev.venue?.longitude ? parseFloat(ev.venue.longitude) : null
  if (vLat && vLng) return { lat: vLat, lng: vLng }
  return null
})

const mapUrl = computed(() => {
  if (!mapCoords.value) return ''
  const { lat, lng } = mapCoords.value
  const d = 0.008
  return `https://www.openstreetmap.org/export/embed.html?bbox=${lng - d},${lat - d},${lng + d},${lat + d}&layer=mapnik&marker=${lat},${lng}`
})

/** Popis bez HTML — do meta description aj do structured data. */
const plainDescription = computed(() => {
  const e = event.value
  if (!e) return ''
  if (e.body) return e.body.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').slice(0, 300).trim()
  return [e.dateRangeLabel, placeLabel.value].filter(Boolean).join(' · ')
})

/**
 * schema.org/Event — bez neho Google nevie, že ide o podujatie, a detail sa
 * nedostane medzi „Events" výsledky s dátumom a miestom. Vypĺňajú sa len polia,
 * ktoré naozaj máme; prázdne by validáciu zhodili.
 */
const eventJsonLd = computed(() => {
  const e = event.value
  if (!e || !e.startAt) return null

  const location = e.venue
    ? {
        '@type': 'Place',
        name: e.venue.name,
        address: {
          '@type': 'PostalAddress',
          ...(e.venue.street ? { streetAddress: e.venue.street } : {}),
          ...(e.venue.postcode ? { postalCode: e.venue.postcode } : {}),
          ...(e.municipality ? { addressLocality: e.municipality.name } : {}),
          addressCountry: e.country ?? 'SK',
        },
        ...(mapCoords.value
          ? { geo: { '@type': 'GeoCoordinates', latitude: mapCoords.value.lat, longitude: mapCoords.value.lng } }
          : {}),
      }
    : e.locationName || e.municipality
      ? {
          '@type': 'Place',
          name: e.locationName ?? e.municipality?.name ?? '',
          address: {
            '@type': 'PostalAddress',
            ...(e.street ? { streetAddress: e.street } : {}),
            ...(e.postcode ? { postalCode: e.postcode } : {}),
            ...(e.municipality ? { addressLocality: e.municipality.name } : {}),
            addressCountry: e.country ?? 'SK',
          },
        }
      : undefined

  return {
    '@context': 'https://schema.org',
    '@type': 'Event',
    name: e.name,
    startDate: e.startAt,
    ...(e.endAt ? { endDate: e.endAt } : {}),
    eventStatus: 'https://schema.org/EventScheduled',
    eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
    ...(plainDescription.value ? { description: plainDescription.value } : {}),
    ...(heroImage.value ? { image: [heroImage.value] } : {}),
    ...(location ? { location } : {}),
    ...(e.canal
      ? { organizer: { '@type': 'Organization', name: e.canal.name, ...(e.canal.website ? { url: e.canal.website } : {}) } }
      : {}),
    url: canonicalUrl.value,
    ...(e.ticketsEnabled
      ? {
          offers: {
            '@type': 'Offer',
            price: ((e.priceAmount ?? 0) / 100).toFixed(2),
            priceCurrency: e.priceCurrency ?? 'EUR',
            availability: 'https://schema.org/InStock',
            url: canonicalUrl.value,
            ...(e.publishedAt ? { validFrom: e.publishedAt } : {}),
          },
        }
      : {}),
  }
})

const breadcrumbJsonLd = computed(() => {
  if (!event.value) return null
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: breadcrumbs.value.map((item, idx) => ({
      '@type': 'ListItem',
      position: idx + 1,
      name: item.label,
      ...(item.to ? { item: absoluteUrl(item.to) } : {}),
    })),
  }
})

useHead(computed(() => {
  const e = event.value
  if (!e) return { title: 'Načítavam…' }
  const title = e.name
  const description = plainDescription.value.slice(0, 160) || title
  const image = e.imageUrlLarge ?? e.imageUrl ?? undefined
  // Kanonická adresa, nie `window.location.href` — ten nesie aj parametre
  // z kampaní (`?fbclid=…`) a zo starej číselnej cesty, čo by z jedného
  // podujatia urobilo v indexe niekoľko rôznych stránok.
  const url = canonicalUrl.value
  return {
    title: `${title} | Event`,
    link: [{ rel: 'canonical', href: url }],
    meta: [
      { name: 'description', content: description },
      { property: 'og:title', content: title },
      { property: 'og:description', content: description },
      { property: 'og:type', content: 'event' },
      { property: 'og:url', content: url },
      { property: 'og:locale', content: 'sk_SK' },
      ...(image ? [{ property: 'og:image', content: image }] : []),
      { name: 'twitter:card', content: image ? 'summary_large_image' : 'summary' },
      { name: 'twitter:title', content: title },
      { name: 'twitter:description', content: description },
      ...(image ? [{ name: 'twitter:image', content: image }] : []),
    ],
    script: [
      ...(eventJsonLd.value
        ? [{ key: 'event-jsonld', type: 'application/ld+json', innerHTML: JSON.stringify(eventJsonLd.value) }]
        : []),
      ...(breadcrumbJsonLd.value
        ? [{ key: 'breadcrumb-jsonld', type: 'application/ld+json', innerHTML: JSON.stringify(breadcrumbJsonLd.value) }]
        : []),
    ],
  }
}))

async function load() {
  loading.value = true
  error.value = false
  notFound.value = false
  try {
    const ev = await showPublicEvent(idFromRouteParam(route.params.slugId))

    // Adresa sa zosúladí s kanonickou podobou — na detail sa dá doraziť aj
    // zo starého číselného odkazu alebo so zastaraným slugom po premenovaní.
    // `replace`, nie `push`: v histórii nemá vzniknúť krok navyše.
    const canonicalPath = publicEventPath(ev)
    if (route.path !== canonicalPath) {
      router.replace(canonicalPath)
    }

    // Typy lístkov (vrátane workshopov) načítame tu — používa ich sekcia
    // workshopov aj registračný formulár, aby sa nerobili dva rovnaké requesty.
    try {
      await loadTicketTypes(ev.id)
    } catch { /* non-fatal — formulár ukáže prázdny stav */ }

    event.value = ev
    // Bez `await`: súvisiace podujatia sú doplnok, nemajú zdržiavať vykreslenie.
    void nextTick(() => loadRelated(ev))
  } catch (e: unknown) {
    // 404 chce inú správu než výpadok siete — pri neexistujúcom podujatí nemá
    // zmysel ponúkať „Skúsiť znova".
    const status = (e as { response?: { status?: number } }).response?.status
    notFound.value = status === 404 || status === 403
    error.value = true
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
