/** Client-side toast: the layout listens for this event. Server toasts come in as flash props. */
export function toast(message) {
    window.dispatchEvent(new CustomEvent('clan-toast', { detail: message }));
}
