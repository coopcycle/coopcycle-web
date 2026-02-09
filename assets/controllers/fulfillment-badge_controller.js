import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';
import { asText } from '../../js/app/components/ShippingTimeRange'

export default class extends Controller {

    static values = {
        fulfillmentMethod: Object
    }

    static targets = [ "label" ]

    async initialize() {
        this.component = await getComponent(this.element);
        this.labelTarget.innerHTML = asText(this.fulfillmentMethodValue.range, false, true);
    }
}
