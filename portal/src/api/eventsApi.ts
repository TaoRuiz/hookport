export type EventState = 'unrouted' | 'in_progress' | 'delivered' | 'needs_attention'

export interface ReceivedEvent {
  uuid: string
  type: string
  source: { id: number; slug: string }
  externalId: string
  receivedAt: string
  state: EventState
}

export async function listEvents(): Promise<ReceivedEvent[]> {
  const response = await fetch('/api/events')

  if (!response.ok) {
    throw new Error(`Loading events failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as ReceivedEvent[]
}
