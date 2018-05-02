import React from 'react'

export class DeliveryZonePicker extends React.Component {
  constructor (props) {
    super(props)

    const initial = this.parseInitialLine()

    this.state = {
      operator: initial[0],
      value: initial[1]
    }

    this.onOperatorSelect = this.onOperatorSelect.bind(this)
    this.renderInput = this.renderInput.bind(this)
    this.onChange = this.onChange.bind(this)
  }

  componentDidUpdate (prevProps, prevState) {
    const {operator, value} = this.state
    const {onExprChange} = this.props

    if (operator && value && (operator !== prevState.operator || value !== prevState.value)) {
      switch (operator) {
        case 'in_zone':
          onExprChange('in_zone(deliveryAddress, "' + value + '")')
          break
        case 'distance':
          onExprChange('distance < ' + value * 1000)
          break
      }
    }
  }

  onOperatorSelect (ev) {
    this.setState({operator: ev.target.value, value: ''})
  }

  onChange (ev) {
    this.setState({value: ev.target.value})
  }

  renderInput () {
    const {operator, value} = this.state
    const {zones} = this.props

    switch (operator) {
      case 'in_zone':
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
          <input type="number" value={value} onChange={this.onChange} className="form-control" />
        )
    }
  }

  parseInitialLine () {
    /*
      Parse the initial line
     */

    // zone
    let zoneTest = /in_zone\(deliveryAddress, ['|"](.+)['|"]\)/.exec(this.props.expression)
    if (zoneTest) {
      return ['in_zone', zoneTest[1]]
    }

    // max distance type
    let comparatorTest = /distance < ([\d]+)/.exec(this.props.expression)
    if (comparatorTest) {
      return ['distance', (comparatorTest[1] / 1000).toFixed(2)]
    }
  }


  render () {
    return (
      <div className="row">
        <div className="col-md-3 form-group">
          <select value={this.state.operator} onChange={this.onOperatorSelect} className="form-control">
            <option value="in_zone">Zone</option>
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