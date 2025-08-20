import React, { useContext, useEffect, useMemo, useState } from 'react';
import { Divider, Spin, Radio, Collapse } from 'antd';
import { useSelector } from 'react-redux';
import { useTranslation } from 'react-i18next';

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
  const { t } = useTranslation();

  const mode = useSelector(selectMode);
  const { values } = useDeliveryFormFormikContext();

  const [newOrder, setNewOrder] = useState(undefined as OrderType | undefined);
  const [selectedPriceOption, setSelectedPriceOption] = useState<
    'original' | 'new'
  >('original');

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
        // Allow setting newOrder in update mode for price comparison
        // The radio button will control which order is displayed
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
        {isPriceBreakdownEnabled && (existingOrder || newOrder) ? (
          <div className="mb-4">
            {/* Show both orders when they exist (update mode) */}
            {existingOrder && newOrder && mode === Mode.DELIVERY_UPDATE ? (
              <>
                <Radio.Group
                  value={selectedPriceOption}
                  onChange={e => setSelectedPriceOption(e.target.value)}
                  className="mb-3">
                  <Collapse
                    activeKey={['original']}
                    items={[
                      {
                        key: 'original',
                        label: (
                          <Radio value="original">{t('DELIVERY_FORM_KEEP_ORIGINAL_PRICE')}</Radio>
                        ),
                        children: (
                          <Cart
                            order={existingOrder}
                            overridePrice={selectedPriceOption !== 'original'}
                          />
                        ),
                        showArrow: false,
                      },
                    ]}
                  />

                  <Collapse
                    activeKey={['new']}
                    items={[
                      {
                        key: 'new',
                        label: <Radio value="new">{t('DELIVERY_FORM_APPLY_NEW_PRICE')}</Radio>,
                        children: (
                          <Cart
                            order={newOrder}
                            overridePrice={overridePrice}
                          />
                        ),
                        showArrow: false,
                      },
                    ]}
                  />
                </Radio.Group>
              </>
            ) : (
              <>
                {/* Show single order when only one exists */}
                {existingOrder && !newOrder ? (
                  <Cart order={existingOrder} overridePrice={overridePrice} />
                ) : null}
                {newOrder && !existingOrder ? (
                  <Cart order={newOrder} overridePrice={overridePrice} />
                ) : null}
              </>
            )}
          </div>
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
