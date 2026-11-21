import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import RemindMeButton from './RemindMeButton.vue'
import { canalSubscriptionTicket, subscribeToCanal, subscribeToEvent, subscriptionTicket } from '@/api/subscriptions'
import { t } from '@/i18n'

vi.mock('@/api/subscriptions', () => ({
  subscriptionTicket: vi.fn().mockResolvedValue('event-ticket'),
  subscribeToEvent: vi.fn().mockResolvedValue(undefined),
  canalSubscriptionTicket: vi.fn().mockResolvedValue('canal-ticket'),
  subscribeToCanal: vi.fn().mockResolvedValue(undefined),
}))

async function openAndSubmit(props: Record<string, unknown>) {
  const wrapper = mount(RemindMeButton, { props, global: { stubs: { teleport: true, transition: false } }, attachTo: document.body })
  await wrapper.find('button').trigger('click')
  await flushPromises()
  await wrapper.find('input[type="email"]').setValue('fan@example.com')
  await wrapper.find('form').trigger('submit')
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('RemindMeButton as "follow organiser"', () => {
  it('follows the canal right away, without asking for a confirmation', async () => {
    const wrapper = await openAndSubmit({ canalId: 9, eventName: 'Zbor' })

    expect(wrapper.text()).toContain(t('public.follow.doneTitle'))
    expect(canalSubscriptionTicket).toHaveBeenCalledWith(9)
    expect(subscribeToCanal).toHaveBeenCalledWith(9, expect.objectContaining({ email: 'fan@example.com', ticket: 'canal-ticket' }))
    expect(subscribeToEvent).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('keeps the event reminder working as before', async () => {
    const wrapper = await openAndSubmit({ eventId: 5 })

    expect(subscriptionTicket).toHaveBeenCalledWith(5)
    expect(subscribeToEvent).toHaveBeenCalledWith(5, expect.objectContaining({ ticket: 'event-ticket' }))
    expect(wrapper.text()).toContain(t('public.remind.doneTitle'))
    wrapper.unmount()
  })
})
