import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import LoginPage from './LoginPage.vue'

const { login, push, route } = vi.hoisted(() => ({ login: vi.fn(), push: vi.fn(), route: { query: {} as Record<string, string> } }))
vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ login }) }))
vi.mock('vue-router', () => ({ useRouter: () => ({ push }), useRoute: () => route }))
vi.mock('@/i18n', () => ({ t: (key: string) => key }))
vi.mock('@unhead/vue', () => ({ useHead: vi.fn() }))
vi.mock('@/composables/useFormValidation', () => ({ provideFormValidation: () => ({ markValidated: vi.fn() }) }))

beforeEach(() => {
  vi.clearAllMocks()
  route.query = { event: '42' }
})
function page() {
  return mount(LoginPage, { global: { stubs: {
    RouterLink: true, GoogleSignInButton: true, TermsConsentField: true,
    FormField: { props: ['modelValue', 'type'], template: `<input :type="type" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" />` },
  } } })
}

describe('pending login', () => {
  it('opens verification with the email and event context', async () => {
    login.mockRejectedValue({ response: { data: { code: 'email_not_verified' } } })
    const wrapper = page()
    await wrapper.find('input[type=email]').setValue('Jana@Example.test')
    await wrapper.find('input[type=password]').setValue('password')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(push).toHaveBeenCalledWith({ name: 'verify-email', query: { email: 'jana@example.test', event: '42' } })
  })
  it('keeps invalid credentials on the login form', async () => {
    login.mockRejectedValue({ response: { data: { message: 'Invalid login details' } } })
    const wrapper = page()
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(push).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Invalid login details')
  })
  it('passes the redirect on to registration', () => {
    route.query = { redirect: '/organizatori/5' }
    const targets = page().findAllComponents({ name: 'RouterLink' }).map(l => l.vm.$attrs.to as { name?: string })
    expect(targets.find(to => to?.name === 'register')).toEqual({ name: 'register', query: { redirect: '/organizatori/5' } })
  })
})
