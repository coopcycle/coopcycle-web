import OpeningHoursInput from '../widgets/OpeningHoursInput'

$(function() {

  const openingHoursInputs = new Map()

  document.querySelectorAll('[data-widget="opening-hours"]').forEach((el) => {

    const ohi = new OpeningHoursInput(el, {
      locale: $('html').attr('lang'),
      rowsWithErrors: JSON.parse(el.dataset.errors),
      behavior: el.dataset.behavior,
      withBehavior: true,
      disabled: JSON.parse(el.dataset.disabled),
      onChangeBehavior: behavior => {
        const input = document
          .querySelector(el.dataset.behaviorSelector)
          .querySelector(`input[value="${behavior}"]`)
        if (input) {
          input.checked = true
        }
      }
    })

    openingHoursInputs.set(el.dataset.method, ohi)
  })

  document.querySelectorAll('[data-enable-fulfillment-method]').forEach(checkbox => {
    checkbox.addEventListener('change', e => {
      if (openingHoursInputs.has(e.target.value)) {
        const widget = openingHoursInputs.get(e.target.value)
        if (e.target.checked) {
          widget.enable()
        } else {
          widget.disable()
        }
      }
    })
  })

})
