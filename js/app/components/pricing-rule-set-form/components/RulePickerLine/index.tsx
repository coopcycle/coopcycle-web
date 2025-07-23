import React from 'react'
import _ from 'lodash'
import isScalar from 'locutus/php/var/is_scalar'
import { withTranslation } from 'react-i18next'
import numbro from 'numbro'

import ZonePicker from './ZonePicker'
import PackagePicker from './PackagePicker'
import TimeSlotPicker from './TimeSlotPicker'
import {
  numericTypes,
  isNum,
} from '../../../../delivery/pricing/expression-builder'
import { RulePickerTypeSelect } from './RulePickerTypeSelect'

/*

  A component to edit a rule which will be evaluated as Symfony's expression language.

  Variables :
    - pickup.address : L'adresse de retrait
    - dropoff.address : L'adresse de dépôt
    - distance : La distance entre le point de retrait et le point de dépôt
    - weight : Le poids du colis transporté en grammes
    - vehicle : Le type de véhicule (bike ou cargo_bike)

  Examples :
    * distance in 0..3000
    * weight > 1000
    * in_zone(pickup.address, "paris_est")
    * vehicle == "cargo_bike"
*/

const typeToOperators = {
  distance: ['<', '>', 'in'],
  weight: ['<', '>', 'in'],
  vehicle: ['=='],
  'pickup.address': ['in_zone', 'out_zone'],
  'dropoff.address': ['in_zone', 'out_zone'],
  'diff_days(pickup)': ['==', '<', '>', 'in'],
  'diff_hours(pickup)': ['==', '<', '>'],
  'dropoff.doorstep': ['=='],
  packages: ['containsAtLeastOne'],
  'order.itemsTotal': ['==', '<', '>', 'in'],
  'packages.totalVolumeUnits()': ['<', '>', 'in'],
  "time_range_length(pickup, 'hours')": ['<', '>', 'in'],
  "time_range_length(dropoff, 'hours')": ['<', '>', 'in'],
  'task.type': ['=='],
  time_slot: ['==', '!='],
}

const isK = type => type === 'distance' || type === 'weight'
const isDecimals = type =>
  isK(type) ||
  [
    "time_range_length(pickup, 'hours')",
    "time_range_length(dropoff, 'hours')",
  ].includes(type)

const formatValue = (value, type) => {
  if (!_.includes(numericTypes, type)) {
    return value
  }

  if (value === '') {
    return 0
  }

  return numbro.unformat(value) * (isK(type) ? 1000 : 1)
}

const getStepForType = type => {
  // As it returns float, it will never work when comparing to floats
  // https://github.com/coopcycle/coopcycle-web/issues/5002
  if (type === 'packages.totalVolumeUnits()') {
    return '1'
  }

  return '0.1'
}

type Props = {
  index: number
  ruleTarget: string
  type: string
  operator: string
  value: string | string[]
  onUpdate: (index: number, line: { left: string; operator: string; right: string | string[] }) => void
  onDelete: (index: number) => void
  testID?: string
}

class RulePickerLine extends React.Component<Props> {
  constructor(props: Props) {
    super(props)

    this.state = {
      type: props.type || '', // the variable the rule is built upon
      operator: props.operator || '', // the operator/function used to build the rule
      value: isScalar(props.value) ? `${props.value}` : props.value || '', // the value(s) which complete the rule
    }

    this.onTypeSelect = this.onTypeSelect.bind(this)
    this.onOperatorSelect = this.onOperatorSelect.bind(this)
    this.renderBoundPicker = this.renderBoundPicker.bind(this)
    this.handleFirstBoundChange = this.handleFirstBoundChange.bind(this)
    this.handleSecondBoundChange = this.handleSecondBoundChange.bind(this)
    this.handleValueChange = this.handleValueChange.bind(this)
    this.delete = this.delete.bind(this)
  }

  componentDidUpdate(prevProps, prevState) {
    if (!_.isEqual(this.state, prevState)) {
      this.props.onUpdate(this.props.index, {
        left: this.state.type,
        operator: this.state.operator,
        right: this.state.value,
      })
    }
  }

  handleFirstBoundChange(ev) {
    const { type } = this.state
    let value = this.state.value.slice()
    value[0] = ev.target.value * (isK(type) ? 1000 : 1)
    this.setState({ value })
  }

  handleSecondBoundChange(ev) {
    const { type } = this.state
    let value = this.state.value.slice()
    value[1] = ev.target.value * (isK(type) ? 1000 : 1)
    this.setState({ value })
  }

  handleValueChange(ev) {
    const { type, value } = this.state
    if (!Array.isArray(value)) {
      this.setState({
        value: formatValue(ev.target.value, type),
      })
    }
  }

  onTypeSelect(ev) {
    ev.preventDefault()

    const type = ev.target.value

    const operators = typeToOperators[type]

    if (!operators) {
      return
    }

    const operator = operators.length === 1 ? operators[0] : ''
    this.setState({
      type,
      operator,
      value: '',
    })
  }

