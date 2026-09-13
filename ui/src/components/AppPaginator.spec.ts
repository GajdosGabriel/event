import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AppPaginator from './AppPaginator.vue'

const labels = (currentPage: number, lastPage: number) =>
  mount(AppPaginator, { props: { currentPage, lastPage } })
    .findAll('.page-btn, .gap')
    .map((el) => el.text())

describe('AppPaginator', () => {
  it('pri jedinej stránke sa nezobrazí nič', () => {
    expect(mount(AppPaginator, { props: { currentPage: 1, lastPage: 1 } }).find('div').exists()).toBe(false)
  })

  it('malý počet stránok ukáže všetky a prechod medzi nimi ponuku nemení', () => {
    expect(labels(1, 4)).toEqual(['1', '2', '3', '4'])
    expect(labels(2, 4)).toEqual(['1', '2', '3', '4'])
    expect(labels(4, 4)).toEqual(['1', '2', '3', '4'])
  })

  it('okno má stálu šírku, prvá a posledná stránka sú vždy dostupné', () => {
    expect(labels(1, 12)).toEqual(['1', '2', '3', '4', '5', '6', '…', '12'])
    expect(labels(4, 12)).toEqual(['1', '2', '3', '4', '5', '6', '…', '12'])
    expect(labels(7, 12)).toEqual(['1', '…', '5', '6', '7', '8', '9', '…', '12'])
    expect(labels(12, 12)).toEqual(['1', '…', '7', '8', '9', '10', '11', '12'])
  })

  it('šípky sa na okrajoch vypnú a klik na číslo emituje cieľovú stránku', async () => {
    const wrapper = mount(AppPaginator, { props: { currentPage: 1, lastPage: 5 } })
    const [prev, next] = wrapper.findAll('.nav-btn')
    expect(prev.attributes('disabled')).toBeDefined()
    expect(next.attributes('disabled')).toBeUndefined()

    await wrapper.findAll('.page-btn')[2].trigger('click')
    expect(wrapper.emitted('change')).toEqual([[3]])
  })
})
