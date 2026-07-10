<template>
  <div class="html-editor rounded-xl border border-slate-200 bg-white focus-within:border-blue-400 focus-within:ring-1 focus-within:ring-blue-300">
    <template v-if="editor">
    <!-- Toolbar -->
    <div class="flex flex-wrap items-center gap-0.5 border-b border-slate-200 p-1.5">
      <ToolBtn title="Tučné (Ctrl+B)" :active="editor.isActive('bold')" @click="editor.chain().focus().toggleBold().run()">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/><path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/></svg>
      </ToolBtn>
      <ToolBtn title="Kurzíva (Ctrl+I)" :active="editor.isActive('italic')" @click="editor.chain().focus().toggleItalic().run()">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>
      </ToolBtn>
      <ToolBtn title="Podčiarknuté (Ctrl+U)" :active="editor.isActive('underline')" @click="editor.chain().focus().toggleUnderline().run()">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4v6a6 6 0 0 0 12 0V4"/><line x1="4" y1="20" x2="20" y2="20"/></svg>
      </ToolBtn>

      <div class="mx-1 h-5 w-px bg-slate-200" />

      <ToolBtn title="Nadpis H2" :active="editor.isActive('heading', { level: 2 })" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">
        <span class="text-xs font-bold">H2</span>
      </ToolBtn>
      <ToolBtn title="Nadpis H3" :active="editor.isActive('heading', { level: 3 })" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">
        <span class="text-xs font-bold">H3</span>
      </ToolBtn>

      <div class="mx-1 h-5 w-px bg-slate-200" />

      <ToolBtn title="Zoznam s odrážkami" :active="editor.isActive('bulletList')" @click="editor.chain().focus().toggleBulletList().run()">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1" fill="currentColor"/><circle cx="4" cy="12" r="1" fill="currentColor"/><circle cx="4" cy="18" r="1" fill="currentColor"/></svg>
      </ToolBtn>
      <ToolBtn title="Číslovaný zoznam" :active="editor.isActive('orderedList')" @click="editor.chain().focus().toggleOrderedList().run()">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4"/><path d="M4 10H6"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
      </ToolBtn>

      <div class="mx-1 h-5 w-px bg-slate-200" />

      <ToolBtn title="Odkaz" :active="editor.isActive('link')" @click="setLink">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
      </ToolBtn>
      <ToolBtn v-if="editor.isActive('link')" title="Odstrániť odkaz" @click="editor.chain().focus().unsetLink().run()">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.84 12.25l1.72-1.71h-.02a5.004 5.004 0 0 0-.12-7.07 5.006 5.006 0 0 0-6.95 0l-1.72 1.71"/><path d="M5.17 11.75l-1.71 1.71a5.004 5.004 0 0 0 .12 7.07 5.006 5.006 0 0 0 6.95 0l1.71-1.71"/><line x1="8" y1="2" x2="8" y2="5"/><line x1="2" y1="8" x2="5" y2="8"/><line x1="16" y1="19" x2="16" y2="22"/><line x1="19" y1="16" x2="22" y2="16"/></svg>
      </ToolBtn>

      <div class="mx-1 h-5 w-px bg-slate-200" />

      <ToolBtn title="Citácia" :active="editor.isActive('blockquote')" @click="editor.chain().focus().toggleBlockquote().run()">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/></svg>
      </ToolBtn>
      <ToolBtn title="Horizontálna čiara" @click="editor.chain().focus().setHorizontalRule().run()">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </ToolBtn>

      <div class="mx-1 h-5 w-px bg-slate-200" />

      <ToolBtn title="Späť (Ctrl+Z)" :disabled="!editor.can().undo()" @click="editor.chain().focus().undo().run()">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
      </ToolBtn>
      <ToolBtn title="Vpred (Ctrl+Y)" :disabled="!editor.can().redo()" @click="editor.chain().focus().redo().run()">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 14 20 9 15 4"/><path d="M4 20v-7a4 4 0 0 1 4-4h12"/></svg>
      </ToolBtn>
    </div>

    <EditorContent :editor="editor" class="html-editor-content" />
    </template>
  </div>
