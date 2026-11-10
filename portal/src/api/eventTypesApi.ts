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
