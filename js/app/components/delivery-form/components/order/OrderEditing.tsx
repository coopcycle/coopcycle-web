import React, { useContext, useMemo, useState } from 'react';
import FlagsContext from '../../FlagsContext';
import Cart from './Cart';
import { Order as OrderType } from '../../../../api/types';
import { Collapse, Radio } from 'antd';
import { TotalPrice } from './TotalPrice';
import { useTranslation } from 'react-i18next';

type Props = {
  overridePrice: boolean;
  existingOrder: OrderType;
  newOrder?: OrderType;
};

export const OrderEditing = ({
  overridePrice,
  existingOrder,
  newOrder,
}: Props) => {
  const { isPriceBreakdownEnabled } = useContext(FlagsContext);

  const { t } = useTranslation();

  // aka "old price"
  const existingPrice = useMemo(() => {
    return {
      exVAT: +existingOrder.total - +existingOrder.taxTotal,
      VAT: +existingOrder.total,
    };
  }, [existingOrder]);

  const [selectedPriceOption, setSelectedPriceOption] = useState<
    'original' | 'new'
  >('original');

  return (
    <div>
      {isPriceBreakdownEnabled && (existingOrder || newOrder) ? (
        <div className="mb-4">
          {/* Show both orders when they exist (update mode) */}
          {existingOrder && newOrder ? (
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
                        <Radio value="original">
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
                  activeKey={['new']}
                  items={[
                    {
                      key: 'new',
                      label: (
                        <Radio value="new">
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
      <TotalPrice
        overridePrice={overridePrice}
        priceWithTaxes={existingPrice.VAT}
        priceWithoutTaxes={existingPrice.exVAT}
      />
    </div>
  );
};
