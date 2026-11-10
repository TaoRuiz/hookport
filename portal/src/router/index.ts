import { createRouter, createWebHistory, type RouterHistory } from 'vue-router'
import { useAuthenticatedUserStore } from '@/stores/authenticatedUser'
import DeliveryDetailView from '@/views/DeliveryDetailView.vue'
import EventDetailView from '@/views/EventDetailView.vue'
import EventsView from '@/views/EventsView.vue'
import EventTypesView from '@/views/EventTypesView.vue'
import HomeView from '@/views/HomeView.vue'
import LoginView from '@/views/LoginView.vue'
import MappingsView from '@/views/MappingsView.vue'
import SourcesView from '@/views/SourcesView.vue'
import TargetsView from '@/views/TargetsView.vue'

declare module 'vue-router' {
  interface RouteMeta {
    isPublic?: boolean
  }
}

export function createAppRouter(history: RouterHistory) {
  const router = createRouter({
    history,
    routes: [
      { path: '/', name: 'home', component: HomeView },
      { path: '/login', name: 'login', component: LoginView, meta: { isPublic: true } },
      { path: '/events', name: 'events', component: EventsView },
      { path: '/events/:uuid', name: 'event-detail', component: EventDetailView, props: true },
      {
        path: '/deliveries/:uuid',
        name: 'delivery-detail',
        component: DeliveryDetailView,
        props: true,
      },
      { path: '/sources', name: 'sources', component: SourcesView },
      { path: '/targets', name: 'targets', component: TargetsView },
      { path: '/event-types', name: 'event-types', component: EventTypesView },
      { path: '/mappings', name: 'mappings', component: MappingsView },
    ],
  })

  router.beforeEach(async (destination) => {
    const authenticatedUserStore = useAuthenticatedUserStore()
    await authenticatedUserStore.restoreSessionOnce()

    if (!destination.meta.isPublic && !authenticatedUserStore.authenticatedUser) {
      return { name: 'login' }
    }
  })

  return router
}

export default createAppRouter(createWebHistory(import.meta.env.BASE_URL))
