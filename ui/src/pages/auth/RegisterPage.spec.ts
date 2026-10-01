import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setLocale, t } from '@/i18n'
import RegisterPage from './RegisterPage.vue'

const { register } = vi.hoisted(() => ({ register: vi.fn() }))
vi.mock('@/api/auth', () => ({ register }))
vi.mock('@/api/events', () => ({ showPublicEvent: vi.fn() }))

beforeEach(() => {
  vi.clearAllMocks()
  setLocale('sk')
})

async function page() {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/login', name: 'login', component: { template: '<div />' } }, { path: '/:pathMatch(.*)*', component: { template: '<div />' } }] })
  await router.push('/registracia')
  return mount(RegisterPage, { global: { plugins: [router, createPinia()], stubs: { GoogleSignInButton: true } } })
}

const field = (w: Awaited<ReturnType<typeof page>>, label: string) =>
  w.findAllComponents({ name: 'FormField' }).find(f => f.props('label') === label)!

describe('registration', () => {
  it('rejects a short password on the password field, not on the confirmation', async () => {
    const w = await page()
    await field(w, t('auth.register.name')).find('input').setValue('Jana')
    await field(w, t('auth.register.email')).find('input').setValue('jana@example.test')
    await field(w, t('auth.register.password')).find('input').setValue('abc')
    await field(w, t('auth.register.passwordConfirm')).find('input').setValue('abc')
    await w.get('form').trigger('submit')
    await flushPromises()

    expect(register).not.toHaveBeenCalled()
    expect(field(w, t('auth.register.password')).props('error')).toBe(t('auth.register.passwordTooShort'))
    expect(field(w, t('auth.register.passwordConfirm')).props('error')).toBeFalsy()
  })

  it('names the terms checkbox by its label text', async () => {
    const w = await page()
    const checkbox = w.get('input[type="checkbox"]')
    const labelledBy = checkbox.attributes('aria-labelledby')
    expect(labelledBy).toBeTruthy()
    const label = w.get(`[id="${labelledBy}"]`)
    expect(label.text()).toContain(t('auth.register.termsLink'))
  })
})
