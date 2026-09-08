import Wrapped from './pages/Wrapped.vue';
import WrappedWidget from './components/WrappedWidget.vue';

Statamic.booting(() => {
    // Inertia pages are registered by the name Inertia::render() uses.
    Statamic.$inertia.register('wrapped::Wrapped', Wrapped);

    // Dashboard widgets are ordinary Vue components, named by whatever the
    // widget's component() passes to VueComponent::render().
    Statamic.$components.register('wrapped-widget', WrappedWidget);
});
