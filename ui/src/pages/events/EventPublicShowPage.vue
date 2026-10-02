<template>
  <div class="pb-20 lg:pb-0">
    <!-- Načítavanie: kostra v tvare výslednej stránky. Spinner na prázdnej ploche
         pôsobil pomalšie, než stránka v skutočnosti je, a po dobehnutí skákal obsah. -->
    <div v-if="loading" class="animate-pulse">
      <div class="mx-auto w-full max-w-300 sm:px-4 sm:pt-4">
        <div class="h-96 w-full bg-slate-300 sm:rounded-3xl md:h-[30rem]" />
      </div>
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
      <span class="sr-only">{{ t('public.event.loading') }}</span>
    </div>

    <div v-else-if="error" class="mx-auto w-full max-w-300 px-4 py-16">
      <div class="mx-auto max-w-md rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5 p-8 text-center">
        <p class="mb-1 text-lg font-semibold text-slate-900">
          {{ notFound ? t('public.event.notFoundTitle') : t('public.event.errorTitle') }}
        </p>
        <p class="mb-5 text-sm text-slate-500">
          {{ notFound ? t('public.event.notFoundLead') : t('public.event.errorLead') }}
        </p>
        <div class="flex flex-wrap justify-center gap-2">
          <button
            v-if="!notFound"
            type="button"
            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            @click="load"
          >{{ t('common.retry') }}</button>
          <RouterLink
            :to="PUBLIC_EVENTS"
            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 no-underline hover:bg-slate-50"
          >{{ t('public.list.allEvents') }}</RouterLink>
        </div>
      </div>
    </div>

    <template v-else-if="event">
      <!-- Hero. Plagát sa zobrazuje celý — orezaný na široký pás z neho
           ostával len pás textu uprostred. Pozadie je ten istý obrázok,
           rozmazaný: stránka tak preberie farby plagátu bez ďalšieho obsahu.
           Bez obrázka nesie hlavičku prechod, aby nevyzerala nedonačítane. -->
      <!-- Obal drží hlavičku v rovnakej šírke (max-w-300 + px-4) ako obsah pod ňou. -->
      <div class="mx-auto w-full max-w-300 sm:px-4 sm:pt-4">
      <header class="relative isolate overflow-hidden bg-slate-900 text-white sm:rounded-3xl">
        <img
          v-if="heroImage"
          :src="event.imageUrl ?? heroImage"
          alt=""
          aria-hidden="true"
          class="absolute inset-0 -z-10 h-full w-full scale-125 object-cover opacity-90 blur-3xl saturate-150"
        />
        <div v-else class="absolute inset-0 -z-10 bg-linear-to-br from-indigo-800 via-slate-900 to-rose-900" />
        <!-- Stmavenie — biely text musí byť čitateľný aj nad svetlým plagátom. -->
        <div class="absolute inset-0 -z-10 bg-linear-to-b from-slate-950/40 via-slate-950/55 to-slate-950/80" />

        <!-- Obrázok na šírku vypĺňa celú ľavú polovicu hlavičky až po okraje —
             v rámčeku s pozadím okolo pôsobil ako vložená známka. Plagát na
             výšku by sa tak musel orezať, ten ostáva celý v rámčeku. -->
        <div
          class="grid w-full items-center"
          :class="posterBleed
            ? 'md:grid-cols-2'
            : ['mx-auto max-w-300 gap-8 px-4 py-8 md:py-14', heroImage ? 'md:grid-cols-[minmax(0,460px)_1fr] lg:gap-14' : '']"
        >
          <!-- Plagát vždy vyplní stĺpec, aj keď je zdroj malý (import občas
               prinesie 190 px náhľad) — pri `w-auto` ostával ako známka.
               Orientáciu vieme až po načítaní. -->
          <div v-if="heroImage" :class="posterBleed ? 'relative self-stretch md:min-h-[26rem]' : 'contents'">
            <img
              :src="heroImage"
              :srcset="heroSrcset"
              :sizes="posterBleed ? '(min-width: 768px) 50vw, 100vw' : '(min-width: 768px) 460px, 100vw'"
              :alt="event.name"
              fetchpriority="high"
              decoding="async"
              :class="posterBleed
                ? 'block h-auto w-full md:absolute md:inset-0 md:h-full md:object-cover'
                : 'mx-auto h-[30rem] w-auto max-w-full rounded-2xl object-contain shadow-2xl ring-1 ring-white/15 md:h-[38rem]'"
              @load="onPosterLoad"
            />
          </div>

          <div class="min-w-0" :class="{ 'px-4 py-8 md:px-10 md:py-14 lg:px-14': posterBleed }">
            <!-- Štítky vedú do tematických výpisov — pre návštevníka je to cesta
                 „chcem ešte niečo podobné", pre vyhľadávač interné prelinkovanie. -->
            <div v-if="event.tags.length" class="mb-4 flex flex-wrap gap-1.5">
              <RouterLink
                v-for="tag in event.tags"
                :key="tag.id"
                :to="publicTagPath(tag.slug)"
                class="inline-flex items-center gap-1 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-white no-underline ring-1 ring-inset ring-white/20 backdrop-blur-sm transition-colors hover:bg-white/20"
              >
                <span v-if="tag.emoji">{{ tag.emoji }}</span>
                {{ tagLabel(tag) }}
              </RouterLink>
            </div>

            <h1 class="text-3xl leading-tight font-extrabold tracking-tight text-balance md:text-5xl">{{ event.name }}</h1>

            <dl class="mt-6 grid gap-4 text-sm sm:text-base">
              <div v-if="event.startAt || event.dateRangeLabel" class="flex items-center gap-3">
                <dt class="sr-only">{{ t('public.event.date') }}</dt>
                <!-- Kalendárny lístok — rovnaký tvar ako na kartách vo výpise. -->
                <div
                  v-if="heroTile"
                  class="flex w-12 shrink-0 flex-col items-center rounded-xl bg-white py-1 leading-none text-slate-900 shadow-lg"
                  aria-hidden="true"
                >
                  <span class="text-[10px] font-bold uppercase tracking-wide text-rose-600">{{ heroTile.month }}</span>
                  <span class="mt-0.5 text-lg font-extrabold">{{ heroTile.day }}</span>
                </div>
                <dd>
                  <span v-if="event.startAt" class="block font-semibold">{{ dayName(event.startAt) }}</span>
                  <span class="text-white/75">{{ event.dateRangeLabel }}</span>
                </dd>
              </div>

              <div v-if="placeLabel" class="flex items-center gap-3">
                <dt class="sr-only">{{ t('public.event.place') }}</dt>
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15" aria-hidden="true">
                  <AppIcon name="mapPin" class="h-5 w-5" />
                </span>
                <dd class="min-w-0 font-semibold">{{ placeLabel }}</dd>
              </div>

              <div class="flex items-center gap-3">
                <dt class="sr-only">{{ t('public.event.registration') }}</dt>
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/15" aria-hidden="true">
                  <AppIcon name="ticket" class="h-5 w-5" />
                </span>
                <dd>
                  <span
                    class="inline-flex items-center rounded-full px-3 py-1 text-sm font-bold"
                    :class="hasPaidPrice ? 'bg-white/15' : 'bg-emerald-500 text-white'"
                  >{{ priceLabel }}</span>
                </dd>
              </div>
            </dl>

            <!-- Hlavná akcia hneď pod faktami — na `lg` je formulár v bočnom
                 paneli, ale „kde sa prihlásim" je prvá otázka po prečítaní nadpisu. -->
            <div v-if="(showMobileCta && !hasEnded) || mapCoords" class="mt-7 flex flex-wrap gap-3">
              <button
                v-if="showMobileCta && !hasEnded"
                type="button"
                class="inline-flex h-12 items-center gap-2 rounded-xl bg-white px-6 text-base font-bold text-slate-900 shadow-lg transition hover:bg-slate-100"
                @click="scrollToRegistration"
              >
                <AppIcon name="ticket" class="h-5 w-5" />
                {{ registerLabel }}
              </button>
              <a
                v-if="mapCoords"
                :href="`https://www.google.com/maps/dir/?api=1&destination=${mapCoords.lat},${mapCoords.lng}`"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex h-12 items-center gap-2 rounded-xl bg-white/10 px-5 text-base font-semibold text-white no-underline ring-1 ring-white/25 backdrop-blur-sm transition hover:bg-white/20"
              >
                <AppIcon name="mapPin" class="h-5 w-5" />
                {{ t('public.event.navigate') }}
              </a>
            </div>
          </div>
        </div>
      </header>
      </div>

      <div class="mx-auto w-full max-w-300 px-4 py-6">
        <BreadcrumbNav :items="breadcrumbs" class="mb-5" />

        <!-- Skončené podujatie. Stránka ostáva na svojej adrese kvôli odkazom
             z vyhľadávača a zo zdieľaní, ale musí to o sebe povedať skôr, než
             sa niekto začne chystať. Odkaz vedie ďalej, nie do prázdna. -->
        <div
          v-if="hasEnded"
          class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4"
        >
          <p class="text-sm font-semibold text-amber-900">{{ t('public.event.ended') }}</p>
          <RouterLink
            :to="PUBLIC_EVENTS"
            class="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-sm font-semibold text-amber-900 no-underline transition-colors hover:bg-amber-100"
          >{{ t('public.event.endedCta') }}</RouterLink>
        </div>

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[1fr_340px]">
          <!-- Hlavný stĺpec -->
          <div class="space-y-6">
            <!-- Popis -->
            <div v-if="event.body" class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5 p-6 md:p-8">
              <div class="prose prose-slate max-w-none leading-relaxed text-slate-700" v-html="event.body" />
            </div>

            <!-- Workshopy (sub-akcie v rámci eventu) -->
            <section v-if="workshops.length" class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5 p-6">
              <div class="mb-4 flex items-center gap-2">
                <svg class="h-4 w-4 text-violet-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                <h2 class="text-lg font-bold text-slate-900">{{ t('public.event.workshops') }}</h2>
              </div>
              <p class="mb-3 text-sm text-slate-500">{{ t('public.event.workshopsLead') }}</p>
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

            <!-- Otázky a odpovede. Nástenka bola doteraz dostupná len cez QR
                 premietnutý v sále; zodpovedané otázky sú pritom presne to,
                 na čo sa ľudia pýtajú ešte doma — a čo googlia. -->
            <EventQuestions :event-id="event.id" />

            <!-- Galéria -->
            <section v-if="event.uploadedImages.length" class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5 p-6">
              <h2 class="mb-4 text-lg font-bold text-slate-900">{{ t('public.event.photos') }}</h2>
              <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                <!-- Button, nie div: lightbox sa musí dať otvoriť aj klávesnicou. -->
                <button
                  v-for="(img, idx) in event.uploadedImages"
                  :key="idx"
                  type="button"
                  :aria-label="t('public.event.photoOpen', { n: idx + 1, total: event.uploadedImages.length })"
                  class="group relative aspect-square cursor-zoom-in overflow-hidden rounded-xl bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                  @click="lightboxIdx = idx"
                >
                  <img
                    :src="img.thumb || img.large"
                    :alt="t('public.event.photoAlt', { name: event.name, n: idx + 1 })"
                    loading="lazy" decoding="async"
                    class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                  />
                </button>
              </div>
            </section>

            <!-- Mapa. Zabalená: v module Miesto je náhľad, ktorý na otázku
                 „kde to je" odpovie skôr, než sem človek doscrolluje. Kto chce
                 mapu naplno, rozbalí ju jedným klikom — a kto nie, nemá pod
                 textom tristo pixelov cudzieho iframu.

                 `<details>` zámerne namiesto vlastného stavu: funguje
                 klávesnicou aj bez JS, prehliadač sám rieši `aria-expanded`
                 a obsah zabalenej sekcie sa ani nenačítava. -->
            <details v-if="mapCoords" class="collapsible group overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5">
              <summary class="flex cursor-pointer items-center gap-2 px-6 py-4">
                <AppIcon name="mapPin" class="h-4 w-4 text-slate-400" />
                <h2 class="text-lg font-bold text-slate-900">{{ t('public.event.map') }}</h2>
                <AppIcon
                  name="chevronDown"
                  class="ml-auto h-4 w-4 text-slate-400 transition-transform group-open:rotate-180"
                />
              </summary>
              <!-- `lazy`: mapa je pod zlomom a iframe z cudzej domény inak
                   predlžuje načítanie stránky aj tým, kto na ňu nikdy nedoscrolluje. -->
              <iframe
                :src="mapUrl" width="100%" height="320" loading="lazy"
                frameborder="0" scrolling="no" class="block" :title="t('public.event.mapTitle')"
              />
              <div class="flex flex-wrap gap-3 px-6 py-2 text-xs">
                <a
                  :href="`https://www.google.com/maps?q=${mapCoords.lat},${mapCoords.lng}`"
                  target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline"
                >{{ t('public.event.openInMaps') }}</a>
                <a
                  :href="`https://www.google.com/maps/dir/?api=1&destination=${mapCoords.lat},${mapCoords.lng}`"
                  target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline"
                >{{ t('public.event.navigate') }}</a>
              </div>
            </details>
          </div>

          <!-- Sidebar. `sticky` drží termín a registráciu na očiach aj pri dlhom
               popise — na mobile ju zastupuje spodná lišta. -->
          <aside class="space-y-4 lg:sticky lg:top-4">
            <!-- Termín -->
            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5 p-5">
              <h2 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <rect x="3" y="4" width="18" height="18" rx="2"/><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18"/>
                </svg>
                {{ t('public.event.date') }}
              </h2>
              <EventDateRange :start-at="event.startAt" :end-at="event.endAt" />
              <AddToCalendarButton :links="event.calendarLinks" class="mt-3" />

              <!-- Ďalšie termíny série. Sedí pod dátumom, lebo je to odpoveď na
                   otázku, ktorú si človek kladie práve tu: „a keď v stredu
                   nemôžem?" Každý termín má vlastnú stránku aj vlastné lístky. -->
              <div v-if="event.seriesOccurrences.length" class="mt-4 border-t border-slate-100 pt-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                  {{ t('public.series.title') }}
                </p>
                <ul class="mt-2 grid gap-1">
                  <li v-for="occurrence in event.seriesOccurrences" :key="occurrence.id">
                    <RouterLink
                      :to="publicEventPath({ id: occurrence.id, slug: occurrence.slug })"
                      class="text-sm text-blue-700 hover:underline"
                    >
                      {{ occurrence.dateRangeLabel ?? occurrence.name }}
                    </RouterLink>
                  </li>
                </ul>
              </div>
              <!-- Hneď pod „Pridať do kalendára": tam človek hľadá, čo si
                   s termínom počať. Kalendár si pripomenie sám (VALARM v .ics),
                   toto navyše sľubuje ozvať sa pri zmene či zrušení. -->
              <RemindMeButton
                v-if="showRemindMe"
                :event-id="event.id"
                :event-name="event.name"
                variant="ghost"
                class="mt-2"
              />
              <div v-if="event.registrationDeadlineAt" class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                {{ t('public.event.deadline') }} <strong>{{ fmtDateLong(event.registrationDeadlineAt) }}</strong>
                <span v-if="deadlineCountdown" class="mt-0.5 block font-semibold">{{ deadlineCountdown }}</span>
              </div>
            </section>

            <!-- Lístok / registrácia -->
            <section v-if="event.reservable" id="registracia" class="scroll-mt-4 rounded-2xl bg-white shadow-md ring-2 ring-blue-500/30 p-5">
              <h2 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M5 5h14a2 2 0 012 2v3a2 2 0 000 4v3a2 2 0 01-2 2H5a2 2 0 01-2-2v-3a2 2 0 000-4V7a2 2 0 012-2z"/>
                </svg>
                {{ t('public.event.registration') }}
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
              class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5 p-5">
              <h2 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 9a2 2 0 110-4 2 2 0 010 4z"/>
                </svg>
                {{ t('public.event.place') }}
              </h2>
              <!-- Nové okno: návštevník si pozrie miesto a nepríde o rozčítané podujatie. -->
              <RouterLink v-if="event.venue?.id" :to="publicVenuePath({ id: event.venue.id })"
                target="_blank" rel="noopener"
                class="font-semibold text-slate-900 no-underline hover:text-blue-600">{{ event.venue.name }}</RouterLink>
              <p v-else-if="event.locationName" class="font-semibold text-slate-900">{{ event.locationName }}</p>
              <p v-if="event.venue?.street || event.venue?.postcode" class="mt-0.5 text-sm text-slate-500">
                <span v-if="event.venue?.street">{{ event.venue.street }}, </span>
                <span v-if="event.venue?.postcode">{{ event.venue.postcode }}</span>
              </p>
              <p v-else-if="event.street" class="mt-0.5 text-sm text-slate-500">
                {{ event.street }}<span v-if="event.postcode">, {{ event.postcode }}</span>
              </p>
              <p v-if="municipalityLabel" class="mt-0.5 text-sm text-slate-500">
                {{ municipalityLabel }}
              </p>
              <div class="mt-1 flex flex-wrap gap-2 text-sm">
                <a v-if="event.venue?.phone" :href="`tel:${event.venue.phone}`" class="text-blue-600">{{ event.venue.phone }}</a>
                <ExternalLink v-if="event.venue?.website" :href="event.venue.website" target="venue"
                  :target-id="event.venue.id" class="text-blue-600 hover:underline">{{ t('public.web') }}</ExternalLink>
              </div>
              <template v-if="venueOpeningHours.length">
                <div class="mt-3 border-t border-slate-100 pt-3">
                  <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('public.event.openingHours') }}</p>
                  <dl class="grid grid-cols-2 gap-x-6 gap-y-0.5 text-sm">
                    <template v-for="row in venueOpeningHours" :key="row.day">
                      <dt class="font-medium text-slate-600">{{ row.day }}</dt>
                      <dd class="text-slate-900">{{ row.hours }}</dd>
                    </template>
                  </dl>
                </div>
              </template>

              <!-- Náhľad mapy pri adrese: „kde to je" je otázka, ktorú si človek
                   kladie práve tu, nie o dve obrazovky nižšie. Bez súradníc sa
                   nezobrazí nič — prázdny rámček by bol horší než žiadny.

                   Mapa je zámerne **neinteraktívna** (`pointer-events-none`):
                   inak by iframe pohltil klik aj scroll a na telefóne by sa
                   stránka pod prstom prestala hýbať. Klik tak vždy spadne na
                   odkaz okolo a mapa sa otvorí naplno vo vedľajšej karte. -->
              <a
                v-if="mapCoords"
                :href="`https://www.google.com/maps?q=${mapCoords.lat},${mapCoords.lng}`"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-3 block overflow-hidden rounded-xl border border-slate-200 transition-colors hover:border-blue-400"
                :aria-label="t('public.event.openInMaps')"
              >
                <iframe
                  :src="mapUrl" width="100%" height="130" loading="lazy"
                  frameborder="0" scrolling="no" tabindex="-1" aria-hidden="true"
                  class="pointer-events-none block"
                />
              </a>
            </section>

            <!-- Organizátor -->
            <section v-if="event.canal" class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5 p-5">
              <h2 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                </svg>
                {{ t('public.event.organizer') }}
              </h2>
              <RouterLink :to="publicCanalPath({ id: event.canal.id })"
                target="_blank" rel="noopener"
                class="font-semibold text-slate-900 no-underline hover:text-blue-600">{{ event.canal.name }}</RouterLink>
              <ExternalLink v-if="event.canal.website" :href="event.canal.website" target="canal"
                :target-id="event.canal.id" class="ml-2 text-sm text-blue-600 hover:underline">{{ t('public.web') }}</ExternalLink>
            </section>

            <!-- Kontakt -->
            <section v-if="event.phone || event.website || event.contactable" class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5 p-5">
              <h2 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                {{ t('public.event.contact') }}
              </h2>
              <div class="space-y-2 text-sm">
                <a v-for="phone in [event.phone, ...event.additionalPhones].filter(Boolean)" :key="phone!" :href="`tel:${phone}`" class="flex items-center gap-2 text-slate-700 hover:text-blue-600">
                  {{ phone }}
                </a>
                <ExternalLink v-if="event.website" :href="event.website" target="event" :target-id="event.id"
                  class="flex items-center gap-2 truncate text-blue-600 hover:underline" />
              </div>
              <ContactButton v-if="event.contactable" target-type="event" :target-id="event.id" :target-name="event.name"
                :class="{ 'mt-3': event.phone || event.website }" />
            </section>

            <!-- Zdieľanie -->
            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5 p-5">
              <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ t('public.share.title') }}</h2>
              <ShareButtons :url="canonicalUrl" :title="event.name" :text="shareText" />
            </section>
          </aside>
        </div>

        <!-- Súvisiace podujatia. Bez nich končí detail slepou uličkou —
             návštevník, ktorému termín nevyhovuje, nemá kam pokračovať. -->
        <section v-if="relatedEvents.length" class="mt-14">
          <div class="mb-5 flex items-end justify-between gap-3">
            <h2 class="text-lg font-bold text-slate-900">
              {{ event.municipality
                ? t('public.event.relatedNear', { name: event.municipality.name })
                : t('public.event.related') }}
            </h2>
            <RouterLink :to="PUBLIC_EVENTS" class="shrink-0 text-sm text-blue-600 no-underline hover:underline">
              {{ t('public.event.relatedAll') }}
            </RouterLink>
          </div>
          <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 md:grid-cols-4">
            <EventCard
              v-for="item in relatedEvents"
              :key="item.id"
              :id="item.id"
              :name="item.name"
              :slug="item.slug"
              :image-url="item.imageUrl"
              :image-url-large="item.imageUrlLarge"
              :date-label="item.dateRangeLabel"
              :start-at="item.startAt"
              :canal-name="item.canalName"
              :venue-name="item.venue?.name ?? null"
              :tags="item.tags"
              :ticket-cta="item.ticketCta"
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
            <p class="truncate text-xs text-slate-500">{{ event.dateRangeLabel || t('public.event.dateFallback') }}</p>
            <p class="truncate text-sm font-semibold text-slate-900">{{ priceLabel }}</p>
          </div>
          <button
            type="button"
            class="shrink-0 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700"
            @click="scrollToRegistration"
          >{{ registerLabel }}</button>
        </div>
      </div>

      <!-- Tá istá lišta pre podujatia bez lístkov. Doteraz sa im skryla celá,
           takže na telefóne nemal návštevník k dispozícii vôbec nič. -->
      <div
        v-else-if="showMobileRemind"
        class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur-sm lg:hidden"
      >
        <div class="mx-auto flex max-w-300 items-center gap-3">
          <div class="min-w-0 flex-1">
            <p class="truncate text-xs text-slate-500">{{ event.dateRangeLabel || t('public.event.dateFallback') }}</p>
            <p class="truncate text-sm font-semibold text-slate-900">{{ priceLabel }}</p>
          </div>
          <RemindMeButton :event-id="event.id" :event-name="event.name" class="w-auto shrink-0" />
        </div>
      </div>
    </template>

    <!-- Lightbox -->
    <ImageLightbox v-model:index="lightboxIdx" :images="lightboxImages" />
  </div>
