import React, { useContext, useState } from 'react'
import Price from './Price'
import Cart from './Cart'
import { Spin } from 'antd'
import FlagsContext from './FlagsContext'

export default ({ storeNodeId, order: preLoadedOrder, setPriceLoading }) => {
  const { isPriceBreakdownEnabled } = useContext(FlagsContext)
  const [order, setOrder] = useState(preLoadedOrder)
  const [isLoading, setIsLoading] = useState(false)

  const [overridePrice, setOverridePrice] = useState(false)

  return (
    <Spin spinning={isLoading}>
      <div>
        {isPriceBreakdownEnabled && Boolean(order) && order.items ? (
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
