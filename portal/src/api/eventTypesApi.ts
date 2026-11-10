export interface EventType {
  id: number
  slug: string
  label: string
  enabled: boolean
}

export async function listEventTypes(): Promise<EventType[]> {
  const response = await fetch('/api/event-types')

  if (!response.ok) {
    throw new Error(`Loading event types failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as EventType[]
}

export class EventTypeSlugAlreadyUsedError extends Error {
  constructor() {
    super('This slug is already used.')
  }
}

export async function createEventType(slug: string, label: string): Promise<EventType> {
  const response = await fetch('/api/event-types', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ slug, label }),
  })

  if (response.status === 409) {
    throw new EventTypeSlugAlreadyUsedError()
  }

  if (!response.ok) {
    throw new Error(`Creating the event type failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as EventType
}
