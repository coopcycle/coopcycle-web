import { DatePicker, LocaleProvider } from 'antd'
import React from 'react'
import { render } from 'react-dom'
import moment from 'moment'

import fr_FR from 'antd/lib/locale-provider/fr_FR'
import en_GB from 'antd/lib/locale-provider/en_GB'

const locale = $('html').attr('lang')
const antdLocale = locale === 'fr' ? fr_FR : en_GB

export default class TaskRangePicker {

  constructor(el, inputs) {

    const [ after, before ] = inputs

    const afterValue = after.value
    const beforeValue = before.value

    const $doneAfterHidden = $('<input>')
      .attr('type', 'hidden')
      .attr('name', after.getAttribute('name'))
      .attr('id', after.getAttribute('id'))
      .val(after.value)

    const $doneBeforeHidden = $('<input>')
      .attr('type', 'hidden')
      .attr('name', before.getAttribute('name'))
      .attr('id', before.getAttribute('id'))
      .val(before.value)

    $doneBeforeHidden.insertAfter($(el))
    $doneAfterHidden.insertAfter($(el))

    $(after).closest('.form-group').remove()
    $(before).closest('.form-group').remove()

    render(
      <LocaleProvider locale={ antdLocale }>
        <DatePicker.RangePicker
          style={{ width: '100%' }}
          showTime={{ hideDisabledOptions: true, format: 'HH:mm' }}
          format="YYYY-MM-DD HH:mm"
          defaultValue={[ moment(afterValue), moment(beforeValue) ]}
          onChange={(value, dateString) => {
            const [ doneAfter, doneBefore ] = dateString
            $doneAfterHidden.val(doneAfter)
            $doneBeforeHidden.val(doneBefore)
          }} />
      </LocaleProvider>,
      el
    )

  }
}
