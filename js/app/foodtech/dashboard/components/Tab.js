import React from 'react'
import clsx from 'clsx'

export default ({ title, target, active, onClick }) => {

  return (
    <button className={ clsx({
      'FoodtechDashboard__Tab': true,
      'FoodtechDashboard__Tab--active': active,
      'flex-fill': true }) } onClick={ () => onClick(target) }>{ title }</button>
  )
}
