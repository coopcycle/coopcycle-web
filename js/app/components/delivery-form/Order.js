import React, { useState } from 'react'
import Price from './Price'
import Cart from './Cart'
import { useTranslation } from 'react-i18next'
import { Spin } from 'antd'

export default ({
  storeNodeId,
  order: preLoadedOrder,
  isDebugPricing,
  isDispatcher,
  setPriceLoading,
}) => {
  const [order, setOrder] = useState(preLoadedOrder)
  const [isLoading, setIsLoading] = useState(false)

  const { t } = useTranslation()

  return (
    <Spin spinning={isLoading}>
      <div>
        {Boolean(order) && order.items ? <Cart order={order} /> : null}
        <Price
          storeNodeId={storeNodeId}
          order={order}
          setOrder={setOrder}
          setIsLoading={setIsLoading}
          isDebugPricing={isDebugPricing}
          isDispatcher={isDispatcher}
          setPriceLoading={isLoading => {
            setIsLoading(isLoading)
            setPriceLoading(isLoading)
          }}
        />
      </div>
    </Spin>
  )
}
