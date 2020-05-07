import React from 'react'
import { withTranslation } from 'react-i18next'

export default withTranslation()(({ order, t }) => {

  return (
    <span>
      <span className="mr-2">
        { !order.takeaway && (<i className="fa fa-bicycle"></i>) }
        { order.takeaway && (<i className="fa fa-cube"></i>) }
      </span>
      <span className="order-number">
        { t('RESTAURANT_DASHBOARD_ORDER_TITLE', { number: order.number }) }
      </span>
    </span>
  )
})
