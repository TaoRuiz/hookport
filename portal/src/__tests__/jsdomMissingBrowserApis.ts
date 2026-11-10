// jsdom has no visualViewport; Vuetify overlays (dialogs, menus) read it and handle null.
Object.defineProperty(window, 'visualViewport', { value: null, configurable: true })

// jsdom has no ResizeObserver; Vuetify menus (v-select) use it to position themselves.
class NoOpResizeObserver {
  observe(): void {}
  unobserve(): void {}
  disconnect(): void {}
}
Object.defineProperty(window, 'ResizeObserver', { value: NoOpResizeObserver, configurable: true })
