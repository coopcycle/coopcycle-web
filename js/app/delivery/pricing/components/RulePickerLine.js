import React, { useMemo } from 'react'
import _ from 'lodash'
import isScalar from 'locutus/php/var/is_scalar'
import { useTranslation, withTranslation } from 'react-i18next'
import numbro from 'numbro'

import { numericTypes, isNum } from './RulePicker'
import './RulePicker.scss'

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
  'distance': ['<', '>', 'in'],
  'weight': ['<', '>', 'in'],
  'vehicle': ['=='],
  'pickup.address': ['in_zone', 'out_zone'],
  'dropoff.address': ['in_zone', 'out_zone'],
  'diff_days(pickup)': ['==', '<', '>', 'in'],
  'diff_hours(pickup)': ['==', '<', '>'],
  'dropoff.doorstep': ['=='],
  'packages': ['containsAtLeastOne'],
  'order.itemsTotal': ['==', '<', '>', 'in'],
  'packages.totalVolumeUnits()': ['<', '>', 'in'],
  'time_range_length(pickup, \'hours\')': ['<', '>', 'in'],
  'time_range_length(dropoff, \'hours\')': ['<', '>', 'in'],
  'task.type': ['=='],
}

const isK = type => type === 'distance' || type === 'weight'
const isDecimals = type => isK(type) || ["time_range_length(pickup, 'hours')", "time_range_length(dropoff, 'hours')"].includes(type)

const formatValue = (value, type) => {
  if (!_.includes(numericTypes, type)) {

    return value
  }

  if (value === '') {
    return 0
  }

  return numbro.unformat(value) * (isK(type) ? 1000 : 1)
}

const DELIVERY_TYPES = [
  { name: 'distance' },
  { name: 'pickup.address' },
  { name: 'dropoff.address' },
  { name: 'diff_hours(pickup)' },
  { name: 'diff_days(pickup)' },
  { name: "time_range_length(pickup, 'hours')" },
  { name: "time_range_length(dropoff, 'hours')" },
  { name: 'weight' },
  { name: 'packages' },
  { name: 'packages.totalVolumeUnits()' },
  { name: 'order.itemsTotal' },
  { name: 'vehicle', deprecated: true },
  { name: 'dropoff.doorstep', deprecated: true },
  { name: 'task.type', deprecated: true },
]

const TASK_TYPES = [
  { name: 'task.type' },
  { name: 'pickup.address' },
  { name: 'dropoff.address' },
  { name: "time_range_length(pickup, 'hours')" },
  { name: "time_range_length(dropoff, 'hours')" },
  { name: 'weight' },
  { name: 'packages' },
  { name: 'packages.totalVolumeUnits()' },
  { name: 'diff_hours(pickup)', deprecated: true },
  { name: 'diff_days(pickup)', deprecated: true },
  { name: 'vehicle', deprecated: true },
  { name: 'dropoff.doorstep', deprecated: true },
  { name: 'distance', deprecated: true },
  { name: 'order.itemsTotal', deprecated: true },
]

const LEGACY_TARGET_DYNAMIC_TYPES = [
  { name: 'distance' },
  { name: 'pickup.address' },
  { name: 'dropoff.address' },
  { name: 'diff_hours(pickup)' },
  { name: 'diff_days(pickup)' },
  { name: "time_range_length(pickup, 'hours')" },
  { name: "time_range_length(dropoff, 'hours')" },
  { name: 'weight' },
  { name: 'packages' },
  { name: 'packages.totalVolumeUnits()' },
  { name: 'order.itemsTotal' },
  { name: 'vehicle' },
  { name: 'dropoff.doorstep' },
  { name: 'task.type' },
]

