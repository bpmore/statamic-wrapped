import Wrapped from './pages/Wrapped.vue';
import Story from './pages/Story.vue';
import WrappedWidget from './components/WrappedWidget.vue';

Statamic.booting(() => {
    // Inertia pages are registered by the name Inertia::render() uses.
    Statamic.$inertia.register('wrapped::Wrapped', Wrapped);
    Statamic.$inertia.register('wrapped::Story', Story);

    // Dashboard widgets are ordinary Vue components, named by whatever the
    // widget's component() passes to VueComponent::render().
    Statamic.$components.register('wrapped-widget', WrappedWidget);
});
