{{-- Listens for `notify` browser events dispatched from Livewire: $this->dispatch('notify', message: '...') --}}
<div
    x-data="{
        toasts: [],
        add(message, type = 'success') {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message, type });
            setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), type === 'success' ? 4000 : 8000);
        },
    }"
    x-init="@if (session('status')) add(@js(session('status'))) @endif"
    x-on:notify.window="add($event.detail.message, $event.detail.type ?? 'success')"
    class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4 sm:items-end sm:px-6"
>
    <template x-for="toast in toasts" x-bind:key="toast.id">
        <div
            x-transition
            class="pointer-events-auto w-full max-w-sm rounded-lg px-4 py-3 text-sm font-medium text-white shadow-lg"
            x-bind:class="{ 'bg-red-600': toast.type === 'error', 'bg-amber-600': toast.type === 'warning', 'bg-zinc-900': toast.type === 'success' }"
            x-text="toast.message"
        ></div>
    </template>
</div>
