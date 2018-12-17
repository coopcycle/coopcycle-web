import React, { Component } from 'react'

export default class extends Component {
  render () {

    const classNames = ['order-timeline__step']
    if (this.props.success) {
      classNames.push('order-timeline__step--success')
    }
    if (this.props.danger) {
      classNames.push('order-timeline__step--danger')
    }
    if (this.props.active) {
      classNames.push('order-timeline__step--active')
    }

    return (
      <div className={ classNames.join(' ') }>
        <i className="order-timeline__step__bullet"></i>
        <span className="order-timeline__step__title">
          { this.props.title }
        </span>
        <div
          className="order-timeline__step__description"
          style={ this.props.active ? { display: 'block'} : {} }>
          { this.props.description }
        </div>
      </div>
    )
  }
}
