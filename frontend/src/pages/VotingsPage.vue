<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div class="text-h5">Votings</div>
      <q-space />
      <q-btn v-if="auth.isAdmin" color="indigo-8" icon="add" label="New voting" @click="creating = true" />
    </div>

    <div class="row q-col-gutter-md">
      <div v-for="v in votings" :key="v.id" class="col-12 col-md-6">
        <q-card flat bordered class="cursor-pointer" @click="$router.push(`/votings/${v.id}`)">
          <q-card-section>
            <div class="row items-center">
              <div class="text-subtitle1 text-weight-medium col">{{ v.title }}</div>
              <q-badge :color="stateColor[v.state]" :label="v.state" />
            </div>
            <div class="text-caption text-grey-7">
              {{ date(v.starts_at) }} – {{ date(v.closed_at ?? v.ends_at) }} · {{ v.ballots_count }} ballots ·
              up to {{ v.max_choices }} choice(s)
            </div>
            <q-chip v-if="v.voted" dense icon="check" color="green-1" text-color="green-9" label="You voted" />
            <q-chip v-else-if="v.state === 'active'" dense icon="how_to_vote" color="indigo-1" text-color="indigo-9" label="Vote now" />
          </q-card-section>
        </q-card>
      </div>
    </div>

    <NewVotingDialog v-if="auth.isAdmin" v-model="creating" @created="load" />
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '../api/client'
import type { VotingSummary } from '../api/types'
import NewVotingDialog from '../components/NewVotingDialog.vue'
import { date, stateColor } from '../format'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const votings = ref<VotingSummary[]>([])
const creating = ref(false)

const load = async () => { votings.value = await api.votings() }
onMounted(load)
</script>
