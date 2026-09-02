import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import AiAssistPanel from './AiAssistPanel.vue'
import type { AiKind, ReadinessRules } from '@/api/ai'

/**
 * Panel „Vyplniť pomocou AI".
 *
 * Testuje sa hlavne to, čo je na ňom rozhodnutie a nie kód: kedy sa ukáže,
 * kedy ostane skrytý a čo z neho návrh nikdy neurobí sám.
 */

const RULES: ReadinessRules = {
  event: [
    { key: 'name', rule: 'filled', fields: ['name'] },
    { key: 'body', rule: 'min_chars', fields: ['body'], value: 50 },
    { key: 'contact', rule: 'any_of', fields: ['website', 'email', 'phone'] },
  ],
  venue: [{ key: 'name', rule: 'filled', fields: ['name'] }],
  canal: [{ key: 'name', rule: 'filled', fields: ['name'] }],
}

const aiAssist = vi.fn()
const fetchContentReview = vi.fn()

vi.mock('@/api/ai', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api/ai')>()
  return {
    ...actual,
    fetchReadinessRules: () => Promise.resolve(RULES),
    aiAssist: (...args: unknown[]) => aiAssist(...args),
    fetchContentReview: (...args: unknown[]) => fetchContentReview(...args),
  }
})

const routeQuery: { ai?: string } = {}

vi.mock('vue-router', () => ({
  useRoute: () => ({ query: routeQuery }),
}))

vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn() }),
}))

const LONG_BODY = '<p>Toto je popis podujatia, ktorý je dosť dlhý na to, aby prešiel hranicou.</p>'

function mountPanel(options: {
  kind?: AiKind
  body?: string
  values?: Record<string, unknown>
  recordId?: number | null
} = {}) {
  return mount(AiAssistPanel, {
    props: {
      modelValue: options.body ?? LONG_BODY,
      kind: options.kind ?? 'event',
      scope: 'dashboard',
      values: options.values ?? { name: 'Púť', body: options.body ?? LONG_BODY, email: 'a@b.sk' },
      name: 'Púť na Butkov',
      recordId: options.recordId ?? null,
    },
  })
}

beforeEach(() => {
  aiAssist.mockReset()
  fetchContentReview.mockReset()
  fetchContentReview.mockResolvedValue(null)
  delete routeQuery.ai
})

describe('AiAssistPanel', () => {
  it('stays hidden until the record is ready to publish', async () => {
    // Chýba kontakt — panel nemá čo ponúkať, kým chýba obsah.
    const wrapper = mountPanel({ values: { name: 'Púť', body: LONG_BODY } })
    await flushPromises()

    expect(wrapper.text()).not.toContain('Fill in with AI')
    expect(wrapper.text()).toContain('Ready to publish')
  })

  it('names what is still missing', async () => {
    const wrapper = mountPanel({ values: { name: '', body: LONG_BODY } })
    await flushPromises()

    expect(wrapper.text()).toContain('name')
    expect(wrapper.text()).toContain('contact')
  })

  it('appears once everything is filled in', async () => {
    const wrapper = mountPanel()
    await flushPromises()

    expect(wrapper.text()).toContain('Fill in with AI')
    expect(wrapper.text()).not.toContain('Ready to publish')
  })

  it('counts the description as text, not markup', async () => {
    // Surové HTML má cez 50 znakov, viditeľný text nie.
    const wrapper = mountPanel({
      body: '<p><a href="https://example.com/velmi/dlha/adresa">tu</a></p>',
      values: {
        name: 'Púť',
        body: '<p><a href="https://example.com/velmi/dlha/adresa">tu</a></p>',
        email: 'a@b.sk',
      },
    })
    await flushPromises()

    expect(wrapper.text()).not.toContain('Fill in with AI')
  })

  it('does not offer writing from scratch for an event', async () => {
    const wrapper = mountPanel({ kind: 'event' })
    await flushPromises()
    await wrapper.find('button').trigger('click')

    // Dátum ani program si model domyslieť nesmie.
    expect(wrapper.text()).not.toContain('Write the description for me')
  })

  it('offers writing from scratch for a canal', async () => {
    const wrapper = mountPanel({ kind: 'canal', values: { name: 'Farnosť Belá' } })
    await flushPromises()
    await wrapper.find('button').trigger('click')

    expect(wrapper.text()).toContain('Write the description for me')
  })

  it('shows the suggestion but never applies it on its own', async () => {
    aiAssist.mockResolvedValue({
      success: true,
      improved_text: '<p>Vylepšený text.</p>',
      changes_summary: 'Opravil som čiarky.',
    })

    const wrapper = mountPanel()
    await flushPromises()
    await wrapper.find('button').trigger('click')

    await wrapper.find('button[type="button"].btn').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Opravil som čiarky.')
    // Text, ktorý človek písal, mu nemá zmiznúť pod rukami.
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('applies the suggestion only when confirmed', async () => {
    aiAssist.mockResolvedValue({
      success: true,
      improved_text: '<p>Vylepšený text.</p>',
      changes_summary: 'Opravil som čiarky.',
    })

    const wrapper = mountPanel()
    await flushPromises()
    await wrapper.find('button').trigger('click')
    await wrapper.find('button[type="button"].btn').trigger('click')
    await flushPromises()

    const apply = wrapper.findAll('button').find(b => b.text() === 'Use text')
    await apply!.trigger('click')

    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['<p>Vylepšený text.</p>'])
  })

  it('opens preselected from the e-mail link', async () => {
    routeQuery.ai = 'grammar,expand'

    const wrapper = mountPanel()
    await flushPromises()

    const checked = wrapper.findAll('input[type="checkbox"]')
      .filter(i => (i.element as HTMLInputElement).checked)
      .map(i => (i.element as HTMLInputElement).value)

    expect(checked.sort()).toEqual(['expand', 'grammar'])
  })

  it('ignores junk in the ai query parameter', async () => {
    routeQuery.ai = 'drop-table,../etc'

    const wrapper = mountPanel()
    await flushPromises()

    // Nič sa nezaškrtlo, takže sa panel ani sám neotvoril — na rozdiel od
    // platného odkazu z e-mailu. Rozbaliť ho musí človek.
    expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(0)

    await wrapper.find('button').trigger('click')

    // Neznáme režimy sa zahodili a ostala predvoľba.
    const checked = wrapper.findAll('input[type="checkbox"]')
      .filter(i => (i.element as HTMLInputElement).checked)
      .map(i => (i.element as HTMLInputElement).value)

    expect(checked.sort()).toEqual(['grammar', 'style'])
  })

  it('shows the notes from the published-content check', async () => {
    fetchContentReview.mockResolvedValue({
      score: 62,
      summary: 'Text má pár chýb.',
      issues: [{ severity: 'warning', mode: 'grammar', message: 'Chýba čiarka.', quote: 'a to' }],
      modes: ['grammar'],
      reviewedAt: '2026-09-01T10:00:00+00:00',
      contentHash: 'abc',
    })

    const wrapper = mountPanel({ recordId: 12 })
    await flushPromises()

    expect(wrapper.text()).toContain('Chýba čiarka.')
    expect(wrapper.text()).toContain('62')
  })
})
