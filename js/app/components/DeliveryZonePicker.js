import React from 'react'
import parsePricingRule from '../delivery/pricing-rule-parser'

export default class DeliveryZonePicker extends React.Component {
  constructor (props) {
    super(props)

    let left, type, value
    if (this.props.expression) {
      const [ result ] = parsePricingRule(this.props.expression)
      left = result.left
      value = result.right
    }

    if (left && left === 'distance') {
      type = 'distance'
      value = (value / 1000).toFixed(2)
    } else {
      type = 'zone'
    }

    this.state = {
      type: type || '',
      value: value || ''
    }

    this.onTypeSelect = this.onTypeSelect.bind(this)
    this.renderInput = this.renderInput.bind(this)
    this.onChange = this.onChange.bind(this)
  }

  componentDidUpdate (prevProps, prevState) {
    const {type, value} = this.state
    const {onExprChange} = this.props

    if (type && value && (type !== prevState.type || value !== prevState.value)) {
      switch (type) {
      case 'zone':
        onExprChange(`in_zone(dropoff.address, "${value}")`)
        break
      case 'distance':
        onExprChange(`distance < ${value * 1000}`)
        break
      }
    }
  }

  onTypeSelect (ev) {
    this.setState({type: ev.target.value, value: ''})
  }

  onChange (ev) {
    this.setState({value: ev.target.value})
  }

  renderInput () {
    const {type, value} = this.state
    const {zones} = this.props

    switch (type) {
    case 'zone':
      return (
        <select value={value} onChange={this.onChange} className="form-control">
          <option value="">-</option>
          {
            zones.map((item, index) => {
              return <option key={index} value={item}>{item}</option>
            })
          }
        </select>
      )
    case 'distance':
      return (
        <input type="number" value={ value } onChange={this.onChange} className="form-control" min="0" step=".5" required />
      )
    }
  }

  render () {
    return (
      <div className="row">
        <div className="col-md-3 form-group">
          <select value={this.state.type} onChange={this.onTypeSelect} className="form-control">
            <option value="zone">Zone</option>
            <option value="distance">Distance (km)</option>
          </select>
        </div>
        <div className="col-md-3 form-group">
          { this.renderInput() }
        </div>
      </div>
    )
  }
}
