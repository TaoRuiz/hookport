import type { DeliveryStatus } from '@/api/eventsApi'

export interface DeliveryAttempt {
  attemptNo: number
  sentAt: string
  durationMs: number
  responseCode: number | null
  error: string | null
  responseExcerpt: string | null
}

export interface DeliveryDetail {
  uuid: string
  eventUuid: string
  target: { id: number; name: string }
  status: DeliveryStatus
  attemptsCount: number
  maxAttempts: number
  nextAttemptAt: string | null
  attempts: DeliveryAttempt[]
}

export async function showDelivery(uuid: string): Promise<DeliveryDetail> {
  const response = await fetch(`/api/deliveries/${uuid}`)

  if (!response.ok) {
    throw new Error(`Loading the delivery failed with HTTP ${response.status}.`)
  }

  return (await response.json()) as DeliveryDetail
}
