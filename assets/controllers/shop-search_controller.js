import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

export default class extends Controller {

    async initialize() {
        this.component = await getComponent(this.element);

        this.component.on('render:finished', (component) => {
            const swiper = document.querySelector('.swiper.cuisines').swiper;
            if (swiper) {
                // https://swiperjs.com/swiper-api#method-swiper-update
                // You should call it after you add/remove slides manually,
                // or after you hide/show it, or do any custom DOM modifications with Swiper
                swiper.update();
            }
        });
    }
}
