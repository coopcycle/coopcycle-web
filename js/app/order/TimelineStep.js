import React, { Component } from 'react';
import moment from 'moment';
import i18n from '../i18n'

moment.locale($('html').attr('lang'));

export default class extends Component {
  render () {

    const classNames = ['order-follow--step']
    if (this.props.done) {
      classNames.push('order-follow--step__done')
    }

    return (
      <div
        className={ classNames.join(' ') }
        style={ this.props.active ? { opacity: 1 } : {} }>
        <span className="order-follow--number">
          { this.props.number }
        </span>
        <span className="order-follow--title">
          { this.props.title }
        </span>
        <div
          className="order-follow--description"
          style={ this.props.active ? { display: 'block'} : {} }>
          { this.props.description }
        </div>
      </div>
    )
  }
}
