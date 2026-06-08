import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['loader'];

    loaderTargetConnected(element) {
        this.observer ??= new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.dispatchEvent(
                        new CustomEvent('appear', { detail: { entry } })
                    );
                }
            });
        }, { rootMargin: '0px 0px 100px 0px' });
        this.observer.observe(element);
    }

    loaderTargetDisconnected(element) {
        this.observer?.unobserve(element);
    }

    disconnect() {
        this.observer?.disconnect();
    }
}
