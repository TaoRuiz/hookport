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

export interface CreatedTarget extends Target {
  secret: string
}

export async function createTarget(name: string, url: string): Promise<CreatedTarget> {
  const response = await fetch('/api/targets', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, url }),
  })

  if (!response.ok) {
    throw new Error(`Creating the target failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as CreatedTarget
}
