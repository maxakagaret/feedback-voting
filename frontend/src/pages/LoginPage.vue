<template>
  <q-page class="flex flex-center bg-grey-2">
    <q-card style="width: 380px; max-width: 92vw">
      <q-card-section>
        <div class="text-h6">Sign in</div>
        <div class="text-caption text-grey-7">Synthetic demo accounts, password <code>password</code>.</div>
      </q-card-section>
      <q-form @submit.prevent="submit">
        <q-card-section class="q-gutter-md">
          <q-input v-model="email" type="email" label="Email" outlined dense autofocus />
          <q-input v-model="password" type="password" label="Password" outlined dense />
          <div class="q-gutter-xs">
            <q-chip
              v-for="account in accounts"
              :key="account.email"
              clickable
              dense
              :color="account.role === 'admin' ? 'amber-7' : 'indigo-2'"
              @click="email = account.email; password = 'password'"
            >
              {{ account.name }} ({{ account.role }})
            </q-chip>
          </div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn type="submit" color="indigo-8" label="Sign in" :loading="busy" />
        </q-card-actions>
      </q-form>
    </q-card>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import { api, errorText } from '../api/client'
import { useAuthStore } from '../stores/auth'

const $q = useQuasar()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const email = ref('member@example.test')
const password = ref('password')
const busy = ref(false)
const accounts = ref<{ email: string; name: string; role: string }[]>([])

onMounted(async () => { accounts.value = await api.demoAccounts() })

async function submit() {
  busy.value = true
  try {
    await auth.login(email.value, password.value)
    await router.push((route.query.next as string) || '/')
  } catch (e) {
    $q.notify({ type: 'negative', message: errorText(e) })
  } finally {
    busy.value = false
  }
}
</script>
