import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { reactive } from 'vue'
import { setLocale, t } from '@/i18n'
import PosterUploadWizard from './PosterUploadWizard.vue'

const mocks = vi.hoisted(() => ({
  analyzePoster: vi.fn(),
  rememberPosterDraft: vi.fn(),
  claimPosterDraft: vi.fn(),
  fetchPosterDraft: vi.fn(),
  login: vi.fn(),
  register: vi.fn(),
}))

vi.mock('@/api/posters', () => ({
  analyzePoster: mocks.analyzePoster,
  rememberPosterDraft: mocks.rememberPosterDraft,
  claimPosterDraft: mocks.claimPosterDraft,
  fetchPosterDraft: mocks.fetchPosterDraft,
}))
vi.mock('@/api/municipalities', () => ({ listPublicMunicipalities: () => Promise.resolve([{ id: 1, name: 'Bratislava' }]) }))
vi.mock('@/api/auth', () => ({ register: mocks.register }))
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => reactive({ isAuthenticated: false, login: mocks.login, fetchIdentity: vi.fn() }),
}))

const draft = (sourceKind: string) => ({
  id: 'd1',
  token: 'tok',
  email: null,
  source_kind: sourceKind,
  original_filename: null,
  expires_at: null,
  claimed: false,
  event_id: null,
  analysis: {
    fields: [], found_count: 4, total_count: 8, missing_required: [], can_save: true,
    matches: { canal: null, venue: null },
    source: { kind: sourceKind, page_count: 1, text_length: 100, has_text_layer: true, used_vision: false },
    notice: null,
  },
  suggestion: {
    title: 'Koncert', start_at: '2030-05-01 18:00:00', end_at: null,
    venue: { name: 'Dom kultúry', street_and_number: null, city: 'Bratislava' }, organizer: null,
  },
  description: '<p>Text</p>',
})

beforeEach(() => {
  vi.resetAllMocks()
  setLocale('sk')
  localStorage.clear()
  Element.prototype.scrollIntoView = vi.fn()
})

async function open() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/nahrat-plagat', component: { template: '<div />' } },
      { path: '/verify-email', name: 'verify-email', component: { template: '<div />' } },
    ],
  })
  await router.push('/nahrat-plagat')
  const w = mount(PosterUploadWizard, {
    global: { plugins: [router], stubs: { HtmlEditor: true, SearchableSelect: true } },
  })
  await flushPromises()
  return w
}

async function click(w: VueWrapper, label: string) {
  const button = w.findAll('button').find(b => b.text() === label)
  expect(button, label).toBeTruthy()
  await button!.trigger('click')
  await flushPromises()
}

async function analyzeText(w: VueWrapper) {
  await click(w, t('poster.wizard.textToggleOff'))
  await w.get('textarea').setValue('Pozývame vás na koncert 1. mája 2030 o 18:00 v Dome kultúry v Bratislave.')
  await click(w, t('poster.wizard.textSubmit'))
}

describe('poster wizard', () => {
  it('adapts the waiting screen and review title to pasted text', async () => {
    let finish!: (value: unknown) => void
    mocks.analyzePoster.mockReturnValue(new Promise(done => { finish = done }))
    const w = await open()

    await analyzeText(w)

    expect(w.text()).toContain(t('poster.wizard.textNote'))
    expect(w.text()).not.toContain(t('poster.wizard.scanNote'))
    expect(w.find('[role="progressbar"]').exists()).toBe(true)
    expect(w.text()).toContain('25')

    finish(draft('text'))
    await flushPromises()

    expect(w.text()).toContain(t('poster.wizard.reviewTitleText'))
    expect(w.text()).not.toContain(t('poster.wizard.reviewTitle'))
    w.unmount()
  })

  it('offers to resend verification when the account email is not confirmed', async () => {
    mocks.analyzePoster.mockResolvedValue(draft('text'))
    mocks.rememberPosterDraft.mockResolvedValue(undefined)
    mocks.login.mockRejectedValue({ response: { status: 409, data: { message: 'Email not verified', code: 'email_not_verified' } } })
    const w = await open()

    await analyzeText(w)
    await click(w, t('poster.wizard.continue'))
    await click(w, t('poster.wizard.modeLogin'))
    await w.get('input[type="email"]').setValue('Jana@Example.test')
    await w.get('input[type="password"]').setValue('tajneheslo')
    await click(w, t('poster.wizard.save'))

    expect(w.text()).toContain(t('poster.wizard.emailNotVerified'))
    expect(w.text()).not.toContain('Email not verified')
    const link = w.findAll('a').find(a => a.text() === t('auth.verify.resend'))
    expect(link?.attributes('href')).toBe('/verify-email?email=jana@example.test')
    w.unmount()
  })
})
