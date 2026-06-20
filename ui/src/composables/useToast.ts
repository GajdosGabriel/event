import { ref } from 'vue'

export type ToastType = 'success' | 'error' | 'info'

export interface Toast {
  id: number
  message: string
  type: ToastType
}

const toasts = ref<Toast[]>([])
let nextId = 0

export function useToast() {
  function show(message: string, type: ToastType = 'info', duration = 4000) {
    const id = ++nextId
    toasts.value.push({ id, message, type })
    if (duration > 0) {
      setTimeout(() => dismiss(id), duration)
    }
    return id
  }

  function success(message: string) { return show(message, 'success') }
  function error(message: string) { return show(message, 'error') }
  function info(message: string) { return show(message, 'info') }
  function dismiss(id: number) { toasts.value = toasts.value.filter(t => t.id !== id) }

  return { toasts, show, success, error, info, dismiss }
}
