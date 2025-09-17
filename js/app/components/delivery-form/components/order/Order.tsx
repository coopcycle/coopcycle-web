import React, { useContext, useEffect, useMemo, useState } from 'react';
import { Divider, Spin } from 'antd';
import { useSelector } from 'react-redux';

import FlagsContext from '../../FlagsContext';
import {
  CalculationOutput,
  ManualSupplementValues,
  Order as OrderType,
} from '../../../../api/types';
import { Mode, modeIn } from '../../mode';
import { selectMode } from '../../redux/formSlice';
import { useDeliveryFormFormikContext } from '../../hooks/useDeliveryFormFormikContext';
import { useCalculatedPrice } from '../../hooks/useCalculatedPrice';
import { useOrderManualSupplements } from '../../hooks/useOrderManualSupplements';
import { OverridePrice } from './OverridePrice';
import { OrderOnCheckout } from './OrderOnCheckout';
import { OrderEditing } from './OrderEditing';

type Props = {
  storeNodeId: string;
  order?: OrderType;
  initialManualSupplements?: ManualSupplementValues[];
  setPriceLoading: (loading: boolean) => void;
};

const Order = ({
  storeNodeId,
  order: _existingOrder,
  initialManualSupplements: existingSupplements = [],
  setPriceLoading,
}: Props) => {
  const { isDispatcher } = useContext(FlagsContext);

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

  const priceCalculation = useMemo(() => {
    if (calculatePriceData) {
      return {
        calculation: calculatePriceData.calculation,
        order: calculatePriceData.order,
      };
    }

    if (calculatePriceError && 'calculation' in calculatePriceError) {
      return {
        calculation: calculatePriceError.calculation as CalculationOutput,
        order: undefined,
      };
    }

    return undefined;
  }, [calculatePriceData, calculatePriceError]);

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
            orderManualSupplements={orderManualSupplements}
            overridePrice={overridePrice}
            newOrder={newOrder}
            calculatePriceData={calculatePriceData}
            calculatePriceError={calculatePriceError}
            priceCalculation={priceCalculation}
          />
        ) : null}
        {existingOrder && mode === Mode.DELIVERY_UPDATE ? (
          <OrderEditing
            orderManualSupplements={orderManualSupplements}
            overridePrice={overridePrice}
            existingOrder={existingOrder}
            existingSupplements={existingSupplements}
            updatedOrder={newOrder}
            priceCalculation={priceCalculation}
          />
        ) : null}

        <div>
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
