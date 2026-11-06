export interface AuthenticatedUser {
  login: string
  roles: string[]
  grantedRoles: string[]
}

export class InvalidCredentialsError extends Error {
  constructor() {
    super('Invalid login or password.')
  }
}

export async function logIn(login: string, password: string): Promise<AuthenticatedUser> {
  const response = await fetch('/api/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ login, password }),
  })

  if (response.status === 401) {
    throw new InvalidCredentialsError()
  }

  if (!response.ok) {
    throw new Error(`Login failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as AuthenticatedUser
}

export async function fetchAuthenticatedUser(): Promise<AuthenticatedUser | null> {
  const response = await fetch('/api/me')

  if (response.status === 401) {
    return null
  }

  if (!response.ok) {
    throw new Error(`Session check failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as AuthenticatedUser
}

export async function logOut(): Promise<void> {
  const response = await fetch('/api/logout', { method: 'POST' })

  if (!response.ok) {
    throw new Error(`Logout failed with HTTP ${response.status}.`)
  }
}
