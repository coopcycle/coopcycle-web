import DatePicker from '../widgets/DatePicker'
import DateRangePicker from '../widgets/DateRangePicker'
import Input from '../widgets/Input'

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

const startEl = document.getElementById('start_at')
const endEl = document.getElementById('end_at')
const dateRangeWidgetEl = document.getElementById('daterange_widget')

if (startEl && endEl && dateRangeWidgetEl) {
  let options = {
    showTime: false,
    format: 'DD MMM',
    onChange: function ({after, before}) {
      startEl.value = after.format('YYYY-MM-DD')
      endEl.value = before.format('YYYY-MM-DD')
    }
  }

  if (startEl.value && endEl.value) {
    options = {
      defaultValue: {
        before: startEl.value,
        after: endEl.value
      },
      ...options
    }
  }

  new DateRangePicker(dateRangeWidgetEl, options)
}

const searchEl = document.getElementById('search_input')
const searchWidgetEl = document.getElementById('search_input_widget')

if (searchEl && searchWidgetEl) {
  new Input(searchWidgetEl, {
    allowClear: true,
    placeholder: searchEl.placeholder,
    defaultValue: searchEl.value,
    onChange: function(e) {
      searchEl.value = e.target.value
    }
  })
}