function RulePickerType({ ruleTarget, type }) {
  const { t } = useTranslation()

  const label = useMemo(() => {
    switch (type.name) {
      case 'pickup.address':
        return t('RULE_PICKER_LINE_PICKUP_ADDRESS')
      case 'dropoff.address':
        return t('RULE_PICKER_LINE_DROPOFF_ADDRESS')
      case 'diff_hours(pickup)':
        return t('RULE_PICKER_LINE_PICKUP_DIFF_HOURS')
      case 'diff_days(pickup)':
        return t('RULE_PICKER_LINE_PICKUP_DIFF_DAYS')
      case "time_range_length(pickup, 'hours')":
        return t('RULE_PICKER_LINE_PICKUP_TIME_RANGE_LENGTH_HOURS')
      case "time_range_length(dropoff, 'hours')":
        return t('RULE_PICKER_LINE_DROPOFF_TIME_RANGE_LENGTH_HOURS')
      case 'weight':
        switch (ruleTarget) {
          case 'DELIVERY':
            return t('RULE_PICKER_LINE_WEIGHT_TARGET_DELIVERY')
          case 'TASK':
            return t('RULE_PICKER_LINE_WEIGHT_TARGET_TASK')
          case 'LEGACY_TARGET_DYNAMIC':
            return t('RULE_PICKER_LINE_WEIGHT')
          default:
            return t('RULE_PICKER_LINE_WEIGHT')
        }
      case 'packages': {
        switch (ruleTarget) {
          case 'DELIVERY':
            return t('RULE_PICKER_LINE_PACKAGES_TARGET_DELIVERY')
          case 'TASK':
            return t('RULE_PICKER_LINE_PACKAGES_TARGET_TASK')
          case 'LEGACY_TARGET_DYNAMIC':
            return t('RULE_PICKER_LINE_PACKAGES')
          default:
            return t('RULE_PICKER_LINE_PACKAGES')
        }
      }
      case 'packages.totalVolumeUnits()':
        switch (ruleTarget) {
          case 'DELIVERY':
            return t('RULE_PICKER_LINE_VOLUME_UNITS_TARGET_DELIVERY')
          case 'TASK':
            return t('RULE_PICKER_LINE_VOLUME_UNITS_TARGET_TASK')
          case 'LEGACY_TARGET_DYNAMIC':
            return t('RULE_PICKER_LINE_VOLUME_UNITS')
          default:
            return t('RULE_PICKER_LINE_VOLUME_UNITS')
        }
      case 'task.type':
        return t('RULE_PICKER_LINE_TASK_TYPE')
      case 'distance':
        return t('RULE_PICKER_LINE_DISTANCE')
      case 'order.itemsTotal':
        return t('RULE_PICKER_LINE_ORDER_ITEMS_TOTAL')
      case 'vehicle':
        return t('RULE_PICKER_LINE_BIKE_TYPE')
      case 'dropoff.doorstep':
        return t('RULE_PICKER_LINE_DROPOFF_DOORSTEP')
      default:
        return type
    }
  }, [ruleTarget, type, t])

  return (
    <option value={type.name}>
      {label}
      {type.deprecated && ` (${t('RULE_PICKER_LINE_OPTGROUP_DEPRECATED')})`}
    </option>
  )
}

function RulePickerTypeSelect({ruleTarget, type, onTypeSelect}) {
  const { t } = useTranslation()

  const types = useMemo(() => {
    switch (ruleTarget) {
      case 'DELIVERY':
        return DELIVERY_TYPES
      case 'TASK':
        return TASK_TYPES
      case 'LEGACY_TARGET_DYNAMIC':
        return LEGACY_TARGET_DYNAMIC_TYPES
      default:
        return []
    }
  }, [ruleTarget])

  const nonDeprecatedTypes = useMemo(() => {
    return types.filter(type => !type.deprecated)
  }, [types])

  const deprecatedTypes = useMemo(() => {
    return types.filter(type => type.deprecated)
  }, [types])

  return (
    <select
      value={type}
      onChange={onTypeSelect}
      className="form-control input-sm">
      <option value="">-</option>
      {nonDeprecatedTypes.map((type, index) => (
        <RulePickerType
          ruleTarget={ruleTarget}
          type={type}
          key={`nonDeprecatedTypes-${index}`}
        />
      ))}
      {deprecatedTypes.length > 0 && (
        <optgroup label={t('RULE_PICKER_LINE_OPTGROUP_DEPRECATED')}>
          {deprecatedTypes.map((type, index) => (
            <RulePickerType
              ruleTarget={ruleTarget}
              type={type}
              key={`deprecatedTypes-${index}`}
            />
          ))}
        </optgroup>
      )}
    </select>
  )
}

class RulePickerLine extends React.Component {

  constructor (props) {
    super(props)

    this.state = {
      type: props.type || '',         // the variable the rule is built upon
      operator: props.operator || '', // the operator/function used to build the rule
      value: isScalar(props.value) ? `${props.value}` : (props.value || ''),       // the value(s) which complete the rule
    }

    this.onTypeSelect = this.onTypeSelect.bind(this)
    this.onOperatorSelect = this.onOperatorSelect.bind(this)
    this.renderBoundPicker = this.renderBoundPicker.bind(this)
    this.handleFirstBoundChange = this.handleFirstBoundChange.bind(this)
    this.handleSecondBoundChange = this.handleSecondBoundChange.bind(this)
    this.handleValueChange = this.handleValueChange.bind(this)
    this.delete = this.delete.bind(this)
  }

  componentDidUpdate (prevProps, prevState) {
    if (!_.isEqual(this.state, prevState)) {
      this.props.onUpdate(this.props.index, {
        left: this.state.type,
        operator: this.state.operator,
        right: this.state.value
      })
    }
  }

  handleFirstBoundChange (ev) {
    const { type } = this.state
    let value = this.state.value.slice()
    value[0] = ev.target.value * (isK(type) ? 1000 : 1)
    this.setState({ value })
  }

