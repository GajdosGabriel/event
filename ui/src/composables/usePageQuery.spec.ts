import { describe, it, expect, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory, type Router } from 'vue-router'
import { usePageQuery } from './usePageQuery'

type Api = ReturnType<typeof usePageQuery>

async function mountWithQuery(initial: string) {
  const fetchPage = vi.fn()
  const router: Router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }],
  })
  let api: Api | undefined

  const Component = defineComponent({
    setup() {
      api = usePageQuery(fetchPage)
      return () => h('div')
    },
  })

  await router.push(initial)
  mount(Component, { global: { plugins: [router] } })

  return { api: api!, fetchPage, router }
}

describe('usePageQuery', () => {
  it('prečíta stranu z adresy — na to, aby „späť" vrátilo zoznam tam, kde bol', async () => {
    const { api } = await mountWithQuery('/podujatia?page=3')

    expect(api.pageFromQuery()).toBe(3)
    expect(api.requestedPage.value).toBe(3)
  })

  it('nezmyselné `?page=` berie ako prvú stranu', async () => {
    for (const query of ['?page=0', '?page=-2', '?page=abc', '?page=1.5', '']) {
      const { api } = await mountWithQuery(`/podujatia${query}`)
      expect(api.pageFromQuery()).toBe(1)
    }
  })

  it('prestránkovanie je navigácia — pridá záznam do histórie a nesie filtre', async () => {
    const { api, router, fetchPage } = await mountWithQuery('/podujatia?q=kino')

    api.goToPage(3)
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({ q: 'kino', page: '3' })
    expect(fetchPage).toHaveBeenCalledWith(3)
  })

  it('prvá strana sa do adresy nepíše — zostane čistá', async () => {
    const { api, router } = await mountWithQuery('/podujatia?page=4')

    api.goToPage(1)
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({})
  })

  it('tlačidlo „späť" dotiahne zoznam pre stranu z adresy', async () => {
    const { api, router, fetchPage } = await mountWithQuery('/podujatia')

    api.goToPage(3)
    await flushPromises()
    fetchPage.mockClear()

    router.back()
    await flushPromises()

    expect(fetchPage).toHaveBeenCalledWith(1)
  })

  it('vlastný zápis do adresy nespustí druhé načítanie', async () => {
    const { api, router, fetchPage } = await mountWithQuery('/podujatia')

    api.load(2)
    api.replaceQuery({ q: 'kino' }, 2)
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({ q: 'kino', page: '2' })
    expect(fetchPage).toHaveBeenCalledTimes(1)
  })

  it('zmena filtra prepíše adresu bez záznamu v histórii a zhodí stranu', async () => {
    const { api, router } = await mountWithQuery('/podujatia?page=3')
    const replace = vi.spyOn(router, 'replace')
    const push = vi.spyOn(router, 'push')

    api.load(1)
    api.replaceQuery({ q: 'kino' }, 1)
    await flushPromises()

    // Písanie do filtra nie je navigácia — „späť" nemá skákať po jeho stavoch.
    expect(push).not.toHaveBeenCalled()
    expect(replace).toHaveBeenCalledWith({ path: '/podujatia', query: { q: 'kino' } })
    expect(router.currentRoute.value.query).toEqual({ q: 'kino' })
  })
})
