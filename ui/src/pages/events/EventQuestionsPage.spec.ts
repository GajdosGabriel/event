import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { setLocale, t } from '@/i18n'
import EventQuestionsPage from './EventQuestionsPage.vue'

const { showEvent, indexQuestionBoards, indexBoardQuestions } = vi.hoisted(() => ({
  showEvent: vi.fn(), indexQuestionBoards: vi.fn(), indexBoardQuestions: vi.fn(),
}))

vi.mock('@/api/events', () => ({ showEvent }))
vi.mock('@/api/questions', () => ({
  indexQuestionBoards, indexBoardQuestions,
  createQuestionBoard: vi.fn(), deleteQuestion: vi.fn(), moderateQuestion: vi.fn(),
  rotateQuestionBoardToken: vi.fn(), updateQuestionBoard: vi.fn(),
}))

async function openPage() {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }] })
  await router.push('/dashboard/events/5/questions')
  const wrapper = mount(EventQuestionsPage, {
    global: { plugins: [router], stubs: { EventTicketsTabs: true, SlideStudio: true } },
  })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.resetAllMocks()
  setLocale('sk')
  indexQuestionBoards.mockResolvedValue([{
    targetType: 'event', targetId: 5, title: 'Koncert',
    board: { id: 1, token: 'abc', publicUrl: 'https://x.test/q/abc', pendingCount: 0, counts: {} },
  }])
  indexBoardQuestions.mockResolvedValue({ questions: [], counts: {} })
})

describe('questions dashboard page', () => {
  it('warns that the public link does not work until the event is published', async () => {
    showEvent.mockResolvedValue({ status: 'scheduled' })
    const w = await openPage()

    expect(w.get('[role="status"]').text()).toBe(t('questions.dashboard.notPublic'))
    w.unmount()
  })

  it('shows no warning for a published event', async () => {
    showEvent.mockResolvedValue({ status: 'published' })
    const w = await openPage()

    expect(w.find('[role="status"]').exists()).toBe(false)
    w.unmount()
  })
})
