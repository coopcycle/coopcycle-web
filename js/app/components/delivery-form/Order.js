import React, { useState } from 'react'
import Price from './Price'
import Cart from './Cart'
import { Spin } from 'antd'

export default ({
  storeNodeId,
  order: preLoadedOrder,
  setPriceLoading,
}) => {
  const [order, setOrder] = useState(preLoadedOrder)
  const [isLoading, setIsLoading] = useState(false)

  const [overridePrice, setOverridePrice] = useState(false)

  return (
    <Spin spinning={isLoading}>
      <div>
        {Boolean(order) && order.items ? (
          <Cart order={order} overridePrice={overridePrice} />
        ) : null}
        <Price
          storeNodeId={storeNodeId}
          order={order}
          setPriceLoading={isLoading => {
            setIsLoading(isLoading)
            setPriceLoading(isLoading)
          }}
          setOrder={setOrder}
          setOverridePrice={setOverridePrice}
        />
      </div>
    </Spin>
  )
}
