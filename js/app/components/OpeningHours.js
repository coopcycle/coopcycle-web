import React from 'react'
import moment from 'moment'
import _ from 'lodash'
import { ConfigProvider, Radio, TimePicker } from 'antd'
import classNames from 'classnames'
import PropTypes from 'prop-types'
import { withTranslation } from 'react-i18next'

import 'antd/lib/button/style/index.css'

import openingHourIntervalToReadable from '../restaurant/parseOpeningHours'
import TimeRange from '../utils/TimeRange'
import { timePickerProps } from '../utils/antd'
import { antdLocale } from '../i18n'
import { DragDropContext, Draggable, Droppable } from 'react-beautiful-dnd'

let minutes = []
for (let i = 0; i <= 60; i++) {
  if (0 !== i % 15) {
    minutes.push(i)
  }
}

class OpeningHours extends React.Component {

  constructor(props) {
    super(props)
    this.state = {
      weekdays: TimeRange.weekdaysShort(props.locale),
      rowsWithErrors: props.rowsWithErrors,
      rows: [],
      behavior: props.behavior || 'asap',
      rev: 0,
      disabled: Object.prototype.hasOwnProperty.call(props, 'disabled') ? props.disabled : false
    }
  }

  componentDidMount() {
    let rows = [ this.createRowData() ]
    if (this.props.value) {
      rows = _.map(this.props.value, (value) => this.parseOpeningHours(value))
    }
    this.setState({ rows })
    this.props.onLoad(this.rowsToString(rows))
  }

  parseOpeningHours(text) {

    const { days, start, end } = TimeRange.parse(text)
    const { weekdays } = this.state

    return {
      start: start,
      end: end,
      weekdays: _.map(weekdays, (weekday) => {
        return _.extend({ checked: _.includes(days, weekday.key) }, weekday)
      })
    }
  }

  setBehavior(behavior) {
    this.setState({ behavior })
  }

  enable() {
    this.setState({ disabled: false })
  }

  disable() {
    this.setState({ disabled: true })
  }

  onRangeChange(key, range) {
    if (!range) {
      return
    }

    const { rows } = this.state
    const row = rows[key]

    row.start = range[0].format('HH:mm')
    row.end = range[1].format('HH:mm')

    rows.splice(key, 1, row)
    this.setState({ rows })

    this.props.onChange(_.map(rows, (row) => this.rowToString(row)))
  }

  onCheckboxChange(key, weekdayKey, e) {

    const { rows } = this.state
    const row = rows[key]

    const weekday = _.findIndex(row.weekdays, (wd) => wd.key === weekdayKey)
    row.weekdays[weekday].checked = e.target.checked

    rows.splice(key, 1, row)
    this.setState({ rows })

    this.props.onChange(_.map(rows, (row) => this.rowToString(row)))
  }

  isWeekdayChecked(row, weekdayKey) {
    const weekday = _.findIndex(row.weekdays, (wd) => wd.key === weekdayKey)

    return row.weekdays[weekday].checked
  }

  rowToString(row) {
    let isPrevChecked = false
    let ranges = []
    let buffer = []
    _.each(row.weekdays, (weekday) => {
      if (weekday.checked) {
        if (buffer.length === 0) {
          ranges.push(buffer)
        }
        buffer.push(weekday.key)
      } else {
        if (isPrevChecked) {
          buffer = []
        }
      }
      isPrevChecked = weekday.checked
    })

    const days = _.map(ranges, (range) => {
      if (range.length > 1) {
        return range[0] + '-' + range[range.length - 1]
      } else {
        return range[0]
      }
    }).join(',')

    const hours = [row.start, row.end].join('-')

    return days + ' ' + hours
  }

  rowsToString(rows) {
    return _.map(rows, (row) => {
      return this.rowToString(row)
    })
  }

  toArray() {
    return this.rowsToString(this.state.rows)
  }

  disabledMinutes() {
    return minutes
  }

