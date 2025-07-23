import React from 'react'
import { withTranslation } from 'react-i18next'

import RulePickerLine from './RulePickerLine'
import { linesToString } from '../../../delivery/pricing/expression-builder'
import { parseAST } from '../../../delivery/pricing/pricing-rule-parser'

type Props = {
  ruleTarget: string
  expressionAST: object | null
  onExpressionChange: (expression: string) => void
  t: (key: string) => string
}

type State = {
  lines: Array<{ left: string; operator: string; right: string | string[] }>
  rev: number
}

class RulePicker extends React.Component<Props, State> {
  constructor(props: Props) {
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

  addLine(evt: React.MouseEvent<HTMLButtonElement>): void {
    evt.preventDefault()
    let lines = this.state.lines.slice()
    lines.push({
      left: '',
      operator: '',
      right: '',
    })
    this.setState({
      lines,
      rev: this.state.rev + 1,
    })
  }

  deleteLine(index: number): void {
    let lines = this.state.lines.slice()
    lines.splice(index, 1)
    this.setState({
      lines,
      rev: this.state.rev - 1,
    })
  }

  updateLine(
    index: number,
    line: { left: string; operator: string; right: string | string[] },
  ): void {
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
            {this.state.lines.map((line, index: number) => (
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
            data-testid="rule-add-condition">
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

export default withTranslation()(RulePicker)
