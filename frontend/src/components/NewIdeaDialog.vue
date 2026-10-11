<template>
  <q-dialog v-model="open">
    <q-card style="width: 560px; max-width: 95vw">
      <q-card-section class="text-h6">Suggest an improvement</q-card-section>
      <q-form @submit.prevent="submit">
        <q-card-section class="q-gutter-md">
          <q-input v-model="title" label="Title" outlined dense counter maxlength="140" :rules="[(v) => v.length >= 8 || 'At least 8 characters']" />

          <!-- Duplicates split the votes: suggest upvoting an existing idea instead. -->
          <q-banner v-if="similar.length" dense class="bg-amber-1">
            <div class="text-weight-medium q-mb-xs">Similar ideas already exist — maybe upvote one of them?</div>
            <div v-for="s in similar" :key="s.id" class="row items-center q-py-xs">
              <q-icon name="lightbulb" color="amber-8" class="q-mr-sm" />
              <div class="col">{{ s.title }}</div>
              <q-badge :color="statusColor[s.status]" :label="statusLabel[s.status]" class="q-mr-sm" />
              <span class="text-caption">{{ s.votes_count }} votes</span>
            </div>
          </q-banner>

          <q-input v-model="description" label="Why would it help? (optional)" type="textarea" autogrow outlined dense />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" v-close-popup />
          <q-btn type="submit" color="indigo-8" label="Submit idea" :loading="busy" />
        </q-card-actions>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { api, errorText } from '../api/client'
import type { SimilarIdea } from '../api/types'
import { statusColor, statusLabel } from '../format'

const open = defineModel<boolean>({ required: true })
const emit = defineEmits<{ created: [] }>()
const $q = useQuasar()
const title = ref('')
const description = ref('')
const similar = ref<SimilarIdea[]>([])
const busy = ref(false)

let timer: ReturnType<typeof setTimeout> | undefined
watch(title, (value) => {
  clearTimeout(timer)
  timer = setTimeout(async () => {
    similar.value = value.trim().length >= 4 ? await api.similarIdeas(value) : []
  }, 300)
})

async function submit() {
  busy.value = true
  try {
    await api.createIdea(title.value, description.value)
    $q.notify({ type: 'positive', message: 'Thanks! Your idea is on the board.' })
    title.value = ''
    description.value = ''
    open.value = false
    emit('created')
  } catch (e) {
    $q.notify({ type: 'negative', message: errorText(e) })
  } finally {
    busy.value = false
  }
}
</script>