</template>

<script setup lang="ts">
import { tagLabel } from '@/utils/tagLabel'
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useHead } from '@vueuse/head'
import { showPublicEvent, indexEvents } from '@/api/events'
import { publicTicketTypes, joinWorkshop, leaveWorkshop } from '@/api/ticketTypes'
import { useAuthStore } from '@/stores/auth'
import type { EventItem, TicketTypeItem } from '@/types'
import AppIcon from '@/components/AppIcon.vue'
import ImageLightbox from '@/components/ImageLightbox.vue'
import EventDateRange from '@/components/EventDateRange.vue'
import AddToCalendarButton from '@/components/AddToCalendarButton.vue'
import EventWorkshops from '@/components/EventWorkshops.vue'
import ContactButton from '@/components/ContactButton.vue'
import RemindMeButton from '@/components/RemindMeButton.vue'
import EventQuestions from '@/components/EventQuestions.vue'
import ExternalLink from '@/components/ExternalLink.vue'
import TicketRequestForm from '@/components/TicketRequestForm.vue'
import ShareButtons from '@/components/ShareButtons.vue'
import EventCard from '@/components/EventCard.vue'
import BreadcrumbNav, { type BreadcrumbItem } from '@/components/BreadcrumbNav.vue'
import { fmtDateLong, daysUntil, weekdayLabel, dayName, dateTile } from '@/utils/dateFormat'
import { formatPrice, formatPriceOrFree } from '@/utils/money'
import {
  absoluteUrl,
  publicEventPath,
  publicCanalPath,
  publicVenuePath,
  publicTagPath,
  PUBLIC_EVENTS,
} from '@/utils/publicUrl'
import { useI18n, localeTag } from '@/i18n'

