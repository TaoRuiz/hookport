export type EventState = 'unrouted' | 'in_progress' | 'delivered' | 'needs_attention'

/** The label is null for a type missing from the catalog. */
export interface ReceivedEventType {
  slug: string
  label: string | null
}

export interface ReceivedEvent {
  uuid: string
  type: ReceivedEventType
  source: { id: number; slug: string; name: string }
  externalId: string
  receivedAt: string
  state: EventState
  deliveries: EventDeliverySummary[]
}

/** Empty or null filters keep every event. Dates are ISO 8601, receivedBefore is excluded. */
export interface EventListFilters {
  search: string
  receivedFrom: string | null
  receivedBefore: string | null
  type: string | null
  sourceId: number | null
  states: EventState[]
}

function buildEventListQueryString(eventListFilters: EventListFilters): string {
  const queryParameters = new URLSearchParams()
  Object.entries(eventListFilters).forEach(([filterName, filterValue]) => {
    if (Array.isArray(filterValue)) {
      filterValue.forEach((item) => queryParameters.append(`${filterName}[]`, String(item)))
    } else if (filterValue !== null && filterValue !== '') {
      queryParameters.set(filterName, String(filterValue))
    }
  })

  return queryParameters.toString()
}

export async function listEvents(eventListFilters: EventListFilters): Promise<ReceivedEvent[]> {
  const queryString = buildEventListQueryString(eventListFilters)
  const response = await fetch(queryString === '' ? '/api/events' : `/api/events?${queryString}`)

  if (!response.ok) {
    throw new Error(`Loading events failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as ReceivedEvent[]
}

export type DeliveryStatus = 'pending' | 'retrying' | 'delivered' | 'failed'

export interface EventDeliverySummary {
  uuid: string
  target: { id: number; name: string }
  status: DeliveryStatus
}

export interface EventDelivery extends EventDeliverySummary {
  attemptsCount: number
  maxAttempts: number
  nextAttemptAt: string | null
}

export interface ReceivedEventDetail extends Omit<ReceivedEvent, 'deliveries'> {
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