  handleSecondBoundChange (ev) {
    const { type } = this.state
    let value = this.state.value.slice()
    value[1] = ev.target.value * (isK(type) ? 1000 : 1)
    this.setState({ value })
  }

  handleValueChange (ev) {
    const { type, value } = this.state
    if (!Array.isArray(value)) {
      this.setState({
        value: formatValue(ev.target.value, type)
      })
    }
  }

  onTypeSelect (ev) {
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
      value: ''
    })
  }

  onOperatorSelect (ev) {

    ev.preventDefault()

    const operator = ev.target.value

    let state = { operator }

    if ('in' === operator) {
      state.value = ['0', isK(this.state.type) ? '1000' : '1']
    }

    if (_.includes(['==', '<', '>'], operator)) {
      state.value = isNum(this.state.type) ? '0' : ''
    }

    this.setState(state)
  }

  delete (evt) {
    evt.preventDefault()
    this.props.onDelete(this.props.index)
  }

  renderNumberInput(k = false, decimals = false) {

    let props = {}
    if (decimals) {
      props = {
        ...props,
        step: '.1'
      }
    }

    return (
      <input className="form-control input-sm"
        value={ k ? (this.state.value / 1000) : this.state.value }
        onChange={ this.handleValueChange }
        type="number" min="0" required { ...props }></input>
    )
  }

  renderBooleanInput() {

    return (
      <select onChange={this.handleValueChange} value={this.state.value} className="form-control input-sm">
        <option value="false">No</option>
        <option value="true">Yes</option>
      </select>
    )
  }

  renderBoundPicker () {
    /*
     * Return the displayed input for bound selection
     */
    switch (this.state.operator) {
    // zone
    case 'in_zone':
    case 'out_zone':
      return (
        <select onChange={this.handleValueChange} value={this.state.value} className="form-control input-sm">
          <option value="">-</option>
          { this.props.zones.map((item, index) => {
            return (<option value={item} key={index}>{item}</option>)
          })}
        </select>
      )
    // vehicle, diff_days(pickup)
    case '==':

      if (this.state.type === 'vehicle') {
        return (
          <select onChange={this.handleValueChange} value={this.state.value} className="form-control input-sm">
            <option value="">-</option>
            <option value="bike">Vélo</option>
            <option value="cargo_bike">Vélo Cargo</option>
          </select>
        )
      }

      if (this.state.type === 'task.type') {
        return (
          <select onChange={this.handleValueChange} value={this.state.value} className="form-control input-sm">
            <option value="">-</option>
            <option value="PICKUP">Pickup</option>
            <option value="DROPOFF">Dropoff</option>
          </select>
        )
      }

      if (this.state.type === 'dropoff.doorstep') {
        return this.renderBooleanInput()
      }

      return this.renderNumberInput(isK(this.state.type), isDecimals(this.state.type))
    // weight, distance, diff_days(pickup)
    case 'in':
      return (
        <div className="d-flex justify-content-between">
          <div className="mr-2">
            <input className="form-control input-sm" value={ (this.state.value[0] / (isK(this.state.type) ? 1000 : 1))  } onChange={this.handleFirstBoundChange} type="number" min="0" required step="0.1"></input>
          </div>
          <div>
            <input className="form-control input-sm" value={ (this.state.value[1] / (isK(this.state.type) ? 1000 : 1)) } onChange={this.handleSecondBoundChange} type="number" min="0" required step="0.1"></input>
          </div>
        </div>
      )
    case '<':
    case '>':
      return this.renderNumberInput(isK(this.state.type), isDecimals(this.state.type))
    case 'containsAtLeastOne':
      return (
        <select onChange={this.handleValueChange} value={this.state.value} className="form-control input-sm">
          <option value="">-</option>
          { this.props.packages.map((item, index) => {
            return (<option value={item} key={index}>{item}</option>)
          })}
        </select>
      )
    }
  }

  render () {

    return (
      <tr data-testid={this.props.testID}>
        <td>
          <RulePickerTypeSelect ruleTarget={this.props.ruleTarget} type={this.state.type} onTypeSelect={this.onTypeSelect} />
        </td>
        <td width="20%">
          {
            this.state.type && (
              <select value={this.state.operator} onChange={this.onOperatorSelect} className="form-control input-sm">
                <option value="">-</option>
                { typeToOperators[this.state.type].map(function(operator, index) {
                  return (<option key={index} value={operator}>{operator}</option>)
                })}
              </select>
            )
          }
        </td>
        <td width="25%">
          {
            this.state.operator && this.renderBoundPicker()
          }
        </td>
        <td className="text-right" onClick={this.delete}>
          <a href="#"><i className="fa fa-trash"></i></a>
        </td>
      </tr>
    )
  }
}

export default withTranslation()(RulePickerLine)
