export interface Source {
  id: number
  slug: string
  name: string
  enabled: boolean
}

export async function listSources(): Promise<Source[]> {
  const response = await fetch('/api/sources')

  if (!response.ok) {
    throw new Error(`Loading sources failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as Source[]
}

export interface CreatedSource extends Source {
  secret: string
}

export class SlugAlreadyUsedError extends Error {
  constructor() {
    super('This slug is already used.')
  }
}

export async function createSource(slug: string, name: string): Promise<CreatedSource> {
  const response = await fetch('/api/sources', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ slug, name }),
  })

  if (response.status === 409) {
    throw new SlugAlreadyUsedError()
  }

  if (!response.ok) {
    throw new Error(`Creating the source failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as CreatedSource
}

export async function changeSourceEnabled(sourceId: number, enabled: boolean): Promise<Source> {
  const response = await fetch(`/api/sources/${sourceId}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ enabled }),
  })

  if (!response.ok) {
    throw new Error(`Changing the source status failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as Source
}

export async function fetchSourceSecret(sourceId: number): Promise<string> {
  const response = await fetch(`/api/sources/${sourceId}/secret`)

  if (!response.ok) {
    throw new Error(`Loading the source secret failed with HTTP ${response.status}.`)
  }

  return ((await response.json()) as { secret: string }).secret
}

export async function renameSource(sourceId: number, name: string): Promise<Source> {
  const response = await fetch(`/api/sources/${sourceId}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name }),
  })

  if (!response.ok) {
    throw new Error(`Renaming the source failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as Source
}
