import { describe, expect, it, vi } from 'vitest'
import { defineComponent, ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter, useRoute } from 'vue-router'
import { createPinia } from 'pinia'
import AdminLayout from './AdminLayout.vue'
import DashboardLayout from './DashboardLayout.vue'

vi.mock('@/api/index', () => ({ default: { get: vi.fn().mockResolvedValue({ data: {} }) } }))

// Simulate forms that capture their record and draft on mount.
const RecordPage = defineComponent({
  setup() {
    const record = ref(useRoute().params.id)
    const draft = ref('')
    return { record, draft }
  },
  template: '<section data-testid="record"><h1>Record {{ record }}</h1><input v-model="draft" /></section>',
})

for (const [scope, layout] of [['admin', AdminLayout], ['dashboard', DashboardLayout]] as const) {
  describe(`${scope} record navigation`, () => {
    it('loads a fresh record on path changes and preserves drafts on query changes', async () => {
      const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: `/${scope}`, component: layout, children: [{ path: 'events/:id', component: RecordPage }] }, { path: '/:pathMatch(.*)*', component: { template: '<div />' } }],
      })
      await router.push(`/${scope}/events/1`)
      const wrapper = mount({ template: '<RouterView />' }, {
        global: { plugins: [router, createPinia()], stubs: { UserDropdown: true, NotificationBell: true, MunicipalityAside: true, LangSwitcher: true } },
      })
      await flushPromises()
      await wrapper.get('[data-testid="record"] input').setValue('Unsaved draft')
      await router.push(`/${scope}/events/1?tab=details`)
      await flushPromises()
      expect((wrapper.get('input').element as HTMLInputElement).value).toBe('Unsaved draft')
      await router.push(`/${scope}/events/2`)
      await flushPromises()
      expect(wrapper.get('h1').text()).toBe('Record 2')
      expect((wrapper.get('input').element as HTMLInputElement).value).toBe('')
      wrapper.unmount()
    })
  })
}
