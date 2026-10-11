export type Role = 'member' | 'admin'
export type IdeaStatus = 'new' | 'under_review' | 'planned' | 'in_progress' | 'done' | 'declined'
export type VotingState = 'scheduled' | 'active' | 'closed'
export type ResultsVisibility = 'always' | 'after_vote' | 'after_close'

export interface User {
  id: number
  name: string
  email: string
  role: Role
}

export interface Idea {
  id: number
  title: string
  description: string | null
  status: IdeaStatus
  status_label: string
  accepts_votes: boolean
  allowed_next: IdeaStatus[]
  votes_count: number
  voted_by_me: boolean
  author: string | null
  created_at: string | null
  history?: { from: IdeaStatus; to: IdeaStatus; by: string; comment: string | null; at: string }[]
}

export interface SimilarIdea {
  id: number
  title: string
  status: IdeaStatus
  votes_count: number
  score: number
}

export interface RoadmapColumn {
  status: IdeaStatus
  label: string
  ideas: { id: number; title: string; votes_count: number }[]
}

export interface VotingSummary {
  id: number
  title: string
  description: string | null
  state: VotingState
  starts_at: string
  ends_at: string
  closed_at: string | null
  max_choices: number
  results_visibility: ResultsVisibility
  ballots_count?: number
  voted?: boolean
}

export interface VotingDetail extends VotingSummary {
  options: { id: number; label: string; idea_id: number | null }[]
  my_choices: number[]
  results: {
    ballots: number
    options: { id: number; label: string; votes: number; share: number; leading: boolean }[]
  } | null
}

export interface NewVoting {
  title: string
  description?: string
  ends_at: string
  max_choices: number
  results_visibility: ResultsVisibility
  options: { idea_id?: number; label?: string }[]
}
