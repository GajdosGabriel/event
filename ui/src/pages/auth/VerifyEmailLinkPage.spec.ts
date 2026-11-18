import { mount, flushPromises, RouterLinkStub } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import VerifyEmailLinkPage from './VerifyEmailLinkPage.vue'

const { verifyRegistrationLink } = vi.hoisted(() => ({ verifyRegistrationLink: vi.fn() }))
vi.mock('@/api/auth', () => ({ verifyRegistrationLink }))
vi.mock('vue-router', () => ({ useRoute: () => ({ params: { token: 'abc' } }) }))
vi.mock('@/i18n', () => ({ t: (key: string) => key }))
vi.mock('@/utils/authRedirect', () => ({ takeRememberedRedirect: () => null }))

const event = { id: 42, name: 'Koncert' }

beforeEach(() => vi.clearAllMocks())

async function page() {
  const wrapper = mount(VerifyEmailLinkPage, { global: { stubs: { RouterLink: RouterLinkStub } } })
  await flushPromises()
  return wrapper
}
const targets = (wrapper: Awaited<ReturnType<typeof page>>) =>
  wrapper.findAllComponents(RouterLinkStub).map(l => l.props('to'))

describe('verify email link', () => {
  it('shows the issued ticket right after verification', async () => {
    verifyRegistrationLink.mockResolvedValue({ message: 'ok', reserveEvent: event, signup: { status: 'reserved', event, ticket_uuid: 'u-1' } })
    const wrapper = await page()
    expect(wrapper.text()).toContain('eventSignup.reservedTitle')
    expect(targets(wrapper)).toContain('/tickets/u-1')
    expect(targets(wrapper)).not.toContainEqual({ name: 'event-signup', params: { id: 42 } })
  })

  it('sends a required questionnaire to the event form', async () => {
    verifyRegistrationLink.mockResolvedValue({ message: 'ok', reserveEvent: event, signup: { status: 'needs_form', event, ticket_uuid: null } })
    const wrapper = await page()
    expect(wrapper.text()).toContain('eventSignup.needsFormTitle')
    expect(targets(wrapper)).toContain('/akcie/42#registracia')
  })

  it('falls back to the reservation link when the signup failed', async () => {
    verifyRegistrationLink.mockResolvedValue({ message: 'ok', reserveEvent: event, signup: null })
    const wrapper = await page()
    expect(targets(wrapper)).toContainEqual({ name: 'event-signup', params: { id: 42 } })
  })
})
