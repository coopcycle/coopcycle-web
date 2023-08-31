import DatePicker from '../widgets/DatePicker'

['start', 'end'].forEach(name => {
  const inputEl = document.querySelector(`#data_export_${name}`)
  const widgetEl = document.querySelector(`#data_export_${name}_widget`)
  if (inputEl && widgetEl) {
    new DatePicker(widgetEl, {
      onChange: function(date) {
        if (date) {
          inputEl.value = date.format('YYYY-MM-DD');
        }
      }
    })
  }
})
