import type { IdeaStatus, VotingState } from './api/types'

export const statusColor: Record<IdeaStatus, string> = {
  new: 'grey-6',
  under_review: 'blue-grey-6',
  planned: 'indigo-6',
  in_progress: 'orange-8',
  done: 'green-7',
  declined: 'red-5',
}

export const statusLabel: Record<IdeaStatus, string> = {
  new: 'New',
  under_review: 'Under review',
  planned: 'Planned',
  in_progress: 'In progress',
  done: 'Done',
  declined: 'Declined',
}

export const stateColor: Record<VotingState, string> = { scheduled: 'blue-grey-5', active: 'green-7', closed: 'grey-6' }

export const date = (iso: string) => new Date(iso).toLocaleDateString()
