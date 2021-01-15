import React from 'react'
import classNames from 'classnames'

export default ({ title, target, active, onClick }) => {

  return (
    <button className={ classNames({
      'FoodtechDashboard__Tab': true,
      'FoodtechDashboard__Tab--active': active,
      'flex-fill': true }) } onClick={ () => onClick(target) }>{ title }</button>
  )
}
