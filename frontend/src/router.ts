import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from './stores/auth'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', component: () => import('./pages/LoginPage.vue'), meta: { public: true } },
    { path: '/', component: () => import('./pages/IdeasPage.vue') },
    { path: '/roadmap', component: () => import('./pages/RoadmapPage.vue') },
    { path: '/votings', component: () => import('./pages/VotingsPage.vue') },
    { path: '/votings/:id', component: () => import('./pages/VotingPage.vue'), props: (r) => ({ id: Number(r.params.id) }) },
  ],
})

router.beforeEach(async (to) => {
  if (to.meta.public) return true
  return (await useAuthStore().restore()) ? true : { path: '/login', query: { next: to.fullPath } }
})
