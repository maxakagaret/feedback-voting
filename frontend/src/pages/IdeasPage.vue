<template>
  <q-page padding>
    <div class="row items-center q-mb-md q-gutter-sm">
      <div class="text-h5">Ideas</div>
      <q-space />
      <q-input v-model="search" dense outlined clearable placeholder="Search" debounce="300" style="min-width: 200px">
        <template #prepend><q-icon name="search" /></template>
      </q-input>
      <q-select v-model="status" :options="statusOptions" dense outlined clearable emit-value map-options label="Status" style="min-width: 150px" />
      <q-btn-toggle v-model="sort" dense unelevated toggle-color="indigo-8" :options="[{ label: 'Top', value: 'top' }, { label: 'New', value: 'new' }]" />
      <q-btn color="indigo-8" icon="add" label="Suggest" @click="creating = true" />
    </div>

    <q-list bordered separator class="rounded-borders bg-white">
      <q-item v-for="idea in ideas" :key="idea.id" clickable @click="openIdea(idea.id)">
        <q-item-section side class="q-pr-md">
          <q-btn
            :outline="!idea.voted_by_me"
            :unelevated="idea.voted_by_me"
            color="indigo-8"
            :disable="!idea.accepts_votes"
            class="vote"
            @click.stop="toggle(idea)"
          >
            <div class="column items-center">
              <q-icon name="keyboard_arrow_up" />
              <div class="text-weight-bold">{{ idea.votes_count }}</div>
            </div>
            <q-tooltip v-if="!idea.accepts_votes">Voting is closed for {{ idea.status_label.toLowerCase() }} ideas</q-tooltip>
          </q-btn>
        </q-item-section>
        <q-item-section>
          <q-item-label class="text-weight-medium">{{ idea.title }}</q-item-label>
          <q-item-label caption lines="2">{{ idea.description }}</q-item-label>
          <q-item-label caption>by {{ idea.author }}</q-item-label>
        </q-item-section>
        <q-item-section side>
          <q-badge :color="statusColor[idea.status]" :label="idea.status_label" />
        </q-item-section>
      </q-item>
      <q-item v-if="!ideas.length"><q-item-section class="text-grey-7">Nothing here yet.</q-item-section></q-item>
    </q-list>

    <NewIdeaDialog v-model="creating" @created="load" />
    <IdeaDialog v-model="dialog" :idea-id="selected" @changed="load" />
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { api, errorText } from '../api/client'
import type { Idea, IdeaStatus } from '../api/types'
import IdeaDialog from '../components/IdeaDialog.vue'
import NewIdeaDialog from '../components/NewIdeaDialog.vue'
import { statusColor, statusLabel } from '../format'

const $q = useQuasar()
const ideas = ref<Idea[]>([])
const search = ref<string | null>('')
const status = ref<IdeaStatus | null>(null)
const sort = ref<'top' | 'new'>('top')
const creating = ref(false)
const dialog = ref(false)
const selected = ref<number | null>(null)
const statusOptions = Object.entries(statusLabel).map(([value, label]) => ({ value, label }))

async function load() {
  ideas.value = await api.ideas({ status: status.value, sort: sort.value, search: search.value || undefined })
}

/** Optimistic toggle; the server's counter wins afterwards. */
async function toggle(idea: Idea) {
  const before = { voted: idea.voted_by_me, count: idea.votes_count }
  idea.voted_by_me = !before.voted
  idea.votes_count += before.voted ? -1 : 1
  try {
    const result = await api.vote(idea.id)
    idea.voted_by_me = result.voted
    idea.votes_count = result.votes_count
  } catch (e) {
    idea.voted_by_me = before.voted
    idea.votes_count = before.count
    $q.notify({ type: 'negative', message: errorText(e) })
  }
}

function openIdea(id: number) {
  selected.value = id
  dialog.value = true
}

watch([search, status, sort], load)
onMounted(load)
</script>

<style scoped>
.vote { min-width: 52px; padding: 2px 6px; }
</style>
