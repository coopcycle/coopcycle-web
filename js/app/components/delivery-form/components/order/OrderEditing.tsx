import React, { useContext, useState, useEffect } from 'react';
import { Collapse, Radio } from 'antd';
import { useTranslation } from 'react-i18next';
import FlagsContext from '../../FlagsContext';
import Cart from './Cart';
import { Order as OrderType } from '../../../../api/types';
import { TotalPrice } from './TotalPrice';
import { useDeliveryFormFormikContext } from '../../hooks/useDeliveryFormFormikContext';

type Props = {
  overridePrice: boolean;
  existingOrder: OrderType;
  newOrder?: OrderType;
};

function hasOrderChanged(existingOrder: OrderType, newOrder: OrderType) {
  return (
    existingOrder.total !== newOrder.total ||
    existingOrder.items.length !== newOrder.items.length
  );
}

export const OrderEditing = ({
  overridePrice,
  existingOrder,
  newOrder,
}: Props) => {
  const { isPriceBreakdownEnabled } = useContext(FlagsContext);

  const { t } = useTranslation();
  const { setFieldValue } = useDeliveryFormFormikContext();

  const [selectedPriceOption, setSelectedPriceOption] = useState<
    'original' | 'new'
  >('original');

  useEffect(() => {
    setFieldValue('order.recalculatePrice', selectedPriceOption === 'new');
  }, [selectedPriceOption, setFieldValue]);

  return (
    <div>
      {isPriceBreakdownEnabled ? (
        <div>
          {/* Show a choice between both an existing and a new order */}
          {newOrder && hasOrderChanged(existingOrder, newOrder) ? (
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
                          order={existingOrder}
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
                        <Cart order={newOrder} overridePrice={overridePrice} />
                      ),
                      showArrow: false,
                    },
                  ]}
                />
              </Radio.Group>
            </>
          ) : (
            <Cart order={existingOrder} overridePrice={overridePrice} />
          )}
        </div>
      ) : null}
      <div className="mt-2">
        {/* Show both an old and a new price when a new price is selected */}
        {newOrder && selectedPriceOption === 'new' ? (
          <>
            <TotalPrice
              overridePrice={true}
              total={existingOrder.total}
              taxTotal={existingOrder.taxTotal}
            />
            <TotalPrice
              overridePrice={overridePrice}
              total={newOrder.total}
              taxTotal={newOrder.taxTotal}
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
    </div>
  );
};
