import openingHourIntervalToReadable from '../restaurant/parseOpeningHours'
import { localeDetector } from '../i18n'

document.querySelectorAll('[data-opening-hours]').forEach(el => {
  const openingHours = JSON.parse(el.dataset.openingHours)
  const lines = openingHours.map(oh => openingHourIntervalToReadable(oh, localeDetector(), 'time_slot'))
  const ul = document.createElement('ul')
  ul.classList.add('list-unstyled')
  lines.forEach(line => {
    const li = document.createElement('li')
    li.innerHTML = line
    ul.appendChild(li)
  })
  el.appendChild(ul)
})