const { t, plural } = useI18n()
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

/** Plagát na výšku sa v hlavičke škáluje podľa výšky, na šírku podľa šírky. */
const posterPortrait = ref(true)
function onPosterLoad(e: Event) {
  const img = e.target as HTMLImageElement
  posterPortrait.value = img.naturalHeight > img.naturalWidth
}
/** Obrázok na šírku ide v hlavičke „na spad" — bez rámčeka až po okraje. */
const posterBleed = computed(() => Boolean(heroImage.value) && !posterPortrait.value)

const heroTile = computed(() => (event.value?.startAt ? dateTile(event.value.startAt) : null))

// Cena podujatia (`event.priceAmount`) býva prázdna, keď sa predáva cez typy
// lístkov — vtedy by hlavička hlásila „Zdarma" popri platenom lístku. Rozhodujú
// preto samotné lístky; bez nich (alebo kým sa nenačítajú) platí cena podujatia.
const mainTickets = computed(() => ticketTypes.value.filter(t => t.kind === 'ticket' && t.isActive !== false))
const onlyPaidTickets = computed(
  () => mainTickets.value.length > 0 && mainTickets.value.every(t => (t.priceAmount ?? 0) > 0),
)

const priceLabel = computed(() => {
  const tickets = mainTickets.value
  if (!tickets.length) return formatPriceOrFree(event.value?.priceAmount, event.value?.priceCurrency)
  if (!onlyPaidTickets.value) return t('common.free')
  const prices = tickets.map(x => x.priceAmount ?? 0)
  const min = Math.min(...prices)
  const label = formatPrice(min, tickets.find(x => x.priceAmount === min)?.priceCurrency)
  return min === Math.max(...prices) ? label : `${t('public.event.priceFrom')} ${label}`
})

