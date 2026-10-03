import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, shallowMount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import PublicEventList from './PublicEventList.vue'
import { indexEvents, indexEventMapPoints } from '@/api/events'
import { useSettings } from '@/composables/useSettings'
import { t } from '@/i18n'

vi.mock('@/api/events', () => ({ indexEvents: vi.fn(), indexEventMapPoints: vi.fn() }))
vi.mock('@vueuse/head', () => ({ useHead: vi.fn() }))

async function mountList(path = '/akcie') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }],
  })
  await router.push(path)
  await router.isReady()
  const wrapper = shallowMount(PublicEventList, {
    props: { heading: 'Podujatia', subheading: 'Prehľad' },
    global: { plugins: [router], stubs: { RouterLink: false } },
  })
  await flushPromises()
  return { wrapper, router }
}

beforeEach(() => {
  vi.clearAllMocks()
  useSettings().reset()
  vi.mocked(indexEvents).mockResolvedValue({
    data: [], meta: { current_page: 1, last_page: 1, per_page: 12, total: 0 },
  })
  vi.mocked(indexEventMapPoints).mockResolvedValue({ data: [], total: 0 })
})

describe('public event filters', () => {
  it('reloads the results when switching between the archive and upcoming events', async () => {
    const { wrapper } = await mountList()
    await wrapper.setProps({ list: 'past' })
    await flushPromises()
    expect(indexEvents).toHaveBeenLastCalledWith('public', expect.objectContaining({ list: 'past' }))

    await wrapper.setProps({ list: null })
    await flushPromises()
    expect(indexEvents).toHaveBeenLastCalledWith('public', expect.objectContaining({ list: 'upcoming' }))
    wrapper.unmount()
  })

  it('restores the quick date filter from a shared URL and clears it with All', async () => {
    const { wrapper, router } = await mountList('/akcie?when=today&q=koncert&page=3')
    expect(indexEvents).toHaveBeenLastCalledWith('public', expect.objectContaining({ range: 'today', search: 'koncert', page: 3 }))
    const all = wrapper.findAll('button').find(button => button.text().includes(t('filters.events.all')))!
    await all.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.fullPath).toBe('/akcie')
    expect(indexEvents).toHaveBeenLastCalledWith('public', { list: 'upcoming', page: 1, per_page: 12 })
    wrapper.unmount()
  })

  it('stores quick filters in the URL and applies browser navigation changes', async () => {
    const { wrapper, router } = await mountList()
    const today = wrapper.findAll('button').find(button => button.text().includes(t('public.list.mapWhen.today')))!
    await today.trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query.when).toBe('today')
    expect(indexEvents).toHaveBeenLastCalledWith('public', expect.objectContaining({ range: 'today' }))

    await router.push('/akcie?when=week')
    await flushPromises()
    expect(indexEvents).toHaveBeenLastCalledWith('public', expect.objectContaining({ range: 'week' }))
    wrapper.unmount()
  })

  it('offers a way to clear a date filter with no results', async () => {
    const { wrapper } = await mountList('/akcie?when=week')
    expect(wrapper.text()).toContain(t('public.list.emptyFiltered'))
    expect(wrapper.findAll('button').some(button => button.text() === t('public.list.allEvents'))).toBe(true)
    wrapper.unmount()
  })
})
