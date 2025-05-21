import React, { createRef, Component } from 'react'
import { createRoot } from 'react-dom/client'
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

// https://stackoverflow.com/questions/37949981/call-child-method-from-parent
export default function(el, options) {

  const ref = createRef()

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

  createRoot(el).render(<SwitchWrapper ref={ ref } { ...props } onChange={ options.onChange } />)

  return {
    check: () => ref.current.check(),
    uncheck: () => ref.current.uncheck()
  }
}