const registerLabel = computed(() => t(onlyPaidTickets.value ? 'tickets.request.buy' : 'public.event.register'))
const hasPaidPrice = computed(() => (event.value?.priceAmount ?? 0) > 0 || onlyPaidTickets.value)

/** Porovnanie názvov bez ohľadu na diakritiku a veľkosť písmen. */
const isSameLabel = (a: string, b: string) =>
  a.trim().localeCompare(b.trim(), undefined, { sensitivity: 'base' }) === 0

const placeLabel = computed(() => {
  const e = event.value
  if (!e) return ''
  const place = e.venue?.name ?? e.locationName ?? null
  const town = e.municipality?.name ?? null
  const extraTown = town && (!place || !isSameLabel(place, town)) ? town : null
  return [place, extraTown].filter(Boolean).join(' · ')
})

/**
 * Import bez rozpoznanej obce priradí zberné miesto „Celé Slovensko" — venue
 * aj obec potom nesú ten istý text a karta Miesto by ho vypísala dvakrát pod
 * sebou. Obec ukážeme len vtedy, keď hovorí niečo navyše.
 */
const municipalityLabel = computed(() => {
  const e = event.value
  const town = e?.municipality?.fullname ?? e?.municipality?.name ?? null
  if (!town) return null
  const place = e?.venue?.name ?? e?.locationName ?? null
  return place && isSameLabel(place, town) ? null : town
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
    { label: t('public.breadcrumb.home'), to: '/' },
    { label: t('public.breadcrumb.events'), to: PUBLIC_EVENTS },
    { label: e.name },
  ]
})

