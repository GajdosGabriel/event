<template>
  <!-- Výber obsahových štítkov, zoskupený podľa facetu.
       Číselník má ~40 položiek, takže prepínateľné čipy sú prehľadnejšie než
       multi-select s vyhľadávaním. -->
  <div>
    <p class="mb-2 text-xs text-slate-500">{{ hint }}</p>

    <p v-if="loading" class="text-sm text-slate-500">Načítavam štítky…</p>
    <p v-else-if="!groups.length" class="text-sm text-slate-500">Číselník štítkov je prázdny.</p>

    <div v-else class="space-y-3">
      <div v-for="group in groups" :key="group.group">
        <p class="mb-1.5 text-xs font-medium text-slate-400 uppercase">{{ group.label }}</p>
        <div class="flex flex-wrap gap-1.5">
          <button
            v-for="tag in group.tags"
            :key="tag.id"
            type="button"
            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs transition-colors"
            :class="isSelected(tag.id)
              ? 'bg-violet-600 font-medium text-white'
              : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            @click="toggle(tag.id)"
          >
            <span v-if="tag.emoji">{{ tag.emoji }}</span>
            {{ tag.name }}
          </button>
        </div>
      </div>
    </div>

    <!-- Ktoré štítky priradila AI a ktoré sa odvodili z termínu/ceny — aby
         organizátor vedel, čo prepisuje. -->
    <p v-if="automatedNote" class="mt-3 text-xs text-slate-400">{{ automatedNote }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { indexTags } from '@/api/tags'
import type { TagGroupItem } from '@/types'

const props = defineProps<{
  modelValue: number[]
  /** Štítky, ktoré na podujatí už sú od AI alebo z odvodenia. */
  automated?: { name: string }[] | null
}>()

const emit = defineEmits<{ 'update:modelValue': [number[]] }>()

const groups = ref<TagGroupItem[]>([])
const loading = ref(true)

const hint = 'Vyberte, čo podujatie vystihuje. Štítky pomáhajú návštevníkom nájsť podujatie vo filtri.'

const automatedNote = computed(() => {
  const names = (props.automated ?? []).map((tag) => tag.name)
  return names.length ? `Automaticky priradené: ${names.join(', ')}.` : ''
})

function isSelected(id: number) {
  return props.modelValue.includes(id)
}

function toggle(id: number) {
  emit(
    'update:modelValue',
    isSelected(id) ? props.modelValue.filter((current) => current !== id) : [...props.modelValue, id],
  )
}

onMounted(async () => {
  try {
    groups.value = await indexTags()
  } catch {
    groups.value = []
  } finally {
    loading.value = false
  }
})
</script>