</template>

<script setup lang="ts">
import { watch, onBeforeUnmount, defineComponent, h } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Placeholder from '@tiptap/extension-placeholder'

const ToolBtn = defineComponent({
  props: { active: Boolean, disabled: Boolean, title: String },
  emits: ['click'],
  setup(props, { slots, emit }) {
    return () => h('button', {
      type: 'button',
      title: props.title,
      disabled: props.disabled,
      onClick: (e: MouseEvent) => { e.preventDefault(); emit('click') },
      class: [
        'flex h-7 w-7 items-center justify-center rounded-md text-slate-600 transition-colors',
        props.active ? 'bg-slate-200 text-slate-900' : 'hover:bg-slate-100',
        props.disabled ? 'opacity-35 cursor-default' : 'cursor-pointer',
      ],
    }, slots.default?.())
  },
})

const props = withDefaults(defineProps<{
  modelValue: string
  placeholder?: string
  minHeight?: string
}>(), {
  placeholder: 'Napíšte popis…',
  minHeight: '180px',
})

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit.configure({ link: { openOnClick: false } }),
    Placeholder.configure({ placeholder: props.placeholder }),
  ],
  editorProps: {
    attributes: { class: 'prose prose-slate prose-sm max-w-none focus:outline-none' },
  },
  onUpdate({ editor }) {
    const html = editor.getHTML()
    emit('update:modelValue', html === '<p></p>' ? '' : html)
  },
})

watch(() => props.modelValue, (val) => {
  if (!editor.value) return
  if (editor.value.getHTML() !== val) {
    editor.value.commands.setContent(val || '', { emitUpdate: false })
  }
})

function setLink() {
  const prev = editor.value?.getAttributes('link').href ?? ''
  const url = window.prompt('URL odkazu', prev)
  if (url === null) return
  if (url === '') {
    editor.value?.chain().focus().unsetLink().run()
  } else {
    editor.value?.chain().focus().setLink({ href: url, target: '_blank' }).run()
  }
}

onBeforeUnmount(() => editor.value?.destroy())
</script>

<style>
.html-editor-content .tiptap {
  min-height: v-bind(minHeight);
  padding: 0.75rem 1rem;
  outline: none;
}

.html-editor-content .tiptap p.is-editor-empty:first-child::before {
  color: #94a3b8;
  content: attr(data-placeholder);
  float: left;
  height: 0;
  pointer-events: none;
}

.html-editor-content .tiptap h2 { font-size: 1.25rem; font-weight: 700; margin: 1rem 0 0.5rem; }
.html-editor-content .tiptap h3 { font-size: 1.1rem; font-weight: 600; margin: 0.75rem 0 0.4rem; }
.html-editor-content .tiptap p  { margin: 0.4rem 0; }
.html-editor-content .tiptap ul { list-style: disc; padding-left: 1.5rem; margin: 0.4rem 0; }
.html-editor-content .tiptap ol { list-style: decimal; padding-left: 1.5rem; margin: 0.4rem 0; }
.html-editor-content .tiptap li { margin: 0.15rem 0; }
.html-editor-content .tiptap a  { color: #2563eb; text-decoration: underline; }
.html-editor-content .tiptap blockquote { border-left: 3px solid #cbd5e1; padding-left: 0.75rem; color: #64748b; margin: 0.5rem 0; }
.html-editor-content .tiptap hr  { border: none; border-top: 1px solid #e2e8f0; margin: 0.75rem 0; }
.html-editor-content .tiptap strong { font-weight: 700; }
.html-editor-content .tiptap em { font-style: italic; }
.html-editor-content .tiptap u  { text-decoration: underline; }
</style>
