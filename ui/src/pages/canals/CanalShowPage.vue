<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <p v-if="loading" class="text-slate-600">{{ t('common.loading') }}</p>
    <div v-else-if="error" class="show-not-found">
      <h1>{{ t('canals.show.notFound') }}</h1>
      <RouterLink :to="indexRoute">{{ t('common.back') }}</RouterLink>
    </div>

    <template v-else-if="canal">
      <div class="mb-4 flex flex-wrap items-center gap-2">
        <RouterLink :to="indexRoute" class="action-btn">{{ t('common.back') }}</RouterLink>
      </div>

      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <!-- Ľavý stĺpec -->
        <div class="grid gap-4">
          <!-- Hlavné info -->
          <div class="show-card">
            <div class="flex flex-wrap items-start gap-4">
              <img v-if="canal.imageUrl" :src="canal.imageUrl" :alt="canal.name"
                class="h-20 w-20 shrink-0 rounded-xl object-cover ring-1 ring-slate-200" />
              <div class="min-w-0 flex-1">
                <!-- Akcie sedia na riadku s názvom: úpravy, publikovanie aj
                     mazanie sú tie isté ako vo výpise — jedno ovládanie, jedny práva. -->
                <div class="flex flex-wrap items-center gap-2">
                  <h1 class="text-3xl font-bold text-slate-900">{{ canal.name }}</h1>
                  <span v-if="canal.identityModeLabel" class="rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-700">
                    {{ canal.identityModeLabel }}
                  </span>
                  <ResourceActionsMenu
                    class="ml-auto shrink-0"
                    resource="canal"
                    :scope="scope"
                    :item="canal"
                    :show-view="false"
                    @changed="reload"
                    @removed="router.push(indexRoute)"
                  />
                </div>
                <p v-if="canal.titlePrefix || canal.titleSuffix" class="mt-1 text-sm text-slate-500">
                  <span v-if="canal.titlePrefix">{{ canal.titlePrefix }} </span>
                  {{ canal.name }}
                  <span v-if="canal.titleSuffix"> {{ canal.titleSuffix }}</span>
                </p>
              </div>
            </div>
            <div v-if="canal.body" class="prose prose-slate mt-4 max-w-none text-slate-700" v-html="canal.body" />
          </div>

          <!-- Mapa -->
          <div v-if="canal.latitude && canal.longitude" class="show-card overflow-hidden p-0">
            <iframe
              :src="`https://www.openstreetmap.org/export/embed.html?bbox=${canal.longitude - 0.005},${canal.latitude - 0.003},${canal.longitude + 0.005},${canal.latitude + 0.003}&layer=mapnik&marker=${canal.latitude},${canal.longitude}`"
              class="h-72 w-full border-0"
              loading="lazy"
            />
            <div class="px-4 py-2 text-xs text-slate-500">
              GPS: {{ canal.latitude }}, {{ canal.longitude }} ·
              <a :href="`https://www.google.com/maps?q=${canal.latitude},${canal.longitude}`" target="_blank" class="text-blue-600">{{ t('common.googleMaps') }}</a>
            </div>
          </div>

          <!-- Galéria -->
          <div v-if="files.length" class="show-card">
            <h2 class="mb-3 text-base font-semibold text-slate-800">{{ t('common.gallery') }}</h2>
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
              <a v-for="f in files" :key="f.id" :href="f.url" target="_blank"
                class="block aspect-square overflow-hidden rounded-lg border border-slate-200">
                <img :src="f.thumbUrl ?? f.url" :alt="f.name"
                  class="h-full w-full object-cover transition-transform hover:scale-105" />
              </a>
            </div>
          </div>

          <!-- Miesta (venues) -->
          <div v-if="canal.venuesList.length" class="show-card">
            <h2 class="mb-3 text-base font-semibold text-slate-800">{{ t('venues.index.title') }}</h2>
            <ul class="grid gap-1.5">
              <li v-for="v in canal.venuesList" :key="v.id"
                class="flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                <span v-if="v.isOwner" class="shrink-0 text-xs font-semibold text-teal-600">{{ t('common.owner') }}</span>
                <RouterLink :to="`${prefix}/venues/${v.id}`"
                  class="flex-1 truncate text-sm font-medium text-slate-900 no-underline hover:text-blue-700">
                  {{ v.name }}
                </RouterLink>
                <RouterLink :to="`${prefix}/venues/${v.id}`" class="action-btn shrink-0">{{ t('common.detail') }}</RouterLink>
              </li>
            </ul>
          </div>

          <!-- Tím kanála (len v dashboarde — admin spravuje používateľov inde).
               Detail tím ukazuje, meniť sa dá v úprave kanála. -->
          <CanalTeamPanel
            v-if="scope === 'dashboard'"
            :canal-id="canal.id"
            readonly
            :manage-to="`${prefix}/canals/${canal.id}/edit#team`"
          />

          <!-- Eventy kanálu -->
          <div class="show-card">
            <div class="mb-3 flex items-center justify-between gap-2">
              <h2 class="text-base font-semibold text-slate-800">{{ t('canals.show.events') }}</h2>
              <RouterLink :to="`${prefix}/events`" class="text-xs text-blue-600 hover:underline">{{ t('events.index.all') }}</RouterLink>
            </div>
            <p v-if="eventsLoading" class="text-sm text-slate-500">{{ t('common.loading') }}</p>
            <p v-else-if="!events.length" class="text-sm text-slate-400">{{ t('events.index.empty') }}</p>
            <ul v-else class="grid gap-1.5">
              <li v-for="ev in events" :key="ev.id"
                class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                <span class="h-2 w-2 shrink-0 rounded-full"
                  :class="ev.status === 'published' ? 'bg-green-500' : ev.status === 'archived' ? 'bg-slate-400' : 'bg-amber-400'" />
                <RouterLink :to="`${prefix}/events/${ev.id}`"
                  class="flex-1 min-w-0 truncate text-sm font-medium text-slate-900 no-underline hover:text-blue-700">
                  {{ ev.name }}
                </RouterLink>
                <span v-if="ev.startAt" class="shrink-0 text-xs text-slate-500">{{ formatDate(ev.startAt) }}</span>
                <div class="shrink-0">
                  <!-- Viď VenueShowPage — položky aj odkazy riadi policy. -->
                  <RowActions v-if="ev.permissions?.view || ev.permissions?.update">
                    <RouterLink v-if="ev.permissions?.view" :to="`${prefix}/events/${ev.id}`" class="row-menu-item">{{ t('common.view') }}</RouterLink>
                    <RouterLink v-if="ev.permissions?.update" :to="`${prefix}/events/${ev.id}/edit`" class="row-menu-item">{{ t('common.edit') }}</RouterLink>
                  </RowActions>
                </div>
              </li>
            </ul>
          </div>
        </div>

        <!-- Pravý stĺpec -->
        <aside class="grid gap-4 self-start">
          <dl class="show-card grid gap-3">
            <!-- Organizácia (fakturačná identita kanála) -->
            <div v-if="canal.organization" class="detail-card">
              <dt>{{ t('common.organization') }}</dt>
              <dd>
                <RouterLink :to="`${prefix}/organizations/${canal.organization.id}/edit`" class="text-blue-700 no-underline hover:underline">
                  {{ canal.organization.name }}
                </RouterLink>
              </dd>
            </div>

            <!-- Adresa sídla. Rovnaký zápis ako na detaile miesta — obec je
                 povinná, zvyšok adresy pribudol s editorom adresy. -->
            <div v-if="canal.street || canal.municipality" class="detail-card">
              <dt>{{ t('common.address') }}</dt>
              <dd>
                <span v-if="canal.street">{{ canal.street }}<br/></span>
                <span v-if="canal.postcode">{{ canal.postcode }} </span>
                <span v-if="canal.municipality">{{ canal.municipality.name }}</span>
                <span v-if="canal.country && canal.country !== 'Slovakia'" class="block text-slate-500 text-xs">{{ canal.country }}</span>
              </dd>
            </div>

            <!-- Kontakt -->
            <div v-if="canal.phone" class="detail-card">
              <dt>{{ t('common.phone') }}</dt>
              <dd><a :href="`tel:${canal.phone}`" class="text-blue-700">{{ canal.phone }}</a></dd>
            </div>
            <div v-if="canal.website" class="detail-card">
              <dt>{{ t('common.website') }}</dt>
              <dd><a :href="canal.website" target="_blank" class="break-all text-blue-700">{{ canal.website }}</a></dd>
            </div>

            <!-- Členovia sú v dashboarde v paneli „Tím kanála"; tu už len pre admina. -->
            <div v-if="scope === 'admin' && canal.membersList.length" class="detail-card">
              <dt>{{ t('canals.show.members') }}</dt>
              <dd class="mt-1 grid gap-1">
                <span v-for="m in canal.membersList" :key="m.id"
                  class="flex items-center gap-1.5 text-sm text-slate-700">
                  <span v-if="m.isOwner" class="text-xs font-semibold text-teal-600">{{ t('common.owner') }}</span>
                  {{ m.name }}
                </span>
              </dd>
            </div>

            <!-- Stav a technické dátumy (publikované/vytvorené/upravené) tu
                 zámerne nie sú — pri práci s kanálom ich nikto nečíta. Kôš je
                 iné: zmazaný záznam musí byť na prvý pohľad vidieť. -->
            <div v-if="canal.deletedAt" class="detail-card bg-red-50">
              <dt class="text-red-600">{{ t('common.deletedAt') }}</dt>
              <dd>{{ formatDate(canal.deletedAt) }}</dd>
            </div>
          </dl>

          <div v-if="canal.contactable" class="mt-4">
            <ContactButton target-type="canal" :target-id="canal.id" :target-name="canal.name" />
          </div>
        </aside>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showCanal, listCanalEvents, type CanalEventItem } from '@/api/canals'
