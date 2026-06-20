<template>
  <div class="grid gap-4">
    <h1 class="text-2xl font-semibold text-slate-900">Používatelia</h1>

    <p v-if="loading" class="text-slate-600">Načítavam…</p>
    <p v-else-if="error" class="text-red-600">{{ error }}</p>

    <div v-else class="panel-card">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
            <th class="pb-2 pr-4">Meno</th>
            <th class="pb-2 pr-4">Email</th>
            <th class="pb-2 pr-4">Role</th>
            <th class="pb-2">Akcie</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id as number" class="border-b border-slate-100 last:border-0">
            <td class="py-2 pr-4">{{ user.display_name ?? user.name }}</td>
            <td class="py-2 pr-4 text-slate-600">{{ user.email }}</td>
            <td class="py-2 pr-4 text-slate-600">{{ (user.roles as string[])?.join(', ') ?? '—' }}</td>
            <td class="py-2">
              <button class="action-btn" @click="openRoleEditor(user)">Upraviť role</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Role editor modal -->
    <div v-if="editingUser" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
        <h2 class="mb-3 text-lg font-semibold">Role pre {{ editingUser.email }}</h2>
        <div class="grid gap-2">
          <label v-for="role in roles" :key="role.name" class="flex items-center gap-2 text-sm">
            <input type="checkbox" :value="role.name" v-model="selectedRoles" />
            {{ role.label ?? role.name }}
          </label>
        </div>
        <div class="mt-4 flex gap-2">
          <button class="btn btn-primary" :disabled="saving" @click="saveRoles">{{ saving ? 'Ukladám…' : 'Uložiť' }}</button>
          <button class="btn btn-secondary" @click="editingUser = null">Zrušiť</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { listUsers, getRoles, updateUserRoles } from '@/api/access-control'
import type { AccessRole } from '@/types'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const users = ref<Record<string, unknown>[]>([])
const roles = ref<AccessRole[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const editingUser = ref<Record<string, unknown> | null>(null)
const selectedRoles = ref<string[]>([])
const saving = ref(false)

onMounted(async () => {
  loading.value = true
  try {
    ;[users.value, roles.value] = await Promise.all([listUsers(), getRoles()])
  } catch { error.value = 'Nepodarilo sa načítať.' }
  finally { loading.value = false }
})

function openRoleEditor(user: Record<string, unknown>) {
  editingUser.value = user
  selectedRoles.value = [...((user.roles as string[]) ?? [])]
}

async function saveRoles() {
  if (!editingUser.value) return
  saving.value = true
  try {
    await updateUserRoles(editingUser.value.id as number, selectedRoles.value)
    editingUser.value.roles = [...selectedRoles.value]
    toast.success('Role uložené.')
    editingUser.value = null
  } catch { toast.error('Uloženie zlyhalo.') }
  finally { saving.value = false }
}
</script>
