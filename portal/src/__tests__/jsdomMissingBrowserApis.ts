// jsdom has no visualViewport; Vuetify overlays (dialogs, menus) read it and handle null.
Object.defineProperty(window, 'visualViewport', { value: null, configurable: true })
