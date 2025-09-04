import React, { useContext, useEffect, useMemo, useState } from 'react';
import { Collapse, Divider, Radio } from 'antd';
import { useTranslation } from 'react-i18next';
import FlagsContext from '../../FlagsContext';
import Cart from './Cart';
import {
  ManualSupplementValues,
  OrderItem as OrderItemType,
  Order as OrderType,
  PricingRule,
  RetailPrice,
} from '../../../../api/types';
import { TotalPrice } from './TotalPrice';
import { useDeliveryFormFormikContext } from '../../hooks/useDeliveryFormFormikContext';
import { PriceCalculation } from '../../../../delivery/PriceCalculation';
import ManualSupplements from './ManualSupplements';

type Props = {
  orderManualSupplements?: PricingRule[];
  overridePrice: boolean;
  existingOrder: OrderType;
  existingSupplements?: ManualSupplementValues[];
  updatedOrder?: OrderType;
  calculatePriceData?: RetailPrice;
};

function getCalculatedOrderItems(orderItems: OrderItemType[]) {
  const items = orderItems.map(item => {
    return {
      ...item,
      adjustments: {
        ...item.adjustments,
        order_item_package_delivery_manual_supplement: [],
      },
    };
  });

  return items.filter(item => {
    return (
      item.adjustments['order_item_package_delivery_calculated']?.length > 0
    );
  });
}

function getManualSupplementsOrderItems(orderItems: OrderItemType[]) {
  const items = orderItems.map(item => {
    return {
      ...item,
      adjustments: {
        ...item.adjustments,
        order_item_package_delivery_calculated: [],
      },
    };
  });

  return items.filter(item => {
    return (
      item.adjustments['order_item_package_delivery_manual_supplement']
        ?.length > 0
    );
  });
}

function getCalculatedAmount(orderItems: OrderItemType[]) {
  return orderItems.reduce(
    (total, item) =>
      total +
      item.adjustments['order_item_package_delivery_calculated'].reduce(
        (total, adjustment) => total + adjustment.amount,
        0,
      ),
    0,
  );
}

function hasCalculatedOrderItemsChanged(
  existingOrder: OrderType,
  newOrder: OrderType,
) {
  const existingItems = getCalculatedOrderItems(existingOrder.items);
  const newItems = getCalculatedOrderItems(newOrder.items);

  return (
    getCalculatedAmount(existingItems) !== getCalculatedAmount(newItems) ||
    existingItems.length !== newItems.length
  );
}

export const OrderEditing = ({
  orderManualSupplements = [],
  overridePrice,
  existingOrder,
  existingSupplements = [],
  updatedOrder,
  calculatePriceData,
}: Props) => {
  const { isPriceBreakdownEnabled, isDispatcher, isDebugPricing } =
    useContext(FlagsContext);

  const { t } = useTranslation();
  const { values, setFieldValue } = useDeliveryFormFormikContext();

  const [selectedPriceOption, setSelectedPriceOption] = useState<
    'original' | 'new'
  >('original');

  useEffect(() => {
    setFieldValue('order.recalculatePrice', selectedPriceOption === 'new');
  }, [selectedPriceOption, setFieldValue]);

  const orderManualSupplementsWithQuantity = useMemo(() => {
    return orderManualSupplements.map(rule => ({
      ...rule,
      quantity:
        values.order.manualSupplements.find(
          supplement => supplement.pricingRule === rule['@id'],
        )?.quantity || 0,
    }));
  }, [orderManualSupplements, values.order.manualSupplements]);

  const hasSupplementsChanged = useMemo(() => {
    const currentSupplements = values.order.manualSupplements;

    if (existingSupplements.length !== currentSupplements.length) {
      return true;
    }

    return (
      existingSupplements.some(existing => {
        const current = currentSupplements.find(
          current => current.pricingRule === existing.pricingRule,
        );
        return !current || current.quantity !== existing.quantity;
      }) ||
      currentSupplements.some(current => {
        const existing = existingSupplements.find(
          existing => existing.pricingRule === current.pricingRule,
        );
        return !existing;
      })
    );
  }, [existingSupplements, values.order.manualSupplements]);

  return (
    <div>
      {isPriceBreakdownEnabled ? (
        <div>
          {/* Show a choice between both an existing and a new order */}
          {!overridePrice &&
          updatedOrder &&
          hasCalculatedOrderItemsChanged(existingOrder, updatedOrder) ? (
            <>
              <Radio.Group
                className="w-100"
                value={selectedPriceOption}
                onChange={e => setSelectedPriceOption(e.target.value)}>
                <Collapse
                  activeKey={['original']}
                  items={[
                    {
                      key: 'original',
                      label: (
                        <Radio
                          value="original"
                          data-testid="keep-original-price">
                          {t('DELIVERY_FORM_KEEP_ORIGINAL_PRICE')}
                        </Radio>
                      ),
                      children: (
                        <Cart
                          orderItems={getCalculatedOrderItems(
                            existingOrder.items,
                          )}
                          overridePrice={selectedPriceOption !== 'original'}
                        />
                      ),
                      showArrow: false,
                    },
                  ]}
                />

                <Collapse
                  className="mt-2"
                  activeKey={['new']}
                  items={[
                    {
                      key: 'new',
                      label: (
                        <Radio value="new" data-testid="apply-new-price">
                          {t('DELIVERY_FORM_APPLY_NEW_PRICE')}
                        </Radio>
                      ),
                      children: (
                        <Cart
                          orderItems={getCalculatedOrderItems(
                            updatedOrder.items,
                          )}
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
            <Cart
              orderItems={getCalculatedOrderItems(existingOrder.items)}
              overridePrice={overridePrice}
            />
          )}
        </div>
      ) : null}

      <div className="mt-2">
        {/* Show both an old and a new manual supplements when they changed */}
        {!overridePrice && updatedOrder && hasSupplementsChanged ? (
          <>
            <Cart
              orderItems={getManualSupplementsOrderItems(existingOrder.items)}
              overridePrice={true}
            />
            <Cart
              orderItems={getManualSupplementsOrderItems(updatedOrder.items)}
              overridePrice={overridePrice}
            />
          </>
        ) : (
          <Cart
            orderItems={getManualSupplementsOrderItems(existingOrder.items)}
            overridePrice={overridePrice}
          />
        )}
      </div>
      <div className="mt-2">
        {/* Show both an old and a new total price when there is a price change */}
        {!overridePrice &&
        updatedOrder &&
        (selectedPriceOption === 'new' || hasSupplementsChanged) ? (
          <>
            <TotalPrice
              overridePrice={true}
              total={existingOrder.total}
              taxTotal={existingOrder.taxTotal}
            />
            <TotalPrice
              overridePrice={overridePrice}
              total={updatedOrder.total}
              taxTotal={updatedOrder.taxTotal}
            />
          </>
        ) : (
          <TotalPrice
            overridePrice={overridePrice}
            total={existingOrder.total}
            taxTotal={existingOrder.taxTotal}
          />
        )}
      </div>

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

      {isDispatcher && !overridePrice && orderManualSupplements.length > 0 && (
        <div>
          <Divider size="middle" />
          <ManualSupplements rules={orderManualSupplementsWithQuantity} />
        </div>
      )}
    </div>
  );
};
