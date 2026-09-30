import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ReadinessBadge from './ReadinessBadge.vue'
import type { ReadinessRules } from '@/api/ai'

const RULES: ReadinessRules = {
  event: [
    { key: 'name', rule: 'filled', fields: ['name'] },
    { key: 'contact', rule: 'any_of', fields: ['website', 'email', 'phone'] },
  ],
  venue: [{ key: 'name', rule: 'filled', fields: ['name'] }],
  canal: [{ key: 'name', rule: 'filled', fields: ['name'] }],
}

vi.mock('@/api/ai', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api/ai')>()
  return { ...actual, fetchReadinessRules: () => Promise.resolve(RULES) }
})

async function mountBadge(values: Record<string, unknown>) {
  const wrapper = mount(ReadinessBadge, { props: { kind: 'event', scope: 'dashboard', values } })
  await flushPromises()
  return wrapper
}

describe('ReadinessBadge', () => {
  it('shows progress and names what is still missing after a click', async () => {
    const wrapper = await mountBadge({ name: '' })

    expect(wrapper.text()).toContain('0/2')
    await wrapper.get('button').trigger('click')
    expect(wrapper.text()).toContain('name')
    expect(wrapper.text()).toContain('contact')
  })

  it('reports full progress when everything is filled in', async () => {
    const wrapper = await mountBadge({ name: 'Púť', email: 'a@b.sk' })

    expect(wrapper.text()).toContain('2/2')
  })
})
