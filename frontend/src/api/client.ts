import axios from 'axios'
import type { Idea, IdeaStatus, NewVoting, RoadmapColumn, SimilarIdea, User, VotingDetail, VotingSummary } from './types'

const TOKEN_KEY = 'feedback-voting-token'

export const tokenStorage = {
  get: () => localStorage.getItem(TOKEN_KEY),
  set: (token: string) => localStorage.setItem(TOKEN_KEY, token),
  clear: () => localStorage.removeItem(TOKEN_KEY),
}

const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api',
  headers: { Accept: 'application/json' },
})

// Sanctum personal access token as a Bearer header.
http.interceptors.request.use((config) => {
  const token = tokenStorage.get()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

http.interceptors.response.use(undefined, (error) => {
  if (axios.isAxiosError(error) && error.response?.status === 401 && !location.pathname.startsWith('/login')) {
    tokenStorage.clear()
    location.assign('/login')
  }
  return Promise.reject(error)
})

/** Stateless API layer, one function per endpoint. */
export const api = {
  login: (email: string, password: string) =>
    http.post<{ token: string; user: User }>('/login', { email, password }).then((r) => r.data),
  logout: () => http.post('/logout'),
  me: () => http.get<{ data: User }>('/me').then((r) => r.data.data),
  demoAccounts: () => http.get<{ data: { email: string; name: string; role: string }[] }>('/demo-accounts').then((r) => r.data.data),

  ideas: (params: { status?: IdeaStatus | null; sort?: 'top' | 'new'; search?: string }) =>
    http.get<{ data: Idea[] }>('/ideas', { params }).then((r) => r.data.data),
  idea: (id: number) => http.get<{ data: Idea }>(`/ideas/${id}`).then((r) => r.data.data),
  createIdea: (title: string, description: string) =>
    http.post<{ data: Idea }>('/ideas', { title, description }).then((r) => r.data.data),
  similarIdeas: (title: string) =>
    http.get<{ data: SimilarIdea[] }>('/ideas/similar', { params: { title } }).then((r) => r.data.data),
  vote: (id: number) => http.post<{ data: { voted: boolean; votes_count: number } }>(`/ideas/${id}/vote`).then((r) => r.data.data),
  changeStatus: (id: number, status: IdeaStatus, comment?: string) =>
    http.patch<{ data: Idea }>(`/ideas/${id}/status`, { status, comment }).then((r) => r.data.data),

  roadmap: () => http.get<{ data: RoadmapColumn[] }>('/roadmap').then((r) => r.data.data),
  reorder: (status: IdeaStatus, ids: number[]) =>
    http.put<{ data: RoadmapColumn[] }>('/roadmap/order', { status, ids }).then((r) => r.data.data),

  votings: () => http.get<{ data: VotingSummary[] }>('/votings').then((r) => r.data.data),
  voting: (id: number) => http.get<{ data: VotingDetail }>(`/votings/${id}`).then((r) => r.data.data),
  createVoting: (payload: NewVoting) => http.post<{ data: VotingDetail }>('/votings', payload).then((r) => r.data.data),
  castBallot: (id: number, optionIds: number[]) =>
    http.put<{ data: VotingDetail }>(`/votings/${id}/ballot`, { option_ids: optionIds }).then((r) => r.data.data),
  withdrawBallot: (id: number) => http.delete<{ data: VotingDetail }>(`/votings/${id}/ballot`).then((r) => r.data.data),
  closeVoting: (id: number) => http.post<{ data: VotingDetail }>(`/votings/${id}/close`).then((r) => r.data.data),
}

export function errorText(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined
    return (data?.errors && Object.values(data.errors)[0]?.[0]) || data?.message || error.message
  }
  return String(error)
}
