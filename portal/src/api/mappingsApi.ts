export interface MappingSource {
  id: number
  slug: string
  name: string
}

export interface MappingEventType {
  id: number
  slug: string
  label: string
}

export interface MappingTarget {
  id: number
  name: string
}

/** A null source or event type means "any". */
export interface Mapping {
  id: number
  source: MappingSource | null
  eventType: MappingEventType | null
  target: MappingTarget
  maxAttempts: number
  enabled: boolean
}

export async function listMappings(): Promise<Mapping[]> {
  const response = await fetch('/api/mappings')

  if (!response.ok) {
    throw new Error(`Loading mappings failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as Mapping[]
}