  onOperatorSelect(ev) {
    ev.preventDefault()

    const operator = ev.target.value

    let state = { operator }

    if ('in' === operator) {
      state.value = ['0', isK(this.state.type) ? '1000' : '1']
    }

    if (_.includes(['==', '!=', '<', '>'], operator)) {
      state.value = isNum(this.state.type) ? '0' : ''
    }

    this.setState(state)
  }

  delete(evt) {
    evt.preventDefault()
    this.props.onDelete(this.props.index)
  }

  renderNumberInput(k = false, decimals = false) {
    let props = {}
    if (decimals) {
      props = {
        ...props,
        step: '.1',
      }
    }

    return (
      <input
        data-testid="condition-number-input"
        className="form-control input-sm"
        value={k ? this.state.value / 1000 : this.state.value}
        onChange={this.handleValueChange}
        type="number"
        min="0"
        required
        {...props}></input>
    )
  }

  renderBooleanInput() {
    return (
      <select
        onChange={this.handleValueChange}
        value={this.state.value}
        className="form-control input-sm">
        <option value="false">{this.props.t('NO')}</option>
        <option value="true">{this.props.t('YES')}</option>
      </select>
    )
  }

  renderBoundPicker() {
    /*
     * Return the displayed input for bound selection
     */
    switch (this.state.operator) {
      // zone
      case 'in_zone':
      case 'out_zone':
        return (
          <ZonePicker
            onChange={this.handleValueChange}
            value={this.state.value}
          />
        )

      case '==':
      case '!=':
        if (this.state.type === 'vehicle') {
          return (
            <select
              onChange={this.handleValueChange}
              value={this.state.value}
              className="form-control input-sm">
              <option value="">-</option>
              <option value="bike">{this.props.t('PRICING_RULE_PICKER_VEHICLE_BIKE')}</option>
              <option value="cargo_bike">{this.props.t('PRICING_RULE_PICKER_VEHICLE_CARGO_BIKE')}</option>
            </select>
          )
        }

        if (this.state.type === 'task.type') {
          return (
            <select
              data-testid="condition-task-type-select"
              onChange={this.handleValueChange}
              value={this.state.value}
              className="form-control input-sm">
              <option value="">-</option>
              <option value="PICKUP">{this.props.t('DELIVERY_PICKUP')}</option>
              <option value="DROPOFF">{this.props.t('DELIVERY_DROPOFF')}</option>
            </select>
          )
        }

        if (this.state.type === 'dropoff.doorstep') {
          return this.renderBooleanInput()
        }

        if (this.state.type === 'time_slot') {
          return (
            <TimeSlotPicker
              onChange={this.handleValueChange}
              value={this.state.value}
            />
          )
        }

        return this.renderNumberInput(
          isK(this.state.type),
          isDecimals(this.state.type),
        )
      // weight, distance, diff_days(pickup)
      case 'in':
        return (
          <div className="d-flex justify-content-between">
            <div className="mr-2">
              <input
                className="form-control input-sm"
                value={this.state.value[0] / (isK(this.state.type) ? 1000 : 1)}
                onChange={this.handleFirstBoundChange}
                type="number"
                min="0"
                required
                step={getStepForType(this.state.type)}></input>
            </div>
            <div>
              <input
                className="form-control input-sm"
                value={this.state.value[1] / (isK(this.state.type) ? 1000 : 1)}
                onChange={this.handleSecondBoundChange}
                type="number"
                min="0"
                required
                step={getStepForType(this.state.type)}></input>
            </div>
          </div>
        )
      case '<':
      case '>':
        return this.renderNumberInput(
          isK(this.state.type),
          isDecimals(this.state.type),
        )
      case 'containsAtLeastOne':
        return (
          <PackagePicker
            onChange={this.handleValueChange}
            value={this.state.value}
          />
        )
    }
  }

  render() {
    return (
      <tr data-testid={this.props.testID}>
        <td>
          <RulePickerTypeSelect
            ruleTarget={this.props.ruleTarget}
            type={this.state.type}
            onTypeSelect={this.onTypeSelect}
          />
        </td>
        <td width="20%">
          {this.state.type && (
            <select
              data-testid="condition-operator-select"
              value={this.state.operator}
              onChange={this.onOperatorSelect}
              className="form-control input-sm">
              <option value="">-</option>
              {typeToOperators[this.state.type].map(function (operator, index) {
                return (
                  <option key={index} value={operator}>
                    {operator}
                  </option>
                )
              })}
            </select>
          )}
        </td>
        <td width="25%">{this.state.operator && this.renderBoundPicker()}</td>
        <td className="text-right" onClick={this.delete}>
          <a href="#">
            <i className="fa fa-trash"></i>
          </a>
        </td>
      </tr>
    )
  }
}

export default withTranslation()(RulePickerLine)
