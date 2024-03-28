import { renderDashboard } from '../../foodtech/dashboard'

const el = document.querySelector('#foodtech-dashboard')

const currentRoute = el.dataset.currentRoute

renderDashboard(el, {
  onDateChange: function(date) {
    window.location.href =
      window.Routing.generate(currentRoute, { date: date.format('YYYY-MM-DD') })
  }
});
