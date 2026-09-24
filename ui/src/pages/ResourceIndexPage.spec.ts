import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'

const { get } = vi.hoisted(() => ({ get: vi.fn() }))
vi.mock('@/api/index', () => ({ default: { get, post: vi.fn(), delete: vi.fn() } }))

import ResourceIndexPage from './ResourceIndexPage.vue'

function pagedResponse(currentPage: number, lastPage = 5) {
  return { data: { data: [], meta: { current_page: currentPage, last_page: lastPage } } }
}

async function mountAt(path: string) {
  const router: Router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }],
  })
  await router.push(path)

  const wrapper = mount(ResourceIndexPage, {
    props: { resource: 'event' as const, scope: 'dashboard' as const },
    global: { plugins: [router] },
  })
  await flushPromises()

  return { wrapper, router }
}

/** Parametre posledného volania API. */
function lastParams() {
  const calls = get.mock.calls
  return calls[calls.length - 1]?.[1]?.params as Record<string, unknown>
}

describe('ResourceIndexPage — stránkovanie v adrese', () => {
  beforeEach(() => {
    get.mockReset()
    get.mockImplementation((_url: string, config: { params: { page: number } }) =>
      Promise.resolve(pagedResponse(config.params.page)))
  })

  it('načíta stranu z adresy — návrat z detailu nespadne na prvú stranu', async () => {
    await mountAt('/dashboard/events?page=3')

    expect(lastParams()['page']).toBe(3)
  })

  it('prestránkovanie zapíše stranu do adresy a nechá filtre na mieste', async () => {
    const { wrapper, router } = await mountAt('/dashboard/events?q=kino')

    await wrapper.findAll('.page-btn')[1]!.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({ q: 'kino', page: '2' })
    expect(lastParams()['page']).toBe(2)
  })

  it('tlačidlo „späť" dotiahne predchádzajúcu stranu', async () => {
    const { wrapper, router } = await mountAt('/dashboard/events')

    await wrapper.findAll('.page-btn')[2]!.trigger('click')
    await flushPromises()
    expect(lastParams()['page']).toBe(3)

    router.back()
    await flushPromises()

    expect(router.currentRoute.value.query['page']).toBeUndefined()
    expect(lastParams()['page']).toBe(1)
  })

  it('zmena filtra zhodí stranu — v užšom výsledku nemusí existovať', async () => {
    const { wrapper, router } = await mountAt('/dashboard/events?page=4')
    expect(lastParams()['page']).toBe(4)

    const sort = wrapper.findAll('select')
      .find((s) => s.findAll('option').some((o) => o.attributes('value') === 'oldest'))!
    await sort.setValue('oldest')
    await flushPromises()

    expect(lastParams()['page']).toBe(1)
    expect(router.currentRoute.value.query['page']).toBeUndefined()
  })
})

// Regression coverage for refresh and empty-state behavior.
describe('ResourceIndexPage — refresh', () => {
  beforeEach(() => { get.mockReset() })
  it('retains rows during refresh and after a failure, then offers retry', async () => {
    get.mockResolvedValueOnce({ data: { data: [{ id: 1, name: 'Existing event' }], meta: { current_page: 1, last_page: 2 } } })
    const { wrapper } = await mountAt('/dashboard/events')
    let reject!: (reason: Error) => void
    get.mockReturnValueOnce(new Promise((_resolve, fail) => { reject = fail }))
    await wrapper.findAll('.page-btn')[1]!.trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Existing event')
    expect(wrapper.get('.index-list').classes()).toContain('opacity-50')
    reject(new Error('offline')); await flushPromises()
    expect(wrapper.text()).toContain('Existing event')
    expect(wrapper.find('[role="alert"] button').exists()).toBe(true)
    wrapper.unmount()
  })
  it('clears active filters instead of offering an unauthorized create action', async () => {
    get.mockResolvedValue({ data: { data: [], meta: { current_page: 1, last_page: 1, permissions: { create: false } } } })
    const { wrapper, router } = await mountAt('/dashboard/events?q=missing')
    expect(wrapper.find('a[href="/dashboard/events/create"]').exists()).toBe(false)
    await wrapper.get('.index-list button').trigger('click'); await flushPromises()
    expect(router.currentRoute.value.query).toEqual({})
    expect(lastParams().search).toBeUndefined()
    wrapper.unmount()
  })
})
