import React from 'react'
import PropTypes from 'prop-types'
import { withTranslation } from 'react-i18next'

import RulePickerLine from './RulePickerLine'
import { linesToString } from '../../../delivery/pricing/expression-builder'
import { parseAST } from '../../../delivery/pricing/pricing-rule-parser'

class RulePicker extends React.Component {
  constructor(props) {
    super(props)

    this.state = {
      lines: this.props.expressionAST ? parseAST(this.props.expressionAST) : [],
      // This is used as a "revision counter",
      // to create an accurate React key prop
      rev: 0,
    }

    this.addLine = this.addLine.bind(this)
    this.updateLine = this.updateLine.bind(this)
    this.deleteLine = this.deleteLine.bind(this)
  }

  addLine(evt) {
    evt.preventDefault()
    let lines = this.state.lines.slice()
    lines.push({
      type: '',
      operator: '',
      value: '',
    })
    this.setState({
      lines,
      rev: this.state.rev + 1,
    })
  }

  deleteLine(index) {
    let lines = this.state.lines.slice()
    lines.splice(index, 1)
    this.setState({
      lines,
      rev: this.state.rev - 1,
    })
  }

  updateLine(index, line) {
    let lines = this.state.lines.slice()
    lines.splice(index, 1, line)
    this.setState({ lines })
  }

  componentDidUpdate() {
    this.props.onExpressionChange(linesToString(this.state.lines))
  }

  render() {
    return (
      <div className="pricing-rule-set__rule__rule_picker">
        <table className="table mb-2">
          <tbody>
            {this.state.lines.map((line, index) => (
              <RulePickerLine
                key={`${index}-${this.state.rev}`}
                index={index}
                testID={`condition-${index}`}
                ruleTarget={this.props.ruleTarget}
                type={line.left}
                operator={line.operator}
                value={line.right}
                onUpdate={this.updateLine}
                onDelete={this.deleteLine}
              />
            ))}
          </tbody>
        </table>
        <div className="text-right">
          <button
            className="btn btn-xs btn-default"
            onClick={this.addLine}
            data-testid="rule-picker-add-condition">
            <i className="fa fa-plus"></i> 
            {this.props.t('RULE_PICKER_ADD_CONDITION')}
          </button>
        </div>
        {/*
        <div className="rule-picker-preview">
          <pre>{ linesToString(this.state.lines) }</pre>
        </div>
        */}
      </div>
    )
  }
}

RulePicker.defaultProps = {
  ruleTarget: 'DELIVERY',
  expressionAST: null,
  onExpressionChange: () => {},
}

RulePicker.propTypes = {
  ruleTarget: PropTypes.string,
  expressionAST: PropTypes.object,
  onExpressionChange: PropTypes.func.isRequired,
}

export default withTranslation()(RulePicker)
