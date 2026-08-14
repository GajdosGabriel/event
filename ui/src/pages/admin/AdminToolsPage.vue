<template>
  <div class="mx-auto my-5 w-full max-w-3xl px-4 grid gap-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">{{ t('admin.tools.title') }}</h1>
      <p class="text-sm text-slate-500 mt-1">{{ t('admin.tools.lead') }}</p>
    </div>

    <!-- Import eventov z externých zdrojov -->
    <div class="panel-card grid gap-3">
      <div>
        <h2 class="font-semibold text-slate-900">{{ t('admin.tools.importTitle') }}</h2>
        <p class="text-sm text-slate-500">
          {{ t('admin.tools.run') }} <code class="text-xs bg-slate-100 px-1 rounded">app:import-event-sources</code> {{ t('admin.tools.importLead') }}
        </p>
      </div>
      <div class="grid gap-2">
        <FormField
          v-model="importUrls"
          type="textarea"
          :label="t('admin.tools.importUrls')"
          rows="3"
          placeholder="https://example.com/events&#10;https://other.com/list"
          class="text-sm"
        />
        <div class="flex flex-wrap gap-3">
          <FormField v-model="importPages" type="number" :label="t('admin.tools.importPages')" min="1" max="20" class="text-sm flex-1 min-w-32" />
          <FormField v-model="importLimit" type="number" :label="t('admin.tools.importLimit')" min="0" max="100" class="text-sm flex-1 min-w-32" />
        </div>
        <FormField v-model="importForce" type="checkbox" :label="t('admin.tools.importForce')" />
      </div>
      <ToolRunButton :label="t('admin.tools.importRun')" :running="running === 'import'" @run="runTool('import')" />
      <ToolOutput :output="outputs['import']" />
    </div>

    <!-- AI Detector -->
    <div class="panel-card grid gap-3">
      <div>
        <h2 class="font-semibold text-slate-900">{{ t('admin.tools.aiTitle') }}</h2>
        <p class="text-sm text-slate-500">
          {{ t('admin.tools.run') }} <code class="text-xs bg-slate-100 px-1 rounded">app:ai-detector</code> {{ t('admin.tools.aiLead') }}
        </p>
      </div>
      <ToolRunButton :label="t('admin.tools.aiRun')" :running="running === 'ai-detector'" @run="runTool('ai-detector')" />
      <ToolOutput :output="outputs['ai-detector']" />
    </div>

    <!-- Archivovanie -->
    <div class="panel-card grid gap-3">
      <div>
        <h2 class="font-semibold text-slate-900">{{ t('admin.tools.archiveTitle') }}</h2>
        <p class="text-sm text-slate-500">
          {{ t('admin.tools.run') }} <code class="text-xs bg-slate-100 px-1 rounded">app:events-archive-finished</code> {{ t('admin.tools.archiveLead') }}
        </p>
      </div>
      <ToolRunButton :label="t('admin.tools.archiveRun')" :running="running === 'archive'" @run="runTool('archive')" />
      <ToolOutput :output="outputs['archive']" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import { runAdminTool, startEventImport, getEventImportStatus } from '@/api/events'
import { t } from '@/i18n'
import { useToast } from '@/composables/useToast'
import FormField from '@/components/FormField.vue'

const toast = useToast()

const importUrls = ref('')
// Vymazané číselné pole je `null` — pri spustení sa doplní rozumná predvoľba.
const importPages = ref<number | null>(1)
const importLimit = ref<number | null>(0)
const importForce = ref(false)

type ToolKey = 'import' | 'ai-detector' | 'archive'
const running = ref<ToolKey | null>(null)
const outputs = ref<Record<string, string>>({})
let pollTimer: ReturnType<typeof setTimeout> | undefined

function sleep(ms: number) {
  return new Promise<void>((resolve) => { pollTimer = setTimeout(resolve, ms) })
}

async function runImport() {
  const urls = importUrls.value.trim().split('\n').map(u => u.trim()).filter(Boolean)
  const limit = Number.isFinite(importLimit.value) ? importLimit.value : 0
  const pages = Number.isFinite(importPages.value) ? importPages.value : 1

  const { run_id } = await startEventImport({ urls, pages, limit, force: importForce.value })
  outputs.value['import'] = t('admin.tools.importStarted')

  // Poll the run status until it finishes. The job runs on the queue, so this
  // requires a worker consuming the 'imports' queue — on production that is the
  // scheduled queue:work in routes/console.php, locally:
  // php artisan queue:work database --queue=default,imports
  for (;;) {
    await sleep(2000)
    const run = await getEventImportStatus(run_id)
    if (run.status === 'done') {
      outputs.value['import'] = run.output || t('admin.tools.noOutput')
      toast.success(t('admin.tools.importDone'))
      return
    }
    if (run.status === 'failed') {
      outputs.value['import'] = run.output || t('admin.tools.importFailed')
      toast.error(t('admin.tools.importFailed'))
      return
    }
    outputs.value['import'] = run.status === 'running'
      ? t('admin.tools.importRunning')
      : t('admin.tools.importQueued')
  }
}

async function runTool(tool: ToolKey) {
  running.value = tool
  outputs.value[tool] = ''
  try {
    if (tool === 'import') {
      await runImport()
    } else {
      const res = await runAdminTool(tool === 'ai-detector' ? 'ai-detector' : 'archive-events')
      outputs.value[tool] = res.output || t('admin.tools.noOutput')
      toast.success(t('admin.tools.done'))
    }
  } catch {
    outputs.value[tool] = t('admin.tools.error')
    toast.error(t('admin.tools.failed'))
  } finally {
    running.value = null
  }
}

onBeforeUnmount(() => clearTimeout(pollTimer))
</script>

<script lang="ts">
import { defineComponent, h } from 'vue'
import { t as translate } from '@/i18n'

const ToolRunButton = defineComponent({
  props: { label: String, running: Boolean },
  emits: ['run'],
  setup(props, { emit }) {
    return () => h('button', {
      type: 'button',
      class: `btn btn-primary w-fit ${props.running ? 'opacity-60 cursor-not-allowed' : ''}`,
      disabled: props.running,
      onClick: () => emit('run'),
    }, props.running ? translate('admin.tools.running') : props.label)
  },
})

const ToolOutput = defineComponent({
  props: { output: String },
  setup(props) {
    return () => props.output
      ? h('pre', { class: 'whitespace-pre-wrap rounded-lg bg-slate-900 p-3 text-xs text-green-400 overflow-x-auto' }, props.output)
      : null
  },
})

export default { components: { ToolRunButton, ToolOutput } }
</script>
