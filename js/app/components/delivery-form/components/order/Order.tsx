import React, { useContext, useEffect, useMemo, useState } from 'react';
import { Divider, Spin } from 'antd';
import { useSelector } from 'react-redux';

import Cart from './Cart';
import FlagsContext from '../../FlagsContext';
import { Order as OrderType } from '../../../../api/types';
import { Mode, modeIn } from '../../mode';
import CheckoutTotalPrice from './CheckoutTotalPrice';
import { PriceCalculation } from '../../../../delivery/PriceCalculation';
import { selectMode } from '../../redux/formSlice';
import { useDeliveryFormFormikContext } from '../../hooks/useDeliveryFormFormikContext';
import ManualSupplements from './ManualSupplements';
import { TotalPrice } from './TotalPrice';
import { useCalculatedPrice } from '../../hooks/useCalculatedPrice';
import { useOrderManualSupplements } from '../../hooks/useOrderManualSupplements';
import { OverridePrice } from './OverridePrice';

type Props = {
  storeNodeId: string;
  order?: OrderType;
  setPriceLoading: (loading: boolean) => void;
};

const Order = ({
  storeNodeId,
  order: existingOrder,
  setPriceLoading,
}: Props) => {
  const { isDispatcher, isDebugPricing, isPriceBreakdownEnabled } =
    useContext(FlagsContext);

  const mode = useSelector(selectMode);
  const { values } = useDeliveryFormFormikContext();

  const [newOrder, setNewOrder] = useState(undefined as OrderType | undefined);

  const [overridePrice, setOverridePrice] = useState<boolean>(() => {
    if (modeIn(mode, [Mode.DELIVERY_CREATE, Mode.RECURRENCE_RULE_UPDATE])) {
      // when cloning an order that has an arbitrary price
      if (
        values.variantIncVATPrice !== undefined &&
        values.variantIncVATPrice !== null
      ) {
        return true;
      } else {
        return false;
      }
    } else {
      return false;
    }
  });

  // aka "old price"
  const existingPrice = useMemo(() => {
    if (mode === Mode.DELIVERY_UPDATE && existingOrder) {
      return {
        exVAT: +existingOrder.total - +existingOrder.taxTotal,
        VAT: +existingOrder.total,
      };
    }
  }, [existingOrder, mode]);

  const {
    data: orderManualSupplements,
    isLoading: orderManualSupplementsIsLoading,
  } = useOrderManualSupplements({
    storeUri: storeNodeId,
  });

  const {
    data: calculatePriceData,
    error: calculatePriceError,
    isLoading: calculatePriceIsLoading,
  } = useCalculatedPrice({
    storeUri: storeNodeId,
    skip: overridePrice,
  });

  const isLoading = useMemo(() => {
    return orderManualSupplementsIsLoading || calculatePriceIsLoading;
  }, [orderManualSupplementsIsLoading, calculatePriceIsLoading]);

  // Pass loading state to parent component
  useEffect(() => {
    setPriceLoading(isLoading);
  }, [isLoading, setPriceLoading]);

  useEffect(() => {
    if (calculatePriceError) {
      setNewOrder(undefined);
    }

    if (calculatePriceData) {
      const order = calculatePriceData.order;

      if (mode === Mode.DELIVERY_UPDATE) {
        //TODO; handle switch between current and proposed price
        // uncomment when a switcher is implemented
        return;

        //compare existing and new order
        // if same display existing order
        // if (
        //   existingOrder &&
        //   existingOrder.total === order.total &&
        //   existingOrder.items.length === order.items.length
        // ) {
        //   return;
        // }
      }

      setNewOrder(order);
    }
  }, [
    mode,
    existingOrder,
    calculatePriceData,
    calculatePriceError,
    setNewOrder,
  ]);

  return (
    <Spin spinning={isLoading}>
      <div>
        {isPriceBreakdownEnabled ? (
          <>
            {existingOrder ? (
              <Cart order={existingOrder} overridePrice={overridePrice} />
            ) : null}
            {newOrder ? (
              <Cart order={newOrder} overridePrice={overridePrice} />
            ) : null}
          </>
        ) : null}
        <div>
          {existingPrice ? (
            <TotalPrice
              overridePrice={overridePrice}
              priceWithTaxes={existingPrice.VAT}
              priceWithoutTaxes={existingPrice.exVAT}
            />
          ) : (
            <CheckoutTotalPrice
              overridePrice={overridePrice}
              priceErrorMessage={
                calculatePriceError
                  ? calculatePriceError['hydra:description']
                  : ''
              }
              calculatePriceData={calculatePriceData}
            />
          )}

          {!overridePrice &&
            (isDispatcher || isDebugPricing) &&
            calculatePriceData && (
              <PriceCalculation
                className="mt-2"
                isDebugPricing={isDebugPricing}
                calculation={calculatePriceData.calculation}
                order={calculatePriceData.order}
              />
            )}

          {isDispatcher &&
          !overridePrice &&
          mode === Mode.DELIVERY_CREATE &&
          orderManualSupplements.length > 0 ? (
            <div>
              <Divider size="middle" />
              <ManualSupplements rules={orderManualSupplements} />
            </div>
          ) : null}

          {isDispatcher && (
            <div>
              <Divider size="middle" />
              <OverridePrice
                overridePrice={overridePrice}
                setOverridePrice={setOverridePrice}
              />
            </div>
          )}
        </div>
      </div>
    </Spin>
  );
};

export default Order;
