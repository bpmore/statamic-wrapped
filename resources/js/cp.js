import Wrapped from './pages/Wrapped.vue';

// The name here has to match the one passed to Inertia::render() in
// WrappedController. Server-side routing, client-side rendering.
Statamic.booting(() => {
    Statamic.$inertia.register('wrapped::Wrapped', Wrapped);
});
