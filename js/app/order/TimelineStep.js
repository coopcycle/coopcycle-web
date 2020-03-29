import React from 'react'

export default (props) => {

  const classNames = ['order-timeline__step']
  if (props.success) {
    classNames.push('order-timeline__step--success')
  }
  if (props.danger) {
    classNames.push('order-timeline__step--danger')
  }
  if (props.active) {
    classNames.push('order-timeline__step--active')
  }

  return (
    <div className={ classNames.join(' ') }>
      <i className="order-timeline__step__bullet"></i>
      <span className="order-timeline__step__title">
        { props.title }
        { props.spinner && (<i className="fa fa-spinner fa-pulse ml-2"></i>) }
      </span>
      <div
        className="order-timeline__step__description"
        style={ props.active ? { display: 'block'} : {} }>
        { props.description }
      </div>
    </div>
  )
}
