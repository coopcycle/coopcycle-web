import { Controller } from '@hotwired/stimulus';

/**
 * Per-row "remove bookmark" button on the Saved Orders list.
 *
 * Wires a confirm() prompt, a single DELETE through the shared
 * window._auth.httpClient (which handles JWT refresh + 401 retries),
 * then removes the row from the DOM on success. Reuses the API Platform
 * operation DELETE /api/orders/{id}/bookmark.
 *
 * Reads the order ID from the plain `data-order-id` attribute on the
 * button (rather than a Stimulus value) so the controller works even
 * if the value machinery can't find the auto-generated
 * `data-{identifier}-{name}-value` attribute for some reason.
 *
 * Targets:
 *   - icon : the <i> swapped for a spinner while the request is in flight
 *
 * Values (server-translated strings):
 *   - confirmMessage : text shown in window.confirm()
 *   - errorMessage   : fallback alert text when the response has no readable message
 *   - emptyMessage   : text rendered when the last row of the table is removed
 *
 * Usage in Twig:
 *   <button
 *     type="button"
 *     data-order-id="{{ delivery.order.id }}"
 *     data-testid="bookmark__delete"
 *     {{ stimulus_controller('order-bookmark-delete', {
 *       confirmMessage: '...'|trans,
 *       errorMessage:   '...'|trans,
 *       emptyMessage:   '...'|trans,
 *     }) }}
 *     data-action="order-bookmark-delete#delete">
 *     <i {{ stimulus_target('order-bookmark-delete', 'icon') }} class="fa fa-trash fa-lg"></i>
 *   </button>
 */
export default class extends Controller {
    static values = {
        confirmMessage: { type: String, default: '' },
        errorMessage:   { type: String, default: 'Could not delete' },
        emptyMessage:   { type: String, default: '' },
    };

    static targets = ['icon'];

    get orderId() {
        const raw = this.element.dataset.orderId;
        const id = parseInt(raw, 10);
        if (!Number.isFinite(id) || id <= 0) {
            throw new Error('order-bookmark-delete: missing or invalid data-order-id on element');
        }
        return id;
    }

    async delete(event) {
        event.preventDefault();
        // The row click handler in delivery/list.js navigates the row on
        // click; stop propagation so clicking the trash doesn't open the
        // order page.
        event.stopPropagation();

        if (this.element.disabled) return;

        let orderId;
        try {
            orderId = this.orderId;
        } catch (e) {
            // Misconfigured button — surface the error so we notice in dev.
            // eslint-disable-next-line no-console
            console.error(e);
            window.alert(this.errorMessageValue);
            return;
        }

        if (this.confirmMessageValue && !window.confirm(this.confirmMessageValue)) {
            return;
        }

        this._setLoading(true);

        const httpClient = new window._auth.httpClient();
        const { error } = await httpClient.delete(`/api/orders/${orderId}/bookmark`);

        if (error) {
            this._setLoading(false);
            this._handleError(error);
            return;
        }

        this._removeRow();
    }

    _setLoading(loading) {
        this.element.disabled = loading;
        if (this.hasIconTarget) {
            this.iconTarget.classList.toggle('fa-trash', !loading);
            this.iconTarget.classList.toggle('fa-spinner', loading);
            this.iconTarget.classList.toggle('fa-spin', loading);
        }
    }

    _removeRow() {
        const row = this.element.closest('tr[data-testid="delivery__list_item"]')
            ?? this.element.closest('tr');
        const table = this.element.closest('table');

        if (!row) {
            return;
        }

        row.remove();

        // If the table is now empty, swap in the empty-state message
        // (matching the {% else %} branch in the Twig template).
        if (table && !table.querySelector('tbody tr')) {
            const p = document.createElement('p');
            p.className = 'text-muted';
            p.textContent = this.emptyMessageValue;
            table.replaceWith(p);
        }
    }

    _handleError(error) {
        const data = error.response?.data || error.data || null;
        const violationMessage = data?.violations
            ?.map(v => v.message)
            .filter(Boolean)
            .join('\n');
        const reason = violationMessage || data?.['hydra:description'] || data?.detail;
        window.alert(reason || this.errorMessageValue);
    }
}
