<template>
  <q-page padding>
    <div class="text-h5 q-mb-xs">Roadmap</div>
    <div class="text-grey-7 q-mb-md">
      What is planned, being built and shipped.
      <span v-if="auth.isAdmin">As an admin you can reorder cards inside a column.</span>
    </div>

    <div class="row q-col-gutter-md">
      <div v-for="column in columns" :key="column.status" class="col-12 col-md-4">
        <q-card flat bordered class="column-card">
          <q-card-section class="row items-center" :class="`bg-${statusColor[column.status]} text-white`">
            <div class="text-subtitle1">{{ column.label }}</div>
            <q-space />
            <q-badge color="white" text-color="black" :label="column.ideas.length" />
          </q-card-section>
          <q-list separator>
            <q-item v-for="(idea, index) in column.ideas" :key="idea.id">
              <q-item-section>
                <q-item-label>{{ idea.title }}</q-item-label>
                <q-item-label caption>{{ idea.votes_count }} votes</q-item-label>
              </q-item-section>
              <q-item-section v-if="auth.isAdmin" side>
                <div class="column">
                  <q-btn flat dense round size="sm" icon="keyboard_arrow_up" :disable="index === 0" @click="move(column, index, -1)" />
                  <q-btn flat dense round size="sm" icon="keyboard_arrow_down" :disable="index === column.ideas.length - 1" @click="move(column, index, 1)" />
                </div>
              </q-item-section>
            </q-item>
            <q-item v-if="!column.ideas.length"><q-item-section class="text-grey-6">Empty</q-item-section></q-item>
          </q-list>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { api, errorText } from '../api/client'
import type { RoadmapColumn } from '../api/types'
import { statusColor } from '../format'
import { useAuthStore } from '../stores/auth'

const $q = useQuasar()
const auth = useAuthStore()
const columns = ref<RoadmapColumn[]>([])

async function move(column: RoadmapColumn, index: number, delta: number) {
  const ids = column.ideas.map((i) => i.id)
  ;[ids[index], ids[index + delta]] = [ids[index + delta], ids[index]]
  try {
    columns.value = await api.reorder(column.status, ids)
  } catch (e) {
    $q.notify({ type: 'negative', message: errorText(e) })
    columns.value = await api.roadmap() // stale view: reload what the server has
  }
}

onMounted(async () => { columns.value = await api.roadmap() })
</script>
