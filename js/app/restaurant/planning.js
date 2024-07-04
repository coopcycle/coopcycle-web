import React from 'react'
import { render } from 'react-dom'
import { ConfigProvider, Calendar, DatePicker } from 'antd'
import moment from 'moment'
import _ from 'lodash'
import axios from 'axios'

import 'antd/es/select/style/index.css'

import { antdLocale, localeDetector } from '../i18n'

const baseURL = location.protocol + '//' + location.host

const { RangePicker } = DatePicker

let locale = localeDetector(),
  selectedClosingStartDate,
  selectedClosingEndDate,
  $closingRuleForm = $('#closing-rules-form'),
  closingRules = window.AppData.closingRules || []

let token

closingRules = _.map(closingRules, function (item) {
  return {
    ...item,
    startDate: moment(item.startDate),
    endDate: moment(item.endDate),
  }
})

moment.locale(locale)

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

class ClosingRuleRangePicker extends React.Component {

  render () {
    return (
      <ConfigProvider locale={antdLocale}>
        <RangePicker
          format="DD/MM/YYYY à HH:mm"
          showTime={{ format: 'HH:mm' }}
          onChange={onChange}
        />
      </ConfigProvider>
    )
  }
}

class ClosingRulesCalendar extends React.Component {

  constructor (props) {
    super(props)
    this.state = {
      rules: props.rules
    }
  }

  onDeleteClick(closingRule) {
    if (window.confirm('Are you sure ?')) {
      axios({
        method: 'DELETE',
        url: `${baseURL}/api/opening_hours_specifications/${closingRule.id}`,
        headers: {
          Authorization: `Bearer ${token}`
        }
      })
        .then(() => {
          // TODO Check if response is OK
          this.setState({
            rules: _.filter(this.state.rules, function(oneClosingRule) {
              return oneClosingRule.id !== closingRule.id
            })
          })
        })
    }
  }

  dateCellRender (date) {

    return (
      <li>{
        this.state.rules.map((item, index) => {
          if (item.startDate.isBefore(date, 'day') && item.endDate.isAfter(date, 'day')) {
            return (
              <ol key={index} className="calendar-close">
                <button type="button" className="close" onClick={()=> this.onDeleteClick(item)}><span>&times;</span></button>
                <span>Fermé ce jour</span>
                { item.reason && <span><br/>{item.reason}</span> }
              </ol>
            )
          } else if (item.startDate.isSame(date, 'day') && item.endDate.isSame(date, 'day')) {
            return (
              <ol key={index} className="calendar-close">
                <button type="button" className="close" onClick={()=> this.onDeleteClick(item)}><span>&times;</span></button>
                <span>Fermé de {item.startDate.format('HH:mm')} à {item.endDate.format('HH:mm')}</span>
                {item.reason && <span><br/>{item.reason}</span>}
              </ol>
            )
          } else if (item.startDate.isSame(date, 'day') && item.endDate.isAfter(date, 'day')) {
            return (
              <ol key={index} className="calendar-close">
                <button type="button" className="close" onClick={()=> this.onDeleteClick(item)}><span>&times;</span></button>
                <span>Fermé à partir de {item.startDate.format('HH:mm')}</span>
                {item.reason && <span><br/>{item.reason}</span>}
              </ol>
            )
          } else if (item.startDate.isBefore(date, 'day') && item.endDate.isSame(date, 'day')) {
            return (
              <ol key={index} className="calendar-close">
                <button type="button" className="close" onClick={()=> this.onDeleteClick(item)}><span>&times;</span></button>
                <span>{ `Fermé jusqu'à ${item.endDate.format('HH:mm')}` }</span>
                {item.reason && <span><br/>{item.reason}</span>}
              </ol>
            )
          }
        })
      }
      </li>
    )
  }

  render () {
    return (
      <ConfigProvider locale={antdLocale}>
        <Calendar dateCellRender={this.dateCellRender.bind(this)} />
      </ConfigProvider>
    )
  }
}

$.getJSON(window.Routing.generate('profile_jwt'))
  .then(result => {
    token = result.jwt
    render(
      <ClosingRulesCalendar rules={closingRules} />,
      document.getElementById('calendar-planning')
    )
    render(
      <ClosingRuleRangePicker />,
      document.getElementById('closing-rules-range-picker')
    )
  })
