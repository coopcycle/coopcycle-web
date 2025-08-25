import React, { useContext, useEffect, useMemo, useState } from 'react';
import { Divider, Spin } from 'antd';
import { useSelector } from 'react-redux';

import FlagsContext from '../../FlagsContext';
import { Order as OrderType } from '../../../../api/types';
import { Mode, modeIn } from '../../mode';
import { PriceCalculation } from '../../../../delivery/PriceCalculation';
import { selectMode } from '../../redux/formSlice';
import { useDeliveryFormFormikContext } from '../../hooks/useDeliveryFormFormikContext';
import ManualSupplements from './ManualSupplements';
import { useCalculatedPrice } from '../../hooks/useCalculatedPrice';
import { useOrderManualSupplements } from '../../hooks/useOrderManualSupplements';
import { OverridePrice } from './OverridePrice';
import { OrderOnCheckout } from './OrderOnCheckout';
import { OrderEditing } from './OrderEditing';

type Props = {
  storeNodeId: string;
  order?: OrderType;
  setPriceLoading: (loading: boolean) => void;
};

const Order = ({
  storeNodeId,
  order: _existingOrder,
  setPriceLoading,
}: Props) => {
  const { isDispatcher, isDebugPricing } = useContext(FlagsContext);

  const mode = useSelector(selectMode);
  const { values } = useDeliveryFormFormikContext();

  const [newOrder, setNewOrder] = useState(undefined as OrderType | undefined);

  const existingOrder = useMemo(() => {
    if (!_existingOrder) {
      return undefined;
    }

    // Make sure items are always in the same order
    _existingOrder?.items.sort((a, b) => a.id - b.id);

    return _existingOrder;
  }, [_existingOrder]);

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
      setNewOrder(calculatePriceData.order);
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
        {modeIn(mode, [Mode.DELIVERY_CREATE, Mode.RECURRENCE_RULE_UPDATE]) ? (
          <OrderOnCheckout
            overridePrice={overridePrice}
            newOrder={newOrder}
            calculatePriceData={calculatePriceData}
            calculatePriceError={calculatePriceError}
          />
        ) : null}
        {existingOrder && mode === Mode.DELIVERY_UPDATE ? (
          <OrderEditing
            overridePrice={overridePrice}
            existingOrder={existingOrder}
            newOrder={newOrder}
          />
        ) : null}

        <div>
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
