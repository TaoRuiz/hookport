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

export class MappingAlreadyExistsError extends Error {
  constructor() {
    super('This mapping already exists.')
  }
}

export interface MappingCreation {
  sourceId: number | null
  eventTypeId: number | null
  targetId: number
  maxAttempts: number
}

export async function createMapping(mappingCreation: MappingCreation): Promise<Mapping> {
  const response = await fetch('/api/mappings', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(mappingCreation),
  })

  if (response.status === 409) {
    throw new MappingAlreadyExistsError()
  }

  if (!response.ok) {
    throw new Error(`Creating the mapping failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as Mapping
}
