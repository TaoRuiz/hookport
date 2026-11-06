export interface Target {
  id: number
  name: string
  protocol: string
  config: { url: string }
  enabled: boolean
}

export async function listTargets(): Promise<Target[]> {
  const response = await fetch('/api/targets')

  if (!response.ok) {
    throw new Error(`Loading targets failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as Target[]
}
