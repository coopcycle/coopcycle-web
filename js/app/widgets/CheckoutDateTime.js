import moment from 'moment'
import _ from 'lodash'

moment.locale($('html').attr('lang'))

export default function(el, options) {

  if (!el) {

    return
  }

  const choices = JSON.parse(el.getAttribute('data-choices'))

  options.dateElement.addEventListener('change', (e) => {

    const values = _.filter(choices, (choice) => moment(choice).format('YYYY-MM-DD') === e.target.value)

    options.timeElement.querySelectorAll('option')
      .forEach(option => options.timeElement.removeChild(option))

    values.forEach(value => {
      const optionElement = document.createElement('option')
      optionElement.appendChild(document.createTextNode(moment(value).format('LT')))
      optionElement.value = moment(value).format('HH:mm')
      options.timeElement.appendChild(optionElement)
    })

  })
}
