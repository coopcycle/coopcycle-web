import React from 'react'
import PropTypes from 'prop-types';
import RulePickerLine from "./RulePickerLine.jsx"


class RulePicker extends React.Component {

  constructor (props) {
      super(props)

      this.state = {
        lines: this.props.expression.split(' and '),
      }

    this.addLine = this.addLine.bind(this)
  }

  addLine (evt) {
    evt.preventDefault()
    let state = {...this.state}
    state.lines.push('')
    this.setState({lines: state.lines})
  }

  deleteLine(index) {
    let lines = this.state.lines.slice()
    lines = lines.slice(0, index).concat(lines.slice(index + 1))
    this.setState({lines})
  }

  updateLine(index, line) {
    let lines = this.state.lines.slice()
    lines[index] = line
    this.setState({lines})
  }

  componentDidUpdate () {
    this.props.onExpressionChange(this.state.lines.filter(function(item) {return item}).join(' and '))
  }

  render () {
    console.log(this.state.lines)
    return (
      <div className="rule-picker">
        { this.state.lines.map((line, index) => <RulePickerLine key={index} index={index} rulePicker={this} line={line} />) }
        <div className="row">
          <div className="col-xs-12 text-right">
            <button className="btn btn-xs btn-primary" onClick={this.addLine}>
              <i className="fa fa-plus"></i> Ajouter une condition
            </button>
          </div>
        </div>
      </div>
    )
  }
}

RulePicker.propTypes = {
  expression: PropTypes.string.isRequired,
  onExpressionChange: PropTypes.func.isRequired,
  zones: PropTypes.arrayOf(PropTypes.string)
}

export default RulePicker
