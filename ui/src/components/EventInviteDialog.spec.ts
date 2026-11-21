import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import EventInviteDialog from './EventInviteDialog.vue'
import { t } from '@/i18n'

function mountDialog() {
  return mount(EventInviteDialog, { props: { eventId: 42 }, global: { stubs: { teleport: true } } })
}

describe('EventInviteDialog', () => {
  it('downloads the format and theme the organizer picked', async () => {
    const wrapper = mountDialog()
    const download = () => wrapper.find('a[download]').attributes('href') ?? ''

    expect(download()).toContain('/events/42/invite.png?variant=square&theme=dark')
    expect(download()).toContain('download=1')

    await wrapper.findAll('button').find(b => b.text() === t('events.promotion.invite.variantPoster'))!.trigger('click')
    await wrapper.findAll('button').find(b => b.text() === t('events.promotion.invite.themeBold'))!.trigger('click')

    expect(download()).toContain('variant=poster&theme=bold')
    expect(wrapper.text()).toContain(t('events.promotion.invite.hintPoster'))
  })

  it('closes on Escape', async () => {
    const wrapper = mountDialog()
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    expect(wrapper.emitted('close')).toHaveLength(1)
    wrapper.unmount()
  })
})
