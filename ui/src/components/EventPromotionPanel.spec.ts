import { describe, expect, it } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import EventPromotionPanel from './EventPromotionPanel.vue'
import { t } from '@/i18n'
import type { EventItem } from '@/types'

function mountPanel(overrides: Partial<EventItem> = {}) {
  const event = {
    id: 42,
    name: 'Koncert v parku',
    slug: 'koncert-v-parku',
    dateRangeLabel: 'so 5. 9. 2026, 16:00',
    reservable: false,
    locationName: null,
    venue: { name: 'Amfiteáter' },
    municipality: { id: 1, name: 'Žilina' },
    ...overrides,
  } as unknown as EventItem

  return shallowMount(EventPromotionPanel, { props: { event } })
}

describe('EventPromotionPanel', () => {
  it('sends the canonical link in messages and the short link in the QR code', () => {
    const wrapper = mountPanel()
    const origin = window.location.origin

    const message = (wrapper.find('textarea').element as HTMLTextAreaElement).value
    expect(message).toContain('Koncert v parku')
    expect(message).toContain('Amfiteáter, Žilina')
    expect(message).toContain(`${t('events.promotion.moreInfo')} ${origin}/akcie/42/koncert-v-parku`)

    expect(wrapper.text()).toContain(`${origin}/akcie/42`)
    expect(wrapper.text()).toContain(t('events.promotion.qrLeadsToPage'))

    const img = wrapper.find('img')
    expect(img.attributes('src')).toContain('/events/42/qr.png?size=336')
    const downloads = wrapper.findAll('a[download]').map(a => a.attributes('href'))
    expect(downloads).toEqual([
      expect.stringContaining('/events/42/qr.png?size=1200&download=1'),
      expect.stringContaining('/events/42/qr.svg?download=1'),
    ])
  })

  it('points both the message and the QR code to registration when the event is reservable', () => {
    const wrapper = mountPanel({ reservable: true })
    const origin = window.location.origin

    const message = (wrapper.find('textarea').element as HTMLTextAreaElement).value
    expect(message).toContain(`${t('events.promotion.signupHere')} ${origin}/akcie/42/koncert-v-parku#registracia`)
    expect(wrapper.text()).toContain(`${origin}/akcie/42#registracia`)
    expect(wrapper.text()).toContain(t('events.promotion.qrLeadsToSignup'))
  })
})

describe('EventPromotionPanel after publishing', () => {
  it('greets the organizer right after publishing and only then', () => {
    expect(mountPanel().find('[role="status"]').exists()).toBe(false)

    const greeted = shallowMount(EventPromotionPanel, {
      props: { event: { id: 42, name: 'X', slug: 'x', reservable: false } as unknown as EventItem, justPublished: true },
    })
    expect(greeted.find('[role="status"]').text()).toContain(t('events.promotion.published'))
  })
})
