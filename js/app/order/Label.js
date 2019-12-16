import React from 'react'
import i18n from '../i18n'

const map = {
  'cart': 'label-default',
  'new': 'label-primary',
  'accepted': 'label-success',
  'ready': 'label-success',
  'refused': 'label-danger'
}

class Label extends React.Component {
  render() {

    const classes = ['label']
    if (map.hasOwnProperty(this.props.order.state)) {
      classes.push(map[this.props.order.state])
    } else {
      classes.push('label-default')
    }

    return (
      <span className={ classes.join(' ') }>{ i18n.t(this.props.order.state) }</span>
    )
  }
}

export default Label
