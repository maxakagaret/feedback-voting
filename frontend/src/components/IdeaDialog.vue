<template>
  <q-dialog v-model="open">
    <q-card v-if="idea" style="width: 560px; max-width: 95vw">
      <q-card-section class="row items-start no-wrap">
        <div class="col">
          <div class="text-h6">{{ idea.title }}</div>
          <div class="text-caption text-grey-7">by {{ idea.author }} · {{ idea.created_at ? date(idea.created_at) : '' }}</div>
        </div>
        <q-badge :color="statusColor[idea.status]" :label="idea.status_label" />
      </q-card-section>
      <q-card-section v-if="idea.description" class="q-pt-none">{{ idea.description }}</q-card-section>

      <q-card-section v-if="idea.history?.length" class="q-pt-none">
        <div class="text-subtitle2">Status history</div>
        <q-timeline layout="dense" color="indigo">
          <q-timeline-entry
            v-for="(h, i) in idea.history"
            :key="i"
            :title="`${statusLabel[h.from]} → ${statusLabel[h.to]}`"
            :subtitle="`${date(h.at)} · ${h.by}`"
          >
            <div v-if="h.comment">{{ h.comment }}</div>
          </q-timeline-entry>
        </q-timeline>
      </q-card-section>

      <q-card-section v-if="auth.isAdmin && idea.allowed_next.length" class="q-pt-none">
        <div class="text-subtitle2 q-mb-xs">Move to (admin)</div>
        <q-input v-model="comment" dense outlined label="Comment for the community (optional)" class="q-mb-sm" />
        <q-btn
          v-for="next in idea.allowed_next"
          :key="next"
          outline
          dense
          no-caps
          class="q-mr-xs q-mb-xs"
          :color="statusColor[next]"
          :label="statusLabel[next]"
          @click="move(next)"
        />
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { api, errorText } from '../api/client'
import type { Idea, IdeaStatus } from '../api/types'
import { date, statusColor, statusLabel } from '../format'
import { useAuthStore } from '../stores/auth'

const open = defineModel<boolean>({ required: true })
const props = defineProps<{ ideaId: number | null }>()
const emit = defineEmits<{ changed: [] }>()
const auth = useAuthStore()
const $q = useQuasar()
const idea = ref<Idea | null>(null)
const comment = ref('')

async function load() {
  if (props.ideaId) idea.value = await api.idea(props.ideaId)
}

watch(() => [open.value, props.ideaId], ([isOpen]) => {
  idea.value = null
  comment.value = ''
  if (isOpen) void load()
})

async function move(status: IdeaStatus) {
  try {
    await api.changeStatus(idea.value!.id, status, comment.value || undefined)
    comment.value = ''
    await load()
    emit('changed')
  } catch (e) {
    $q.notify({ type: 'negative', message: errorText(e) })
  }
}
</script>
