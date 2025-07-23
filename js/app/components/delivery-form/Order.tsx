import React, { useContext, useState } from 'react'
import Price from './Price'
import Cart from './Cart'
import { Spin } from 'antd'
import FlagsContext from './FlagsContext'
import { Order as OrderType } from './types'

type Props = {
  storeNodeId: string
  order: OrderType | null
  setPriceLoading: (loading: boolean) => void
}

const Order = ({ storeNodeId, order: preLoadedOrder, setPriceLoading }: Props) => {
  const { isPriceBreakdownEnabled } = useContext(FlagsContext)
  const [order, setOrder] = useState<OrderType | null>(preLoadedOrder)
  const [isLoading, setIsLoading] = useState<boolean>(false)

  const [overridePrice, setOverridePrice] = useState<boolean>(false)

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

export default Order
