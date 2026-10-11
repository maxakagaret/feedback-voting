<template>
  <q-dialog v-model="open">
    <q-card style="width: 620px; max-width: 95vw">
      <q-card-section class="text-h6">New voting</q-card-section>
      <q-form @submit.prevent="submit">
        <q-card-section class="q-gutter-md">
          <q-input v-model="form.title" label="Question" outlined dense />
          <q-input v-model="form.description" label="Description (optional)" outlined dense autogrow type="textarea" />

          <q-select
            v-model="ideaIds"
            :options="ideaOptions"
            label="Options from the ideas board"
            multiple
            use-chips
            emit-value
            map-options
            outlined
            dense
          />
          <q-select
            v-model="freeOptions"
            label="Free-text options (type and press Enter)"
            multiple
            use-chips
            use-input
            hide-dropdown-icon
            new-value-mode="add-unique"
            outlined
            dense
          />

          <div class="row q-col-gutter-sm">
            <q-input v-model="form.ends_at" type="date" label="Ends on" outlined dense class="col-6" />
            <q-input v-model.number="form.max_choices" type="number" min="1" label="Max choices per voter" outlined dense class="col-6" />
          </div>
          <q-select
            v-model="form.results_visibility"
            :options="visibilityOptions"
            emit-value
            map-options
            label="Who sees results when"
            outlined
            dense
          />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn type="submit" color="indigo-8" label="Start voting" :loading="busy" />
        </q-card-actions>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { api, errorText } from '../api/client'
import type { NewVoting } from '../api/types'

const open = defineModel<boolean>({ required: true })
const emit = defineEmits<{ created: [] }>()
const $q = useQuasar()
const busy = ref(false)
const ideaIds = ref<number[]>([])
const freeOptions = ref<string[]>([])
const ideaOptions = ref<{ label: string; value: number }[]>([])
const visibilityOptions = [
  { label: 'After the voter has voted (recommended)', value: 'after_vote' },
  { label: 'Only after the voting closes', value: 'after_close' },
  { label: 'Always', value: 'always' },
]

const inTwoWeeks = new Date(Date.now() + 14 * 864e5).toISOString().slice(0, 10)
const form = reactive<Omit<NewVoting, 'options'>>({
  title: '', description: '', ends_at: inTwoWeeks, max_choices: 1, results_visibility: 'after_vote',
})

onMounted(async () => {
  const ideas = await api.ideas({ sort: 'top' })
  ideaOptions.value = ideas.filter((i) => i.accepts_votes).map((i) => ({ label: `${i.title} (${i.votes_count})`, value: i.id }))
})

async function submit() {
  busy.value = true
  try {
    await api.createVoting({
      ...form,
      ends_at: `${form.ends_at}T23:59:00`,
      options: [...ideaIds.value.map((id) => ({ idea_id: id })), ...freeOptions.value.map((label) => ({ label }))],
    })
    $q.notify({ type: 'positive', message: 'Voting started' })
    open.value = false
    emit('created')
  } catch (e) {
    $q.notify({ type: 'negative', message: errorText(e) })
  } finally {
    busy.value = false
  }
}
</script>
