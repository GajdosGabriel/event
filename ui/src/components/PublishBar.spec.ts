import { describe, it, expect, beforeAll } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import { setLocale, t } from '@/i18n'
import { savedMessage } from '@/utils/publishFlow'
import PublishBar from './PublishBar.vue'

beforeAll(() => setLocale('sk'))

function bar(props: { savedStatus: string | null } & Record<string, unknown>) {
  return mount(PublishBar, {
    props: { modelValue: 'draft', saving: false, cancelTo: '/x', ...props } as InstanceType<typeof PublishBar>['$props'],
    global: { stubs: { RouterLink: RouterLinkStub } },
  })
}

const labels = (w: ReturnType<typeof bar>) => w.findAll('button').map(b => b.text())

describe('PublishBar', () => {
  it('ukáže koncept a ponúkne zverejnenie ako hlavnú voľbu', async () => {
    const w = bar({ savedStatus: 'draft' })
    expect(w.text()).toContain(t('publishBar.state.draft.title'))
    expect(labels(w)).toEqual([t('publishBar.saveDraft'), t('publishBar.publish')])

    await w.findAll('button')[1]!.trigger('click')
    expect(w.emitted('update:modelValue')!.slice(-1)[0]).toEqual(['published'])
  })

  it('pri plánovaní nastaví stav scheduled', async () => {
    const w = bar({ savedStatus: null, scheduleLater: true })
    expect(labels(w)).toEqual([t('publishBar.saveDraft'), t('publishBar.schedule')])
    await w.findAll('button')[1]!.trigger('click')
    expect(w.emitted('update:modelValue')!.slice(-1)[0]).toEqual(['scheduled'])
  })

  it('zverejnené sa dá zrušiť — vráti sa do konceptu', async () => {
    const w = bar({ savedStatus: 'published', modelValue: 'published' })
    expect(labels(w)).toEqual([t('publishBar.saveChanges'), t('publishBar.unpublish')])
    await w.findAll('button')[1]!.trigger('click')
    expect(w.emitted('update:modelValue')!.slice(-1)[0]).toEqual(['draft'])
  })

  it('naplánované sa dá zrušiť', async () => {
    const w = bar({ savedStatus: 'scheduled', modelValue: 'scheduled', scheduleLater: true })
    expect(labels(w)).toEqual([t('publishBar.saveChanges'), t('publishBar.cancelSchedule')])
    await w.findAll('button')[1]!.trigger('click')
    expect(w.emitted('update:modelValue')!.slice(-1)[0]).toEqual(['draft'])
  })

  it('zablokované zrušenie neodošle formulár, len povie prečo', async () => {
    const w = bar({ savedStatus: 'published', modelValue: 'published', blockedReason: 'Odkazuje naň podujatie.' })
    const unpublish = w.findAll('button').find(b => b.text() === t('publishBar.unpublish'))!
    expect(unpublish.attributes('type')).toBe('button')
    await unpublish.trigger('click')
    expect(w.emitted('update:modelValue')).toBeUndefined()
  })
})

describe('savedMessage', () => {
  it('rozlíši koncept, zverejnenie a bežné uloženie', () => {
    expect(savedMessage(null, 'draft')).toBe(t('publishBar.toast.draft'))
    expect(savedMessage('draft', 'published')).toBe(t('publishBar.toast.published'))
    expect(savedMessage('published', 'published')).toBe(t('publishBar.toast.saved'))
    expect(savedMessage('published', 'draft')).toBe(t('publishBar.toast.unpublished'))
    expect(savedMessage('scheduled', 'draft')).toBe(t('publishBar.toast.unscheduled'))
    expect(savedMessage('draft', 'scheduled')).toBe(t('publishBar.toast.scheduled'))
  })
})
