import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createPinia } from 'pinia'
import { setLocale, t } from '@/i18n'
import EventEditPage from './EventEditPage.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'

const { get, post, put } = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn() }))
vi.mock('@/api/index', () => ({ default: { get, post, put } }))

let organizers: { id: number; name: string }[]
beforeEach(() => {
  vi.resetAllMocks()
  setLocale('sk')
  organizers = [{ id: 7, name: 'Kultúrne centrum' }]
  Element.prototype.scrollIntoView = vi.fn()
  get.mockImplementation((url: string) => Promise.resolve({ data: { data: url.endsWith('/canals') ? organizers : [] } }))
  post.mockResolvedValue({ data: { id: 99, name: 'Koncert', status: 'draft' } })
})

async function openForm(scope: 'dashboard' | 'admin' = 'dashboard') {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }] })
  await router.push(`/${scope}/events/create`)
  const wrapper = mount(EventEditPage, {
    props: { scope },
    global: {
      plugins: [router, createPinia()],
      stubs: {
        HtmlEditor: { props: ['modelValue'], template: '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
        AiAssistPanel: true, ImagePicker: { data: () => ({ files: [] }), template: '<div />' }, ImageManager: true,
      },
    },
  })
  await flushPromises()
  return wrapper
}
async function click(wrapper: VueWrapper, label: string) {
  const button = wrapper.findAll('button').find(b => b.text() === label && b.isVisible())
  expect(button, label).toBeTruthy()
  await button!.trigger('click')
  await flushPromises()
}
async function name(wrapper: VueWrapper) {
  const field = wrapper.findAllComponents({ name: 'FormField' }).find(f => f.props('label') === t('events.fields.name'))!
  await field.find('input').setValue('Koncert')
}