  renderRow(row, index) {

    const { weekdays, rev } = this.state
    const startValue = row.start ? moment(row.start + ':00', 'HH:mm:ss') : null
    const endValue = row.end ? moment(row.end + ':00', 'HH:mm:ss') : null

    return (
      <Draggable key={index} draggableId={index.toString()} index={index}>
        {(provided) => (
          <tr key={ `${index}-${rev}` } ref={provided.innerRef} { ...provided.draggableProps }
            className={ classNames({ 'danger': (-1 !== this.props.rowsWithErrors.indexOf(index)) }) }>
            <td width="50%">
              <span className="d-block">
                <i {...provided.dragHandleProps} className="fa fa-bars mr-3"></i>
                <TimePicker.RangePicker
                  { ...timePickerProps }
                  defaultValue={[startValue, endValue]}
                  disabledMinutes={this.disabledMinutes}
                  disabled={ this.state.disabled }
                  hideDisabledOptions
                  placeholder={["Heure","Heure"]}
                  onChange={(value) => {
                    this.onRangeChange(index, value)
                  }}
                />
              </span>
              <small className="text-muted">{ openingHourIntervalToReadable(this.rowToString(row), this.props.locale, this.state.behavior) }</small>
            </td>
            {_.map(weekdays, (weekday) => (
              <td key={weekday.key} className={ _.includes(['Sa', 'Su'], weekday.key) ? 'active text-center' : 'text-center'}>
                <input type="checkbox"
                  disabled={ this.state.disabled }
                  onChange={this.onCheckboxChange.bind(this, index, weekday.key)}
                  checked={this.isWeekdayChecked(row, weekday.key)}
                  className="form-input" />
              </td>
            ))}
            <td className="text-center">
              { !this.state.disabled && (
                <button type="button" className="button-icon" onClick={this.removeRow.bind(this, index)}>
                  <i className="fa fa-times"></i>
                </button>
              )}
              { this.state.disabled && (
                <button type="button" className="button-icon">
                  <i className="fa fa-times text-muted"></i>
                </button>
              )}
            </td>
          </tr>

        )}
      </Draggable>
    )
  }

  createRowData() {
    const { weekdays } = this.state
    return {
      start: '00:00',
      end: '23:59',
      weekdays: _.map(weekdays, (weekday) => {
        return _.extend({ checked: false }, weekday)
      })
    }
  }

  addRow(e) {
    e.preventDefault()
    const { rows } = this.state
    rows.push(this.createRowData())
    this.setState({ rows, rev: this.state.rev + 1 })
    this.props.onRowAdd()
  }

  removeRow(index, e) {
    e.preventDefault()
    const rows = this.state.rows.slice()
    rows.splice(index, 1)
    this.setState({ rows, rev: this.state.rev + 1 })
    this.props.onRowRemove(index)
  }

  reOrderRows(from, to) {
    const rows = Array.from(this.state.rows);
    const [removed] = rows.splice(from, 1);
    rows.splice(to, 0, removed);
    this.setState({ rows, rev: this.state.rev + 1 })
    this.props.onChange(_.map(rows, (row) => this.rowToString(row)))
  }

  render() {
    const { weekdays } = this.state
    return (
      <ConfigProvider locale={ antdLocale }>
        <div>
          <table className="table">
            <thead>
              <tr>
                <th>{this.props.t('DELIVERY_TIME_SLOTS')}</th>
                {_.map(weekdays, (weekday) => (
                  <th key={weekday.key} className={ _.includes(['Sa', 'Su'], weekday.key) ? 'active text-center' : 'text-center'}>{weekday.name}</th>
                ))}
                <th></th>
              </tr>
            </thead>
            <DragDropContext onDragEnd={({ source, destination }) => this.reOrderRows(source.index, destination.index)}>
              <Droppable direction='vertical' droppableId='droppable'>
                {({ droppableProps, innerRef }) => (
                  <tbody
                    {...droppableProps}
                    ref={innerRef}>
                    {_.map(this.state.rows, (row, index) => this.renderRow(row, index))}
                  </tbody>
                )}
              </Droppable>
            </DragDropContext>
          </table>
          <div className="d-flex flex-row align-items-center justify-content-between">
            <div>
              { this.props.withBehavior && (
                <Radio.Group
                  disabled={ this.state.disabled }
                  onChange={ (e) => {
                    this.setState({ behavior: e.target.value })
                    if (this.props.onChangeBehavior && typeof this.props.onChangeBehavior === 'function') {
                      this.props.onChangeBehavior(e.target.value)
                    }
                  }}
                  value={ this.state.behavior }>
                  <Radio value={ 'asap' }>{ this.props.t('OPENING_HOURS_BEHAVIOR.asap') }</Radio>
                  <Radio value={ 'time_slot' }>{ this.props.t('OPENING_HOURS_BEHAVIOR.time_slot') }</Radio>
                </Radio.Group>
              )}
            </div>
            <button className="btn btn-sm btn-success" onClick={ this.addRow.bind(this) } disabled={ this.state.disabled }>
              { this.props.t('ADD_BUTON') }
            </button>
          </div>
        </div>
      </ConfigProvider>
    )
  }
}

OpeningHours.defaultProps = {
  withBehavior: false
}

OpeningHours.propTypes = {
  onChangeBehavior: PropTypes.func,
  withBehavior: PropTypes.bool,
}

export default withTranslation('common', { withRef: true })(OpeningHours)
