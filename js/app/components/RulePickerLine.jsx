import React from 'react'

/*

  A component to edit a rule which will be evaluated as Symfony's expression language.

  Variables :
    - deliveryAddress : L'adresse de livraison
    - distance : La distance entre le point de retrait et le point de dépôt
    - weight : Le poids du colis transporté en grammes
    - vehicle : Le type de véhicule (bike ou cargo_bike)

  Examples :
    * distance in 0..3000
    * weight > 1000
    * in_zone(deliveryAddress, "paris_est")
    * vehicle == "cargo_bike"
*/

const typeToOperators = {
  'distance': ['<', '>', 'in'],
  'weight': ['<', '>', 'in'],
  'zone': ['in_zone'],
  'vehicle': ['=='],
}


class RulePickerLine extends React.Component {

  constructor (props) {
    super(props)

    let { line } = this.props,
      typeValue, // the variable the rule is built upon
      operatorValue, // the operator/function used to build the rule
      boundValues // the value(s) which complete the rule

    if (line) {
      [typeValue, operatorValue, boundValues] = this.parseInitialLine()
    } else {
      operatorValue = ''
      typeValue = ''
      boundValues = ['', '']
    }

    this.state = {
      boundValues: boundValues,
      operatorValue: operatorValue,
      typeValue: typeValue
    }

    this.onTypeSelect = this.onTypeSelect.bind(this)
    this.onOperatorSelect = this.onOperatorSelect.bind(this)
    this.renderBoundPicker = this.renderBoundPicker.bind(this)
    this.handleFirstBoundChange = this.handleFirstBoundChange.bind(this)
    this.handleSecondBoundChange = this.handleSecondBoundChange.bind(this)
    this.buildLine = this.buildLine.bind(this)
    this.delete = this.delete.bind(this)
  }

  parseInitialLine () {
    /*
      Parse the initial line - not the cleanest code ever..
     */

    // zone
    let zoneTest = /in_zone\(deliveryAddress, "([\w]+)"\)/.exec(this.props.line)
    if (zoneTest) {
      return ['zone', 'in_zone', [zoneTest[1]]]
    }

    // bike type
    let bikeTest = /(vehicle) == "(cargo_bike|bike)"/.exec(this.props.line)
    if (bikeTest) {
      return [bikeTest[1], '==', [bikeTest[2]]]
    }

    // in, > or < type
    let comparatorTest = /([\w]+) (in|<|>) ([\d]+)(\.\.([\d]+))?/.exec(this.props.line)
    if (comparatorTest) {
      return [comparatorTest[1], comparatorTest[2], [comparatorTest[3], comparatorTest[5]]]
    }
  }


  buildLine (state) {
    /*
      Build the expression line from the user's input stored in state.

      We pass explicitely the  state so we can compare previous & next state. Returns nothing if we can't build the line.
     */

    if (state.boundValues[0] && state.boundValues[1] && state.operatorValue == 'in') {
      return state.typeValue + ' in ' + state.boundValues[0] + '..' + state.boundValues[1]
    }
    else if (state.boundValues[0]) {
      switch (state.operatorValue) {
        case '>':
          return state.typeValue + ' > ' + state.boundValues[0]
        case '<':
          return state.typeValue + ' < ' + state.boundValues[0]
        case 'in_zone':
          return 'in_zone(deliveryAddress, "' + state.boundValues[0] + '")'
        case '==':
          return state.typeValue + ' == "' + state.boundValues[0] + '"'
      }
    }
  }

  componentDidUpdate (prevProps, prevState) {
    let line = this.buildLine(this.state)
    if (this.buildLine(prevState) !== line) {
      this.props.rulePicker.updateLine(this.props.index, line)
    }
  }

  handleFirstBoundChange (ev) {
    let boundValues = this.state.boundValues.slice()
    boundValues[0] = ev.target.value
    this.setState({boundValues})
  }

  handleSecondBoundChange (ev) {
    let boundValues = this.state.boundValues.slice()
    boundValues[1] = ev.target.value
    this.setState({boundValues})
  }

  onTypeSelect (ev) {
    ev.preventDefault()
    let typeValue = ev.target.value,
        operatorValue = typeToOperators[typeValue].length === 1 ? typeToOperators[typeValue][0] : ''
    this.setState({
      typeValue: typeValue,
      operatorValue: operatorValue,
      boundValues: ['', '']
    })
  }

  onOperatorSelect (ev) {
    ev.preventDefault()
    this.setState({operatorValue: ev.target.value})
  }

  delete (evt) {
    evt.preventDefault()
    this.props.rulePicker.deleteLine(this.props.index)
  }

  renderBoundPicker () {
    /*
     * Return the displayed input for bound selection
     */
    switch (this.state.operatorValue) {
      // zone
      case 'in_zone':
        return (
          <select onChange={this.handleFirstBoundChange} value={this.state.boundValues[0]} className="form-control input-sm">
              <option value="">-</option>
              { this.props.rulePicker.props.zones.map((item, index) => {
                  return (<option value={item} key={index}>{item}</option>)
                }
              )}
          </select>
        )
      // vehicle
      case '==':
        return (
          <select onChange={this.handleFirstBoundChange} value={this.state.boundValues[0]} className="form-control input-sm">
            <option value="">-</option>
            <option value="bike">Vélo</option>
            <option value="cargo_bike">Vélo Cargo</option>
          </select>
        )
      // weight, distance
      case 'in':
        return (
          <div className="row">
            <div className="col-md-6">
              <input className="form-control input-sm" value={this.state.boundValues[0]} onChange={this.handleFirstBoundChange} type="number"></input>
            </div>
            <div className="col-md-6">
              <input className="form-control input-sm" value={this.state.boundValues[1]} onChange={this.handleSecondBoundChange} type="number"></input>
            </div>
          </div>
        )
      case '>':
        return (
          (<input className="form-control input-sm" value={this.state.boundValues[0]} onChange={this.handleFirstBoundChange} type="number"></input>)
        )
      case '<':
        return (
          (<input className="form-control input-sm" value={this.state.boundValues[0]} onChange={this.handleFirstBoundChange} type="number"></input>)
        )
    }
  }

  render () {

    return (
      <div className="row">
        <div className="col-md-3 form-group">
          <select value={this.state.typeValue} onChange={this.onTypeSelect} className="form-control input-sm">
            <option value="">-</option>
            <option value="distance">Distance (m)</option>
            <option value="weight">Poids (g)</option>
            <option value="zone">Zone</option>
            <option value="vehicle">Type de vélo</option>
          </select>
        </div>
        <div className="col-md-3">
          {
            this.state.typeValue && (
              <select value={this.state.operatorValue} onChange={this.onOperatorSelect} className="form-control input-sm">
                <option value="">-</option>
                { typeToOperators[this.state.typeValue].map(function(operator, index) {
                    return (<option key={index} value={operator}>{operator}</option>)
                  })
                }
              </select>
            )
          }
        </div>
        <div className="col-md-5">
          {
            this.state.operatorValue && this.renderBoundPicker()
          }
        </div>
        <div className="col-md-1" onClick={this.delete}>
          <a href="#"><i className="fa fa-trash"></i></a>
        </div>
      </div>
    )
  }
}


export default RulePickerLine
