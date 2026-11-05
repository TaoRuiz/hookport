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
