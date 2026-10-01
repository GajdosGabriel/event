import { describe, it, expect, beforeEach } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import router from './index'
import NotFoundPage from '@/pages/NotFoundPage.vue'
import { authToken } from '@/api/authToken'
import { t } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import type { AuthIdentity } from '@/types'

// Identita je načítaná, takže stráž na chránenej trase nevolá API.
function signIn() {
  authToken.value = 'token'
  useAuthStore().identity = { roles: [] } as unknown as AuthIdentity
}

describe('router', () => {
  beforeEach(async () => {
    setActivePinia(createPinia())
    authToken.value = null
    await router.push('/')
  })

  it('neznáma cesta padne na 404 trasu', () => {
    expect(router.resolve('/neexistuje-stranka').name).toBe('not-found')
    expect(router.resolve('/a/b/c').name).toBe('not-found')
  })

  it('neplatné ID podujatia má vlastnú 404, platné a statické cesty ostávajú', () => {
    expect(router.resolve('/akcie/abc').name).toBe('event-public-not-found')
    expect(router.resolve('/akcie/5').name).toBe('event-public-show')
    expect(router.resolve('/akcie/archiv').name).toBe('events-public-archive')
  })

  it('404 komponent ukáže správny text a odkaz na úvod', () => {
    const general = mount(NotFoundPage, { global: { stubs: { RouterLink: RouterLinkStub } } })
    expect(general.text()).toContain(t('notFound.title'))
    expect(general.findComponent(RouterLinkStub).props('to')).toBe('/')

    const event = mount(NotFoundPage, { props: { kind: 'event' }, global: { stubs: { RouterLink: RouterLinkStub } } })
    expect(event.text()).toContain(t('notFound.eventTitle'))
  })

  it('hosť uvidí /login a /register', async () => {
    await router.push('/login')
    expect(router.currentRoute.value.name).toBe('login')
    await router.push('/register')
    expect(router.currentRoute.value.name).toBe('register')
  })

  it('prihlásený je z /login a /register presmerovaný na dashboard', async () => {
    signIn()
    await router.push('/login')
    expect(router.currentRoute.value.name).toBe('dashboard')
    await router.push('/register')
    expect(router.currentRoute.value.name).toBe('dashboard')
  })

  it('prihlásený na /login rešpektuje vnútorný redirect, vonkajší ignoruje', async () => {
    signIn()
    await router.push('/login?redirect=/akcie/5')
    expect(router.currentRoute.value.path).toBe('/akcie/5')

    await router.push('/')
    await router.push('/login?redirect=//evil.example')
    expect(router.currentRoute.value.name).toBe('dashboard')
  })
})
