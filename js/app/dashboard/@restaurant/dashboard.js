import { renderDashboard } from '../../foodtech/dashboard'

const el = document.querySelector('#restaurant-dashboard')

const currentRoute = el.dataset.currentRoute
const restaurant = JSON.parse(el.dataset.restaurant)

renderDashboard(el, {
  onDateChange: function(date) {
    window.location.href =
      window.Routing.generate(currentRoute, { restaurantId: restaurant.id, date: date.format('YYYY-MM-DD') })
  }
});
