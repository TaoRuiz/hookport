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

export type DeliveryStatus = 'pending' | 'retrying' | 'delivered' | 'failed'

export interface EventDelivery {
  uuid: string
  target: { id: number; name: string }
  status: DeliveryStatus
  attemptsCount: number
  maxAttempts: number
  nextAttemptAt: string | null
}

export interface ReceivedEventDetail extends ReceivedEvent {
  payload: unknown
  deliveries: EventDelivery[]
}

export async function showEvent(uuid: string): Promise<ReceivedEventDetail> {
  const response = await fetch(`/api/events/${uuid}`)

  if (!response.ok) {
    throw new Error(`Loading the event failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as ReceivedEventDetail
}

export async function replayUnroutedEvent(uuid: string): Promise<void> {
  const response = await fetch(`/api/events/${uuid}/replay`, { method: 'POST' })

  if (!response.ok) {
    throw new Error(`Replaying the event failed with HTTP ${response.status}.`)
  }
}

export async function replayFailedDelivery(uuid: string): Promise<void> {
  const response = await fetch(`/api/deliveries/${uuid}/replay`, { method: 'POST' })

  if (!response.ok) {
    throw new Error(`Replaying the delivery failed with HTTP ${response.status}.`)
  }
}