/** „Zostávajú 3 dni" pri blížiacej sa uzávierke; ďaleký termín netlačí. */
const deadlineCountdown = computed(() => {
  const deadline = event.value?.registrationDeadlineAt
  if (!deadline) return null
  const days = daysUntil(deadline)
  if (days > 14) return null
  if (days === 0) return t('public.event.countdownToday')
  if (days === 1) return t('public.event.countdownLastDay')
  return plural('public.event.countdownDays', days)
})

// Lišta má zmysel len tam, kde sa dá niečo urobiť: registrácia je zapnutá
// a návštevník ešte prihlásený nie je. Skončené podujatie registráciu nemá
// (formulár v paneli hlási „registrácia nie je možná"), lišta ju teda neponúka.
const showMobileCta = computed(() => Boolean(event.value?.reservable) && !viewerRegistered.value && !hasEnded.value)

/**
 * Podujatie, ktoré sa ešte len chystá. Bez termínu to nevieme posúdiť, takže
 * ho berieme ako budúce — chýbajúci dátum je bežný pri importe a skryť kvôli
 * nemu jedinú akciu na stránke by bolo horšie než ju ponúknuť zbytočne.
 */
const isUpcoming = computed(() => {
  const start = event.value?.startAt
  return start ? new Date(start).getTime() > Date.now() : true
})

