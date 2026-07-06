<template>
  <div class="mx-auto my-5 w-full max-w-3xl px-4 grid gap-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Nástroje</h1>
      <p class="text-sm text-slate-500 mt-1">Spúšťanie konzolových príkazov a importov.</p>
    </div>

    <!-- Import eventov z externých zdrojov -->
    <div class="panel-card grid gap-3">
      <div>
        <h2 class="font-semibold text-slate-900">Import eventov z externých zdrojov</h2>
        <p class="text-sm text-slate-500">Spustí <code class="text-xs bg-slate-100 px-1 rounded">app:import-event-sources</code> — stiahne eventy z nakonfigurovaných URL.</p>
      </div>
      <div class="grid gap-2">
        <label class="form-label text-sm">
          URL zdrojov (voliteľné — prázdne = použijú sa zo config)
          <textarea v-model="importUrls" class="form-textarea text-sm" rows="3" placeholder="https://example.com/events&#10;https://other.com/list" />
        </label>
        <div class="flex flex-wrap gap-3">
          <label class="form-label text-sm flex-1 min-w-32">
            Strán max
            <input v-model.number="importPages" type="number" min="1" max="20" class="form-input" />
          </label>
          <label class="form-label text-sm flex-1 min-w-32">
            Limit detailov (0 = bez limitu)
            <input v-model.number="importLimit" type="number" min="0" max="100" class="form-input" />
          </label>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
          <input type="checkbox" v-model="importForce" class="accent-blue-600" />
          Vynútiť refresh aj u už kompletných eventov (ignoruje skip)
        </label>
      </div>
      <ToolRunButton label="Spustiť import" :running="running === 'import'" @run="runTool('import')" />
      <ToolOutput :output="outputs['import']" />
    </div>

    <!-- AI Detector -->
    <div class="panel-card grid gap-3">
      <div>
        <h2 class="font-semibold text-slate-900">AI Detektor (jeden event)</h2>
        <p class="text-sm text-slate-500">Spustí <code class="text-xs bg-slate-100 px-1 rounded">app:ai-detector</code> — spracuje jeden event s <code class="text-xs bg-slate-100 px-1 rounded">original_source</code> URL pomocou AI.</p>
      </div>
      <ToolRunButton label="Spustiť AI detektor" :running="running === 'ai-detector'" @run="runTool('ai-detector')" />
      <ToolOutput :output="outputs['ai-detector']" />
    </div>

    <!-- Archivovanie -->
    <div class="panel-card grid gap-3">
      <div>
        <h2 class="font-semibold text-slate-900">Archivácia ukončených eventov</h2>
        <p class="text-sm text-slate-500">Spustí <code class="text-xs bg-slate-100 px-1 rounded">app:events-archive-finished</code> — nastaví status na archived pre eventy po skončení.</p>
      </div>
      <ToolRunButton label="Archivovať" :running="running === 'archive'" @run="runTool('archive')" />
      <ToolOutput :output="outputs['archive']" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import { runAdminTool, startEventImport, getEventImportStatus } from '@/api/events'
import { useToast } from '@/composables/useToast'

const toast = useToast()

const importUrls = ref('')
const importPages = ref(1)
const importLimit = ref(0)
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
  outputs.value['import'] = 'Import spustený na pozadí, čakám na výsledok…'

  // Poll the run status until it finishes. The job runs on the queue, so this
  // requires an active worker (php artisan queue:work database --queue=imports).
  for (;;) {
    await sleep(2000)
    const run = await getEventImportStatus(run_id)
    if (run.status === 'done') {
      outputs.value['import'] = run.output || '(bez výstupu)'
      toast.success('Import dokončený.')
      return
    }
    if (run.status === 'failed') {
      outputs.value['import'] = run.output || 'Import zlyhal.'
      toast.error('Import zlyhal.')
      return
    }
    outputs.value['import'] = run.status === 'running'
      ? 'Import prebieha na pozadí…'
      : 'Import čaká v poradí (queue worker)…'
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
      outputs.value[tool] = res.output || '(bez výstupu)'
      toast.success('Príkaz dokončený.')
    }
  } catch {
    outputs.value[tool] = 'Chyba pri spúšťaní príkazu.'
    toast.error('Príkaz zlyhal.')
  } finally {
    running.value = null
  }
}

onBeforeUnmount(() => clearTimeout(pollTimer))
</script>

<script lang="ts">
import { defineComponent, h } from 'vue'

const ToolRunButton = defineComponent({
  props: { label: String, running: Boolean },
  emits: ['run'],
  setup(props, { emit }) {
    return () => h('button', {
      type: 'button',
      class: `btn btn-primary w-fit ${props.running ? 'opacity-60 cursor-not-allowed' : ''}`,
      disabled: props.running,
      onClick: () => emit('run'),
    }, props.running ? 'Prebieha…' : props.label)
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
