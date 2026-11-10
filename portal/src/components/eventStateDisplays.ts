import type { EventState } from '@/api/eventsApi'

export const eventStateDisplays: Record<EventState, { label: string; color: string }> = {
  unrouted: { label: 'Unrouted', color: 'grey' },
  in_progress: { label: 'In progress', color: 'warning' },
  delivered: { label: 'Delivered', color: 'success' },
  needs_attention: { label: 'Needs attention', color: 'error' },
}
