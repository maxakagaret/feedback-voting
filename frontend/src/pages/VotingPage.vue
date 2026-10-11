<template>
  <q-page padding>
    <template v-if="voting">
      <div class="row items-center q-mb-xs">
        <q-btn flat round icon="arrow_back" to="/votings" />
        <div class="text-h5 q-ml-sm col">{{ voting.title }}</div>
        <q-badge :color="stateColor[voting.state]" :label="voting.state" class="q-mr-sm" />
        <q-btn v-if="auth.isAdmin && voting.state !== 'closed'" outline color="negative" dense label="Close now" @click="close" />
      </div>
      <div class="text-grey-7 q-mb-md">
        {{ voting.description }}
        <span>Ends {{ date(voting.closed_at ?? voting.ends_at) }}. Choose up to {{ voting.max_choices }}.</span>
      </div>

      <div class="row q-col-gutter-md">
        <div class="col-12 col-md-6">
          <q-card flat bordered>
            <q-card-section class="text-subtitle1">Your ballot</q-card-section>
            <q-card-section class="q-pt-none">
              <q-option-group
                v-model="selection"
                :options="voting.options.map((o) => ({ label: o.label, value: o.id }))"
                :type="voting.max_choices > 1 ? 'checkbox' : 'radio'"
                :disable="voting.state !== 'active'"
              />
              <div v-if="voting.max_choices > 1 && choices.length > voting.max_choices" class="text-negative text-caption">
                Too many choices: at most {{ voting.max_choices }}.
              </div>
            </q-card-section>
            <q-card-actions v-if="voting.state === 'active'">
              <q-btn color="indigo-8" :label="voting.my_choices.length ? 'Update vote' : 'Vote'" :disable="!canSubmit" :loading="busy" @click="cast" />
              <q-btn v-if="voting.my_choices.length" flat color="grey-8" label="Withdraw" @click="withdraw" />
            </q-card-actions>
            <q-card-section v-else class="text-grey-7">This voting is {{ voting.state }}.</q-card-section>
          </q-card>
        </div>

        <div class="col-12 col-md-6">
          <q-card flat bordered>
            <q-card-section class="text-subtitle1">Results</q-card-section>
            <q-card-section v-if="voting.results" class="q-pt-none">
              <div v-for="o in voting.results.options" :key="o.id" class="q-mb-sm">
                <div class="row">
                  <div class="col" :class="{ 'text-weight-bold': o.leading }">
                    {{ o.label }} <q-icon v-if="o.leading" name="emoji_events" color="amber-8" />
                  </div>
                  <div>{{ o.votes }} · {{ (o.share * 100).toFixed(0) }}%</div>
                </div>
                <q-linear-progress :value="o.share" :color="o.leading ? 'indigo-8' : 'indigo-3'" size="10px" rounded />
              </div>
              <div class="text-caption text-grey-7">{{ voting.results.ballots }} ballots · share = % of voters who picked the option</div>
            </q-card-section>
            <q-card-section v-else class="q-pt-none text-grey-7">
              {{ voting.results_visibility === 'after_close'
                ? 'Results are revealed when the voting closes.'
                : 'Vote to see the results — this keeps early numbers from swaying your choice.' }}
            </q-card-section>
          </q-card>
        </div>
      </div>
    </template>
    <q-inner-loading :showing="!voting" />
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { api, errorText } from '../api/client'
import type { VotingDetail } from '../api/types'
import { date, stateColor } from '../format'
import { useAuthStore } from '../stores/auth'

const props = defineProps<{ id: number }>()
const $q = useQuasar()
const auth = useAuthStore()
const voting = ref<VotingDetail | null>(null)
const selection = ref<number[] | number | null>(null)
const busy = ref(false)

// Radio gives a single id, checkboxes an array — normalise to a list.
const choices = computed<number[]>({
  get: () => (Array.isArray(selection.value) ? selection.value : selection.value === null ? [] : [selection.value]),
  set: (v) => { selection.value = v },
})
const canSubmit = computed(() => choices.value.length >= 1 && choices.value.length <= (voting.value?.max_choices ?? 1))

function apply(detail: VotingDetail) {
  voting.value = detail
  selection.value = detail.max_choices > 1 ? [...detail.my_choices] : (detail.my_choices[0] ?? null)
}

async function run(action: () => Promise<VotingDetail>, message?: string) {
  busy.value = true
  try {
    apply(await action())
    if (message) $q.notify({ type: 'positive', message })
  } catch (e) {
    $q.notify({ type: 'negative', message: errorText(e) })
  } finally {
    busy.value = false
  }
}

const cast = () => run(() => api.castBallot(props.id, choices.value), 'Your vote is recorded')
const withdraw = () => run(() => api.withdrawBallot(props.id), 'Vote withdrawn')
const close = () => run(() => api.closeVoting(props.id), 'Voting closed')

onMounted(async () => apply(await api.voting(props.id)))
</script>
