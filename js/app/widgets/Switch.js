import React, { Component } from 'react'
import { render } from 'react-dom'
import { Switch } from 'antd'

class SwitchWrapper extends Component {

  constructor(props) {
    super(props)
    this.state = {
      checked: this.props.checked,
    }
  }

  toggle() {
    const checked = !this.state.checked
    this.setState({ checked })
  }

  uncheck() {
    this.setState({ checked: false })
  }

  check() {
    this.setState({ checked: true })
  }

  render () {
    return (
      <Switch
        { ...this.props }
        checked={ this.state.checked }
        onClick={ this.toggle.bind(this) } />
    )
  }
}

export default function(el, options) {

  let props = {
    checked: options.checked,
    disabled: options.disabled || false,
  }

  if (options.checkedChildren) {
    props = {
      ...props,
      checkedChildren: options.checkedChildren,
    }
  }

  if (options.unCheckedChildren) {
    props = {
      ...props,
      unCheckedChildren: options.unCheckedChildren,
    }
  }

  const component = render(<SwitchWrapper { ...props } onChange={ options.onChange } />, el)

  return {
    check: () => component.check(),
    uncheck: () => component.uncheck()
  }
}
