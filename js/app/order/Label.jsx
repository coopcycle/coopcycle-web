import React from 'react';

const map = {
  'CREATED': 'label-default',
  'CANCELED': 'label-default',
  'WAITING': 'label-warning',
  'ACCEPTED': 'label-primary',
  'REFUSED': 'label-danger',
  'READY': 'label-success'
}

const i18n = window.__order_status_i18n;

class Label extends React.Component
{
  render() {
    const classes = [
      'label',
      map[this.props.order.status]
    ]
    return (
      <span className={ classes.join(' ') }>{ i18n[this.props.order.status] }</span>
    );
  }
}

module.exports = Label;
