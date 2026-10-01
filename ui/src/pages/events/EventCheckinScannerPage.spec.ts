import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { setLocale, t } from '@/i18n'
import EventCheckinScannerPage from './EventCheckinScannerPage.vue'

const { checkinTicket, checkinStats, showEvent, indexTicketTypes, pending } = vi.hoisted(() => ({
  checkinTicket: vi.fn(), checkinStats: vi.fn(), showEvent: vi.fn(), indexTicketTypes: vi.fn(), pending: vi.fn(),
}))

vi.mock('@/api/tickets', () => ({ checkinTicket, checkinStats }))
vi.mock('@/api/events', () => ({ showEvent }))
vi.mock('@/api/ticketTypes', () => ({ indexTicketTypes }))
vi.mock('@/utils/checkinQueue', () => ({
  enqueue: vi.fn(), pending, remove: vi.fn(), isSupported: () => false,
}))
vi.mock('qr-scanner/qr-scanner-worker.min.js?url', () => ({ default: '' }))
vi.mock('qr-scanner', () => ({
  default: class { static WORKER_PATH = ''; start = vi.fn(); stop = vi.fn(); destroy = vi.fn() },
}))

async function openPage() {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }] })
  await router.push('/dashboard/events/5/checkin')
  const wrapper = mount(EventCheckinScannerPage, {
    global: { plugins: [router], stubs: { EventTicketsTabs: true, CanalTeamPanel: true } },
  })
  await flushPromises()
  return wrapper
}

async function submit(wrapper: Awaited<ReturnType<typeof openPage>>, code: string) {
  await wrapper.find('input[type="text"]').setValue(code)
  await wrapper.find('form').trigger('submit')
  await flushPromises()
}

beforeEach(() => {
  vi.resetAllMocks()
  setLocale('sk')
  vi.useFakeTimers()
  showEvent.mockResolvedValue({ name: 'Koncert', canalId: null })
  indexTicketTypes.mockResolvedValue([{ id: 1 }])
  pending.mockResolvedValue([])
  checkinStats.mockResolvedValue({ arrived: 0, total: 2, remaining: 2 })
})

afterEach(() => vi.useRealTimers())

describe('check-in scanner', () => {
  it('asks for a code instead of silently ignoring an empty submit', async () => {
    const w = await openPage()
    await w.find('form').trigger('submit')
    await flushPromises()

    expect(w.get('[role="alert"]').text()).toBe(t('checkin.emptyCode'))
    expect(checkinTicket).not.toHaveBeenCalled()
    w.unmount()
  })

  it('shows a warning, not the green confirmation, for an already used ticket', async () => {
    checkinTicket
      .mockResolvedValueOnce({ status: 'checked_in', reason: null, admission: { attendeeName: 'Jana', checkedInAt: '2026-10-01T10:00:00Z' } })
      .mockResolvedValueOnce({ status: 'already_checked_in', reason: null, admission: { attendeeName: 'Jana', checkedInAt: '2026-10-01T10:00:00Z' } })

    const w = await openPage()
    await submit(w, 'abc')
    expect(w.text()).toContain(t('checkin.ok'))
    expect(checkinStats).toHaveBeenCalledTimes(2) // načítanie + po prvom vstupe

    // Skener po každom skene na chvíľu zamkne vstup (processing) — počkáme na odomknutie.
    await vi.advanceTimersByTimeAsync(1600)
    await submit(w, 'abc')
    expect(w.text()).not.toContain(t('checkin.ok'))

    const box = w.find('.bg-amber-50')
    expect(box.exists()).toBe(true)
    expect(box.classes()).not.toContain('bg-green-50')
    // Druhý sken počet „Prišlo" nenačítava znova.
    expect(checkinStats).toHaveBeenCalledTimes(2)
    w.unmount()
  })
})
