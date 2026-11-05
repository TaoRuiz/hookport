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
