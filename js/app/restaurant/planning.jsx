import React from 'react'
import { render } from 'react-dom'
import Calendar from 'antd/lib/calendar'
import moment from 'moment'
import 'moment/locale/fr'
import LocaleProvider from 'antd/lib/locale-provider'
import frFR from 'antd/lib/locale-provider/fr_FR'
import enGB from 'antd/lib/locale-provider/en_GB'
import DatePicker from 'antd/lib/date-picker'
import _ from 'lodash'

const { RangePicker } = DatePicker,
  // HACK : disable default input style so it fits with Bootstrap's design
  rangeInputStyle = {
    'padding': '0',
    'width': '100%'
  }

let antdLocale,
  locale = $('html').attr('lang'),
  selectedClosingStartDate,
  selectedClosingEndDate,
  $closingRuleForm = $('#closing-rules-form'),
  closingRules = window.AppData.closingRules || []

closingRules = _.map(closingRules, function (item) {
  return {
    startDate: moment(item.startDate),
    endDate: moment(item.endDate),
    reason: item.reason,
  }
})

moment.locale(locale)

if (locale === 'fr') {
  antdLocale = frFR
} else {
  antdLocale = enGB
}

function onChange(dates) {
  if (dates.length === 2) {
    selectedClosingStartDate = dates[0]
    selectedClosingEndDate = dates[1]
    $closingRuleForm.find('[name=closing_rule\\[startDate\\]\\[date\\]\\[month\\]]').val(parseInt(selectedClosingStartDate.month()) + 1)
    $closingRuleForm.find('[name=closing_rule\\[startDate\\]\\[date\\]\\[year\\]]').val(selectedClosingStartDate.year())
    $closingRuleForm.find('[name=closing_rule\\[startDate\\]\\[date\\]\\[day\\]]').val(selectedClosingStartDate.date())
    $closingRuleForm.find('[name=closing_rule\\[startDate\\]\\[time\\]\\[hour\\]]').val(selectedClosingStartDate.hour())
    $closingRuleForm.find('[name=closing_rule\\[startDate\\]\\[time\\]\\[minute\\]]').val(selectedClosingStartDate.minute())
    $closingRuleForm.find('[name=closing_rule\\[endDate\\]\\[date\\]\\[year\\]]').val(selectedClosingEndDate.year())
    $closingRuleForm.find('[name=closing_rule\\[endDate\\]\\[date\\]\\[month\\]]').val(parseInt(selectedClosingEndDate.month()) + 1)
    $closingRuleForm.find('[name=closing_rule\\[endDate\\]\\[date\\]\\[day\\]]').val(selectedClosingEndDate.date())
    $closingRuleForm.find('[name=closing_rule\\[endDate\\]\\[time\\]\\[hour\\]]').val(selectedClosingEndDate.hour())
    $closingRuleForm.find('[name=closing_rule\\[endDate\\]\\[time\\]\\[minute\\]]').val(selectedClosingEndDate.minute())
    let submitButton = document.getElementById('closing_rule_submit')
    submitButton.disabled = false
  }
}

function dateCellRender (date) {

  return (
    <li>{
      closingRules.map(function (item, index) {
        if (item.startDate.isBefore(date, 'day') && item.endDate.isAfter(date, 'day')) {
          return (<ol key={index} className="calendar-close">Fermé ce jour{item.reason && <span><br/>{item.reason}</span>}</ol>)
        } else if (item.startDate.isSame(date, 'day') && item.endDate.isSame(date, 'day')) {
          return (<ol key={index} className="calendar-close">Fermé de {item.startDate.format('HH:mm')} à {item.endDate.format('HH:mm')}{item.reason && <span><br/>{item.reason}</span>}</ol>)
        } else if (item.startDate.isSame(date, 'day') && item.endDate.isAfter(date, 'day')) {
          return (<ol key={index} className="calendar-close">Fermé à partir de {item.startDate.format('HH:mm')}{item.reason && <span><br/>{item.reason}</span>}</ol>)
        } else if (item.startDate.isBefore(date, 'day') && item.endDate.isSame(date, 'day')) {
          return (<ol key={index} className="calendar-close">Fermé jusqu'à {item.endDate.format('HH:mm')}{item.reason && <span><br/>{item.reason}</span>}</ol>)
        }
      })
    }
    </li>
  )
}

class ClosingRuleRangePicker extends React.Component {

  render () {
    return (
      <LocaleProvider locale={antdLocale}>
        <RangePicker
          format="DD/MM/YYYY à HH:mm"
          showTime={{format: 'HH:mm'}}
          className="form-control"
          style={rangeInputStyle}
          onChange={onChange}
        />
      </LocaleProvider>
    )
  }
}

const CalendarWithLocale = () => (
  <LocaleProvider locale={antdLocale}>
    <Calendar
      dateCellRender={dateCellRender}
    />
  </LocaleProvider>
)

render(
  <CalendarWithLocale />,
  document.getElementById('calendar-planning')
)

render(
  <ClosingRuleRangePicker />,
  document.getElementById('closing-rules-range-picker')
)
