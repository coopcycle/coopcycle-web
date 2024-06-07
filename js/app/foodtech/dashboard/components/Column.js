import React from 'react'
import classNames from 'classnames'

import OrderCard from './OrderCard'
import { useDispatch, useSelector } from 'react-redux'
import { columnToggled } from '../redux/actions'
import { selectIsCollapsedColumn } from '../redux/selectors'

export default ({ id, title, orders, active, context, onCardClick }) => {
  const isCollapsed = useSelector(state => selectIsCollapsedColumn(state, id))

  const dispatch = useDispatch()

  return (
    <div className={ classNames('FoodtechDashboard__Column', {
      'FoodtechDashboard__Column--active': active,
      'FoodtechDashboard__Column--collapsed': isCollapsed,
    }) }>
      <div className={ `panel panel-${ context }` }>
        <div className="panel-heading FoodtechDashboard__Column__Heading">
          <span>{ title }</span>
          <span className="px-2">{ `(${ orders.length })` }</span>
          <div className="flex-1" />
          <i className={ classNames('fa', {
            'fa-chevron-left': isCollapsed,
            'fa-chevron-right': !isCollapsed,
          }) } onClick={ () => dispatch(columnToggled(id)) } />
        </div>
        { isCollapsed ? null : (
          <div className="panel-body">
            { orders.map((order, key) => (
              <OrderCard key={ key } order={ order } onClick={ onCardClick } />
            )) }
          </div>) }
      </div>
    </div>
  )
}
