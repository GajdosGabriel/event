<template>
  <div class="relative" ref="rootEl">
    <button
      class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm transition"
      :class="triggerClass"
      @click="open = !open"
    >
      <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold uppercase" :class="avatarClass">
        {{ initials }}
      </span>
      <span class="max-w-[160px] truncate">{{ auth.displayName }}</span>
      <svg
        class="h-3.5 w-3.5 shrink-0 transition-transform duration-150"
        :class="{ 'rotate-180': open }"
        fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>

    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="scale-95 opacity-0"
      enter-to-class="scale-100 opacity-100"
      leave-active-class="transition duration-75 ease-in"
      leave-from-class="scale-100 opacity-100"
      leave-to-class="scale-95 opacity-0"
    >
      <div
        v-if="open"
        class="absolute right-0 top-full z-50 mt-2 w-56 origin-top-right overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
      >
        <div class="border-b border-slate-100 px-4 py-3">
          <p class="truncate text-sm font-semibold text-slate-900">{{ auth.displayName }}</p>
          <p v-if="auth.identity?.email && auth.identity.email !== auth.displayName" class="truncate text-xs text-slate-500">
            {{ auth.identity.email }}
          </p>
          <div v-if="auth.isSuperAdmin" class="mt-1.5">
            <span class="inline-block rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">Super admin</span>
          </div>
        </div>

        <div class="py-1">
          <RouterLink
            v-for="link in navLinks"
            :key="link.to"
            :to="link.to"
            class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
            @click="open = false"
          >
            <component :is="link.icon" class="h-4 w-4 shrink-0 text-slate-400" />
            <span class="truncate">{{ link.label }}</span>
          </RouterLink>
        </div>

        <div class="border-t border-slate-100 py-1">
          <button
            class="flex w-full items-center gap-2.5 px-4 py-2 text-sm text-red-600 transition hover:bg-red-50"
            @click="handleLogout"
          >
            <IconLogout class="h-4 w-4 shrink-0" />
            Odhlásiť sa
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, defineComponent, h } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const props = withDefaults(defineProps<{
  variant?: 'dark' | 'teal' | 'amber'
  logoutTo?: string
}>(), {
  variant: 'dark',
  logoutTo: 'home',
})

const auth = useAuthStore()
const router = useRouter()
const open = ref(false)
const rootEl = ref<HTMLElement | null>(null)

const triggerClass = computed(() => ({
  dark:  'text-slate-300 hover:bg-white/10 hover:text-white',
  teal:  'text-teal-50/80 hover:bg-teal-200/15 hover:text-white',
  amber: 'text-amber-50/80 hover:bg-amber-200/15 hover:text-white',
}[props.variant]))

const avatarClass = computed(() => ({
  dark:  'bg-blue-600 text-white',
  teal:  'bg-teal-300 text-teal-950',
  amber: 'bg-amber-300 text-amber-950',
}[props.variant]))

const initials = computed(() => {
  const name = auth.displayName
  if (!name) return '?'
  const parts = name.split(/[\s@._-]+/).filter(Boolean)
  return parts.length >= 2
    ? (parts[0][0] + parts[1][0]).toUpperCase()
    : name.slice(0, 2).toUpperCase()
})

const navLinks = computed(() => {
  const links: { to: string; label: string; icon: ReturnType<typeof defineComponent> }[] = []

  links.push({ to: '/dashboard', label: 'Dashboard', icon: IconDashboard })

  if (auth.isSuperAdmin) {
    links.push({ to: '/admin', label: 'Admin', icon: IconAdmin })
  }

  links.push({ to: '/', label: 'Verejná časť', icon: IconGlobe })

  for (const canal of auth.identity?.canals ?? []) {
    links.push({ to: `/dashboard/canals/${canal.id}`, label: canal.name, icon: IconCanal })
  }

  return links
})

function handleLogout() {
  open.value = false
  auth.logout().then(() => router.push({ name: props.logoutTo }))
}

function onClickOutside(e: MouseEvent) {
  if (rootEl.value && !rootEl.value.contains(e.target as Node)) open.value = false
}

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside))

const IconDashboard = defineComponent({ render: () => h('svg', { fill: 'none', stroke: 'currentColor', 'stroke-width': '2', viewBox: '0 0 24 24' }, [h('rect', { x: '3', y: '3', width: '7', height: '7', rx: '1' }), h('rect', { x: '14', y: '3', width: '7', height: '7', rx: '1' }), h('rect', { x: '3', y: '14', width: '7', height: '7', rx: '1' }), h('rect', { x: '14', y: '14', width: '7', height: '7', rx: '1' })]) })
const IconAdmin = defineComponent({ render: () => h('svg', { fill: 'none', stroke: 'currentColor', 'stroke-width': '2', viewBox: '0 0 24 24' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' }), h('circle', { cx: '12', cy: '12', r: '3' })]) })
const IconGlobe = defineComponent({ render: () => h('svg', { fill: 'none', stroke: 'currentColor', 'stroke-width': '2', viewBox: '0 0 24 24' }, [h('circle', { cx: '12', cy: '12', r: '10' }), h('path', { 'stroke-linecap': 'round', d: 'M2 12h20M12 2c-2.5 2.5-4 5.9-4 10s1.5 7.5 4 10M12 2c2.5 2.5 4 5.9 4 10s-1.5 7.5-4 10' })]) })
const IconCanal = defineComponent({ render: () => h('svg', { fill: 'none', stroke: 'currentColor', 'stroke-width': '2', viewBox: '0 0 24 24' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' })]) })
const IconLogout = defineComponent({ render: () => h('svg', { fill: 'none', stroke: 'currentColor', 'stroke-width': '2', viewBox: '0 0 24 24' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1' })]) })
</script>