describe('guided event creation', () => {
  it('keeps entered content when moving between steps and hides advanced choices', async () => {
    const w = await openForm()
    await name(w)
    await w.find('textarea').setValue('Večerný koncert')
    await click(w, t('eventJourney.next'))
    expect(w.find('textarea').isVisible()).toBe(false)
    expect(w.text()).toContain('Kultúrne centrum')
    expect(w.findAllComponents({ name: 'FormField' }).some(f => f.props('label') === t('eventJourney.publishAs'))).toBe(false)
    await click(w, t('eventJourney.back'))
    expect((w.find('textarea').element as HTMLTextAreaElement).value).toBe('Večerný koncert')
    await click(w, t('eventJourney.next'))
    await click(w, t('eventJourney.next'))
    expect(w.get('details').attributes('open')).toBeUndefined()
    expect(w.text()).toContain('Pred zverejnením doplňte:')
    expect(post).not.toHaveBeenCalled()
    w.unmount()
  })

  it('requires a name but saves a draft without date or place', async () => {
    const w = await openForm()
    await click(w, t('eventJourney.next'))
    expect(w.get('[role="alert"]').text()).toContain(t('eventJourney.nameRequired'))
    await name(w)
    await click(w, t('eventJourney.saveDraft'))
    expect(post).toHaveBeenCalledWith('/dashboard/events', expect.objectContaining({ name: 'Koncert', canal_id: 7, status: 'draft', venue_id: null }))
    w.unmount()
  })

  it('returns to the date step when the server rejects a date', async () => {
    const w = await openForm()
    await name(w)
    await click(w, t('eventJourney.next'))
    await click(w, t('eventJourney.next'))
    post.mockRejectedValueOnce({ response: { data: { message: 'Opravte dátum', errors: { start_at: ['Neplatný dátum'] } } } })
    await click(w, t('eventJourney.saveDraft'))
    expect(w.find('textarea').isVisible()).toBe(true)
    expect(w.text()).toContain('Neplatný dátum')
    w.unmount()
  })

  it('shows a message specific to the end field when it precedes a past start', async () => {
    const w = await openForm()
    await name(w)
    const field = (label: string) => w.findAllComponents({ name: 'FormField' }).find(f => f.props('label') === label)!
    field(t('events.fields.startAt')).vm.$emit('update:modelValue', '2020-05-01T18:00')
    field(t('events.fields.endAt')).vm.$emit('update:modelValue', '2020-05-01T16:00')
    await flushPromises()
    await click(w, t('eventJourney.next'))
    expect(field(t('events.fields.startAt')).props('error')).toBe(t('eventJourney.startInPast'))
    expect(field(t('events.fields.endAt')).props('error')).toBe(t('eventJourney.endBeforeStart'))
    w.unmount()
  })

  it('shows the chosen organizer in the review and links back to fill in a missing place', async () => {
    const w = await openForm()
    await name(w)
    await click(w, t('eventJourney.next'))
    await click(w, t('eventJourney.next'))
    const preview = w.get('section[aria-live="polite"]')
    expect(preview.text()).toContain('Kultúrne centrum')
    expect(preview.text()).not.toContain(t('eventJourney.organizer') + ': ' + t('eventJourney.missing'))
    await click(w, t('eventJourney.fillInPlace'))
    expect(w.text()).toContain(t('eventJourney.venueRequiredHint'))
    w.unmount()
  })

  it('creates the first organizer inline without losing the event', async () => {
    organizers = []
    const w = await openForm()
    await name(w)
    await click(w, t('eventJourney.next'))
    const field = w.findAllComponents({ name: 'FormField' }).find(f => f.props('label') === t('eventJourney.organizerName'))!
    await field.find('input').setValue('Nový organizátor')
    w.findComponent(SearchableSelect).vm.$emit('update:modelValue', 12)
    post.mockResolvedValueOnce({ data: { id: 8, name: 'Nový organizátor' } })
    await click(w, t('eventJourney.addOrganizer'))
    expect(post).toHaveBeenCalledWith('/dashboard/canals', expect.objectContaining({ name: 'Nový organizátor', municipality_id: 12 }))
    await click(w, t('eventJourney.saveDraft'))
    expect(post).toHaveBeenCalledWith('/dashboard/events', expect.objectContaining({ name: 'Koncert', canal_id: 8 }))
    w.unmount()
  })


  it.each([false, true])('publishes or schedules the reviewed event (scheduled: %s)', async (scheduled) => {
    const w = await openForm()
    await name(w)
    const start = w.findAllComponents({ name: 'FormField' }).find(f => f.props('label') === t('events.fields.startAt'))!
    start.vm.$emit('update:modelValue', '2030-10-12T18:00')
    await click(w, t('eventJourney.next'))
    const venue = w.findAllComponents({ name: 'FormField' }).find(f => f.props('label') === t('events.fields.venue'))!
    venue.vm.$emit('update:modelValue', 4)
    await click(w, t('eventJourney.next'))
    if (scheduled) {
      await w.findAll('input[type="checkbox"]')[0]!.setValue(true)
      const publishAt = w.findAllComponents({ name: 'FormField' }).find(f => f.props('label') === t('events.fields.publishAt'))!
      publishAt.vm.$emit('update:modelValue', '2030-10-01T10:00')
    }
    await w.get('form').trigger('submit')
    await flushPromises()
    expect(post).toHaveBeenCalledWith('/dashboard/events', expect.objectContaining({
      canal_id: 7, venue_id: 4, start_at: '2030-10-12T18:00', status: scheduled ? 'scheduled' : 'published',
      publish_at: scheduled ? '2030-10-01T10:00' : null,
    }))
    w.unmount()
  })

  it('shows the server message and offers to publish the draft venue too', async () => {
    const w = await openForm()
    await name(w)
    const start = w.findAllComponents({ name: 'FormField' }).find(f => f.props('label') === t('events.fields.startAt'))!
    start.vm.$emit('update:modelValue', '2030-10-12T18:00')
    await click(w, t('eventJourney.next'))
    const venue = w.findAllComponents({ name: 'FormField' }).find(f => f.props('label') === t('events.fields.venue'))!
    venue.vm.$emit('update:modelValue', 4)
    await click(w, t('eventJourney.next'))
    post.mockRejectedValueOnce({ response: { status: 422, data: {
      message: 'Podujatie sa nedá publikovať, kým nie je publikované aj miesto „Dom“.',
      code: 'dependencies_not_published',
      dependencies: [{ type: 'venue', id: 4, name: 'Dom', status: 'draft', label: 'miesto „Dom“' }],
    } } })
    await w.get('form').trigger('submit')
    await flushPromises()
    expect(w.text()).toContain('kým nie je publikované aj miesto')
    post.mockClear()
    await click(w, t('events.publish.withVenue'))
    expect(post).toHaveBeenCalledWith('/dashboard/events', expect.objectContaining({ venue_id: 4, publish_dependencies: true, status: 'published' }))
    w.unmount()
  })

  it('does not guess the organizer when several are available', async () => {
    organizers.push({ id: 8, name: 'Druhý organizátor' })
    const w = await openForm()
    await name(w)
    await click(w, t('eventJourney.saveDraft'))
    expect(post).not.toHaveBeenCalled()
    expect(w.get('[role="alert"]').text()).toBe(t('eventJourney.chooseOrganizer'))
    expect(w.findAllComponents({ name: 'FormField' }).find(f => f.props('label') === t('eventJourney.publishAs'))!.isVisible()).toBe(true)
    w.unmount()
  })

  it('keeps the full editor for admin creation', async () => {
    const w = await openForm('admin')
    expect(w.find('[aria-current="step"]').exists()).toBe(false)
    expect(w.find('select').exists()).toBe(true)
    w.unmount()
  })
})
