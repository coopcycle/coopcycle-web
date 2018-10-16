import DatePicker from 'antd/lib/date-picker'
import Form from 'antd/lib/form'

import React from 'react'
import { render } from 'react-dom'
import moment from 'moment'

import LocaleProvider from 'antd/lib/locale-provider'
import fr_FR from 'antd/lib/locale-provider/fr_FR'
import en_GB from 'antd/lib/locale-provider/en_GB'

const locale = $('html').attr('lang')
const antdLocale = locale === 'fr' ? fr_FR : en_GB

export default class TaskRangePicker {

  constructor(el, inputs) {

    const [ after, before ] = inputs

    const afterValue = after.value
    const beforeValue = before.value

    $(after).closest('.form-group').addClass('hidden')
    $(before).closest('.form-group').addClass('hidden')

    const hasError = $(after).closest('.form-group').hasClass('has-error') || $(before).closest('.form-group').hasClass('has-error')
    const formItemProps = hasError ? { validateStatus: 'error' } : {}

    render(
      <LocaleProvider locale={ antdLocale }>
        <Form>
          <Form.Item { ...formItemProps }>
            <DatePicker.RangePicker
              style={{ width: '100%' }}
              showTime={{ hideDisabledOptions: true, format: 'HH:mm' }}
              format="YYYY-MM-DD HH:mm"
              defaultValue={[ moment(afterValue), moment(beforeValue) ]}
              onChange={(value, dateString) => {
                const [ doneAfter, doneBefore ] = dateString
                $(after).val(doneAfter)
                $(before).val(doneBefore)
              }} />
          </Form.Item>
        </Form>
      </LocaleProvider>,
      el
    )

  }
}
