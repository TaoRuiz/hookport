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

export async function changeTargetEnabled(targetId: number, enabled: boolean): Promise<Target> {
  const response = await fetch(`/api/targets/${targetId}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ enabled }),
  })

  if (!response.ok) {
    throw new Error(`Changing the target status failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as Target
}

export async function fetchTargetSecret(targetId: number): Promise<string> {
  const response = await fetch(`/api/targets/${targetId}/secret`)

  if (!response.ok) {
    throw new Error(`Loading the target secret failed with HTTP ${response.status}.`)
  }

  return ((await response.json()) as { secret: string }).secret
}

export async function modifyTarget(targetId: number, name: string, url: string): Promise<Target> {
  const response = await fetch(`/api/targets/${targetId}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, url }),
  })

  if (!response.ok) {
    throw new Error(`Modifying the target failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as Target
}