/**
 * Podujatie, ktoré už bolo. Zrkadlo `EventTimeframe::hasEnded()` na backende:
 * bez `end_at` platí celý deň začiatku, aby jednodňová akcia zadaná len dátumom
 * nebola „skončená" už ráno v deň konania.
 *
 * Detail skončeného podujatia ostáva verejný navždy — vedú naň odkazy z Googlu,
 * z e-mailov aj zo zdieľaní. Práve preto musí stránka povedať, že akcia už bola;
 * inak návštevník z vyhľadávača číta pozvánku na niečo, čo mu ušlo.
 */
const hasEnded = computed(() => {
  const e = event.value
  if (!e) return false
  if (e.endAt) return new Date(e.endAt).getTime() < Date.now()
  if (!e.startAt) return false
  const startOfToday = new Date()
  startOfToday.setHours(0, 0, 0, 0)
  return new Date(e.startAt).getTime() < startOfToday.getTime()
})

/**
 * Bezplatné podujatie bez lístkov nemá na stránke **žiadnu** akciu: registračná
 * sekcia aj mobilná lišta sú skryté a návštevníkovi zostane „Kopírovať odkaz".
 * Takto vyzerá väčšina importovaného katalógu, preto tam patrí aspoň
 * „Pripomeň mi".
 */