import { listFiles, type FileItem } from '@/api/files'
import CanalTeamPanel from '@/components/CanalTeamPanel.vue'
import ResourceActionsMenu from '@/components/ResourceActionsMenu.vue'
import RowActions from '@/components/RowActions.vue'
import ContactButton from '@/components/ContactButton.vue'
import { fmtDate } from '@/utils/dateFormat'
import { useI18n } from '@/i18n'
import type { CanalItem } from '@/types'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const indexRoute = computed(() => `${prefix.value}/canals`)

const canal = ref<CanalItem | null>(null)
const loading = ref(false)
const error = ref(false)
const files = ref<FileItem[]>([])
const events = ref<CanalEventItem[]>([])
const eventsLoading = ref(false)

function formatDate(d: string | null) {
  return d ? fmtDate(d) : t('common.none')
}

/** Po akcii z menu (publikovanie, obnova) — stav aj práva prídu nanovo. */
async function reload() {
  try {
    canal.value = await showCanal(scope.value, Number(route.params.id))
  } catch {
    error.value = true
  }
}

onMounted(async () => {
  const id = Number(route.params.id)
  loading.value = true
  try {
    canal.value = await showCanal(scope.value, id)
    document.title = canal.value.name

    eventsLoading.value = true
    const [filesRes, eventsRes] = await Promise.allSettled([
      listFiles({ fileable_type: 'canal', fileable_id: id }),
      listCanalEvents(scope.value, id),
    ])
    if (filesRes.status === 'fulfilled') files.value = filesRes.value.filter(f => !f.deletedAt)
    if (eventsRes.status === 'fulfilled') events.value = eventsRes.value
  } catch {
    error.value = true
  } finally {
    loading.value = false
    eventsLoading.value = false
  }
})
</script>
