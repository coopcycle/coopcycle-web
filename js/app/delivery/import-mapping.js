/*
 * The column mapping screen.
 *
 * The mapping is checked on the server, which is what actually guards the import; this
 * only saves the dispatcher from submitting a form to be told that two of its twenty
 * selects point at the same field, by showing the fields already spoken for as taken.
 */

import './import-mapping.scss'

const SELECT_SELECTOR = 'select[data-column-mapping-choice]'

function markTakenOptions(selects) {
  const taken = new Set(
    selects.map(select => select.value).filter(value => value !== ''),
  )

  selects.forEach(select => {
    Array.from(select.options).forEach(option => {
      if (option.value === '' || option.value === select.value) {
        option.disabled = false
        return
      }

      option.disabled = taken.has(option.value)
    })
  })
}

document.querySelectorAll('form[data-column-mapping]').forEach(form => {
  const selects = Array.from(form.querySelectorAll(SELECT_SELECTOR))

  if (selects.length === 0) {
    return
  }

  selects.forEach(select =>
    select.addEventListener('change', () => markTakenOptions(selects)),
  )

  // The mapping the server suggested may already have taken fields, when it comes back
  // with errors after a submit
  markTakenOptions(selects)
})