const showRemindMe = computed(() => Boolean(event.value) && isUpcoming.value)

// Na mobile buď registrácia, alebo pripomienka — nikdy prázdna lišta.
const showMobileRemind = computed(() => !showMobileCta.value && showRemindMe.value)

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
    workshopError.value = err.response?.data?.message ?? t('public.event.workshopFailed')
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

const venueOpeningHours = computed(() => {
  const oh = event.value?.venue?.openingHours
  if (!oh || typeof oh !== 'object' || Array.isArray(oh)) return []
  return Object.entries(oh as Record<string, string | null>)
    .filter(([, hours]) => hours)
    .map(([day, hours]) => ({ day: weekdayLabel(day), hours: hours as string }))
})

// Use event's own coords first, fall back to venue coords
// Zástupný stred Slovenska (NationwideCoordinates) je „poloha neznáma" — online
// podujatie ani zberné „Celé Slovensko" nemajú čo ukazovať na mape.
const isPlaceholderCoords = (lat: number, lng: number) =>
  Math.abs(lat - 48.7411522) < 1e-6 && Math.abs(lng - 19.4528646) < 1e-6

const mapCoords = computed(() => {
  const ev = event.value
  if (!ev) return null
  const candidates = [
    [ev.latitude, ev.longitude],
    [ev.venue?.latitude, ev.venue?.longitude],
  ]
  for (const [rawLat, rawLng] of candidates) {
    const lat = rawLat ? parseFloat(String(rawLat)) : NaN
    const lng = rawLng ? parseFloat(String(rawLng)) : NaN
    if (lat && lng && !isPlaceholderCoords(lat, lng)) return { lat, lng }
  }
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
  if (!e) return { title: t('common.loading') }
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
      { property: 'og:locale', content: localeTag().replace('-', '_') },
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
    // Id je vlastný segment (`akcie/{id}/{slug}`), takže sa z adresy nemusí
    // dolovať — slug za ním je len ozdoba a routa ho ani nevyžaduje.
    const ev = await showPublicEvent(String(route.params.id))

    // Adresa sa zosúladí s kanonickou podobou — na detail sa dá doraziť aj
    // zo starého číselného odkazu alebo so zastaraným slugom po premenovaní.
    // `replace`, nie `push`: v histórii nemá vzniknúť krok navyše.
    const canonicalPath = publicEventPath(ev)
    if (route.path !== canonicalPath) {
      // Hash sa nesie ďalej — karta vo výpise odkazuje na `#registracia`.
      router.replace({ path: canonicalPath, hash: route.hash })
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

  // Tlačidlo lístkov na karte vedie na `#registracia`. Router tam scrollovať
  // nevie — sekcia existuje až po zmiznutí načítavania, teda až teraz.
  if (route.hash === '#registracia' && event.value?.reservable) {
    await nextTick()
    scrollToRegistration()
  }
}

onMounted(load)
</script>
