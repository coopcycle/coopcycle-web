import React from 'react'
import _ from 'lodash'
import { useTranslation } from 'react-i18next'

const OrderNumber = ({ task }) => {

  if (_.isEmpty(task.metadata.order_number)) {

    return null
  }

  const { t } = useTranslation()

  return (
    <React.Fragment>
      <span className="mx-1">|</span>
      <span className="text-muted">
        <span className="mr-1">{ t('ADMIN_DASHBOARD_ORDERS_ORDER') }</span>
        <strong className="text-monospace">
          { task.metadata.order_number }
        </strong>
      </span>
    </React.Fragment>
  )
}

export default OrderNumber
