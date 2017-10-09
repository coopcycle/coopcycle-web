import React from 'react'
import moment from 'moment'
import _ from 'underscore'

import Button from 'antd/lib/button'
import TimePicker from 'antd/lib/time-picker'
import Switch from 'antd/lib/switch'
import LocaleProvider from 'antd/lib/locale-provider'
import frBE from 'antd/lib/locale-provider/fr_BE'

const timeFormat = 'HH:mm';

let minutes = [];
for (let i = 0; i <= 60; i++) {
  if (0 !== i % 15) {
    minutes.push(i)
  }
}

moment.locale('en');
const keys = _.map(moment.weekdaysShort(), (key) => key.substring(0, 2));

moment.locale('fr');
const weekdaysSorted = moment.weekdaysShort(true);
const weekdays = _.map(_.object(keys, moment.weekdaysShort()), (weekday, key) => {
  return {
    key: key,
    name: weekday
  }
}).sort((a, b) => weekdaysSorted.indexOf(a.name) < weekdaysSorted.indexOf(b.name) ? -1 : 1);

export default class extends React.Component {

  constructor(props) {

    super(props);

    const config = _.object(keys, weekdays);

    let rows = [ this.createRowData() ];
    if (this.props.value) {
      rows = _.map(this.props.value, (value) => this.parseOpeningHours(value))
    }

    this.state = {
      config: config,
      weekdays: weekdays,
      rows: rows
    };
  }

  parseOpeningHours(text) {

    const matches = text.match(/(Mo|Tu|We|Th|Fr|Sa|Su)+-?(Mo|Tu|We|Th|Fr|Sa|Su)?/gi);

    let days = [];
    _.each(matches, (match) => {
      const isRange = match.includes('-');
      if (isRange) {
        const [ start, end ] = match.split('-');
        let append = false;
        _.each(weekdays, (weekday) => {
          if (weekday.key === start) {
            append = true
          }
          if (append) {
            days.push(weekday.key)
          }
          if (weekday.key === end) {
            append = false
          }
        })
      } else {
        days.push(match)
      }
    });

    let start = '';
    let end = '';

    const hours = text.match(/([0-9]{2}:[0-9]{2})-([0-9]{2}:[0-9]{2})/gi);
    if (hours.length === 1) {
      [ start, end ] = hours[0].split('-')
    }

    return {
      start: start,
      end: end,
      weekdays: _.map(weekdays, (weekday) => {
        return _.extend({ checked: _.contains(days, weekday.key) }, weekday)
      })
    }
  }

  onStartChange(key, date, timeString) {

    const { rows } = this.state;
    const row = rows[key];

    row.start = timeString;

    rows.splice(key, 1, row);
    this.setState({ rows });

    this.props.onChange(_.map(rows, (row) => this.rowToString(row)))
  }

  onEndChange(key, date, timeString) {

    const { rows } = this.state;
    const row = rows[key];

    row.end = timeString;

    rows.splice(key, 1, row);
    this.setState({ rows });

    this.props.onChange(_.map(rows, (row) => this.rowToString(row)))
  }

  onCheckboxChange(key, weekdayKey, e) {

    const { rows } = this.state;
    const row = rows[key];

    const weekday = _.findIndex(row.weekdays, (wd) => wd.key === weekdayKey);
    row.weekdays[weekday].checked = e.target.checked;

    rows.splice(key, 1, row);
    this.setState({ rows });

    this.props.onChange(_.map(rows, (row) => this.rowToString(row)))
  }

  isWeekdayChecked(row, weekdayKey) {
    const weekday = _.findIndex(row.weekdays, (wd) => wd.key === weekdayKey);

    return row.weekdays[weekday].checked
  }

  rowToString(row) {
    let isPrevChecked = false;
    let ranges = [];
    let buffer = [];
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
    });

    const days = _.map(ranges, (range) => {
      if (range.length > 1) {
        return range[0] + '-' + range[range.length - 1]
      } else {
        return range[0]
      }
    }).join(',');

    const hours = [row.start, row.end].join('-');

    return days + ' ' + hours
  }

  toString() {
    var lines = _.map(this.state.rows, (row) => {
      return this.rowToString(row)
    });

    return lines
  }

  disabledMinutes(h) {
    return minutes;
  }

  renderRow(row, key) {

    const startValue = row.start ? moment(row.start + ':00', 'HH:mm:ss') : null;
    const endValue = row.end ? moment(row.end + ':00', 'HH:mm:ss') : null;

    return (
      <tr key={key}>
        <td>
          <LocaleProvider locale={frBE}>
            <TimePicker
              disabledMinutes={this.disabledMinutes}
              onChange={this.onStartChange.bind(this, key)}
              defaultValue={startValue}
              format={timeFormat}
              hideDisabledOptions
              placeholder="Heure"
              addon={panel => (
                <Button size="small" type="primary" onClick={() => panel.close()}>OK</Button>
              )}
            />
          </LocaleProvider>
          <LocaleProvider locale={frBE}>
            <TimePicker
              disabledMinutes={this.disabledMinutes}
              onChange={this.onEndChange.bind(this, key)}
              defaultValue={endValue}
              format={timeFormat}
              hideDisabledOptions
              placeholder="Heure"
              addon={panel => (
                <Button size="small" type="primary" onClick={() => panel.close()}>OK</Button>
              )}
            />
          </LocaleProvider>
        </td>
        {_.map(weekdays, (weekday) => (
          <td key={weekday.key} className={['Sa', 'Su'].includes(weekday.key) ? 'active text-center' : 'text-center'}>
            <input type="checkbox"
              onChange={this.onCheckboxChange.bind(this, key, weekday.key)}
              checked={this.isWeekdayChecked(row, weekday.key)}
              className="form-input" />
          </td>
        ))}
        <td>
          <button onClick={this.removeRow.bind(this, key)} type="button" className="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </td>
      </tr>
    )
  }

  createRowData() {
    return {
      start: '',
      end: '',
      weekdays: _.map(weekdays, (weekday) => {
        return _.extend({ checked: false }, weekday)
      })
    }
  }

  addRow(e) {
    e.preventDefault();
    const rows = this.state.rows;
    rows.push(this.createRowData());
    this.setState({ rows });
    this.props.onRowAdd()
  }

  removeRow(key, e) {
    e.preventDefault();
    let rows = this.state.rows;
    rows.splice(key, 1);
    this.setState({ rows });
    this.props.onRowRemove(key)
  }

  render() {

    return (
      <div>
        <table className="table">
          <thead>
            <tr>
              <th>Horaire</th>
              {_.map(weekdays, (weekday) => (
                <th key={weekday.key} className={['Sa', 'Su'].includes(weekday.key) ? 'active text-center' : 'text-center'}>{weekday.name}</th>
              ))}
              <th></th>
            </tr>
          </thead>
          <tbody>
            {_.map(this.state.rows, (row, key) => this.renderRow(row, key))}
          </tbody>
        </table>
        <button className="btn btn-sm btn-success" onClick={this.addRow.bind(this)}>Ajouter</button>
      </div>
    );
  }
}