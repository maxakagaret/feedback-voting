<template>
  <q-layout view="hHh lpR fFf">
    <q-header elevated class="bg-indigo-8 text-white">
      <q-toolbar>
        <q-icon name="how_to_vote" size="sm" class="q-mr-sm" />
        <q-toolbar-title>Feedback &amp; Voting</q-toolbar-title>
        <template v-if="auth.user">
          <q-tabs dense inline-label shrink>
            <q-route-tab to="/" exact icon="lightbulb" label="Ideas" />
            <q-route-tab to="/roadmap" icon="view_kanban" label="Roadmap" />
            <q-route-tab to="/votings" icon="ballot" label="Votings" />
          </q-tabs>
          <q-chip dense :color="auth.isAdmin ? 'amber-7' : 'indigo-4'" text-color="white" class="q-ml-md">
            {{ auth.user.name }} · {{ auth.user.role }}
          </q-chip>
          <q-btn flat dense round icon="logout" @click="logout"><q-tooltip>Sign out</q-tooltip></q-btn>
        </template>
      </q-toolbar>
    </q-header>
    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function logout() {
  await auth.logout()
  await router.push('/login')
}
</script>
