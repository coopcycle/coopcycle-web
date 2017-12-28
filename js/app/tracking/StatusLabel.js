import React, { Component } from 'react'

const classNames = {
  'WAITING': 'label-default',
  'DISPATCHED': 'label-info',
  'PICKED': 'label-primary',
  'DELIVERED': 'label-success',
  'CANCELED': 'label-default',
}

export default class StatusLabel extends Component
{
  constructor(props) {
    super(props);
  }
  render() {

    const labelClass = ['label', classNames[this.props.status]]

    return (
      <span className={ labelClass.join(' ') }>{ window.__i18n['delivery.status.' + this.props.status] }</span>
    )
  }
}
