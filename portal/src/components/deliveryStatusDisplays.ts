import type { DeliveryStatus } from '@/api/eventsApi'

export const deliveryStatusDisplays: Record<DeliveryStatus, { label: string; color: string }> = {
  pending: { label: 'Pending', color: 'grey' },
  retrying: { label: 'Retrying', color: 'warning' },
  delivered: { label: 'Delivered', color: 'success' },
  failed: { label: 'Failed', color: 'error' },
}
