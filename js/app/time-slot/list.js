import openingHourIntervalToReadable from '../restaurant/parseOpeningHours'
import { localeDetector } from '../i18n'

document.querySelectorAll('[data-opening-hours]').forEach(el => {
  const openingHours = JSON.parse(el.dataset.openingHours)
  el.innerHTML = openingHourIntervalToReadable(openingHours, localeDetector(), 'time_slot')
})
