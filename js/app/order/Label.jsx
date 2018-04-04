import React from 'react';

const map = {
  'cart': 'label-default',
  'new': 'label-primary',
  'accepted': 'label-success',
  'refused': 'label-danger'
};

const i18n = window.__order_status_i18n;

class Label extends React.Component
{
  render() {

    const classes = ['label']
    if (map.hasOwnProperty(this.props.order.state)) {
      classes.push(map[this.props.order.state])
    } else {
      classes.push('label-default')
    }

    return (
      <span className={ classes.join(' ') }>{ i18n[this.props.order.state] }</span>
    );
  }
}

module.exports = Label;
