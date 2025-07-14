import React, {
  useState,
  useEffect,
  useCallback,
  useMemo,
  useContext,
} from 'react'
import { Checkbox } from 'antd'
import { useTranslation } from 'react-i18next'

import { money } from '../../../../assets/react/controllers/Incident/utils'
import { PriceCalculation } from '../../delivery/PriceCalculation'

import './ShowPrice.scss'

import { useDeliveryFormFormikContext } from './hooks/useDeliveryFormFormikContext'
import _ from 'lodash'
import OverridePriceForm from './OverridePriceForm'
import { useCalculatePriceMutation, useGetTaxRatesQuery } from '../../api/slice'
import { Mode, modeIn } from './mode'
import { useSelector } from 'react-redux'
import { selectMode } from './redux/formSlice'
import FlagsContext from './FlagsContext'

const TotalPrice = ({ className, priceWithTaxes, priceWithoutTaxes }) => {
  const { t } = useTranslation()

  return (
    <span className={className}>
      <span>
        {t('DELIVERY_FORM_TOTAL_EX_VAT')} {priceWithoutTaxes.formatMoney()}
      </span>
      <br />
      <span className="font-weight-semi-bold" data-testid="tax-included">
        {t('DELIVERY_FORM_TOTAL_VAT')} {priceWithTaxes.formatMoney()}
      </span>
    </span>
  )
}

export default ({
  storeNodeId,
  order,
  setPriceLoading,
  setOrder,
  setOverridePrice: setOverridePriceOnParent,
}) => {
  const { isDispatcher, isDebugPricing } = useContext(FlagsContext)

  const mode = useSelector(selectMode)
  const { values, setFieldValue } = useDeliveryFormFormikContext()

  const [overridePrice, setOverridePrice] = useState(() => {
    if (modeIn(mode, [Mode.DELIVERY_CREATE, Mode.RECURRENCE_RULE_UPDATE])) {
      // when cloning an order that has an arbitrary price
      if (
        values.variantIncVATPrice !== undefined &&
        values.variantIncVATPrice !== null
      ) {
        return true
      } else {
        return false
      }
    } else {
      return false
    }
  })

  // aka "old price"
  const currentPrice = useMemo(() => {
    if (mode === Mode.DELIVERY_UPDATE && order) {
      return { exVAT: +order.total - +order.taxTotal, VAT: +order.total }
    }
  }, [order, mode])

  const [newPrice, setNewPrice] = useState(0)

  const { t } = useTranslation()

  const { data: taxRatesData, error: taxRatesError } = useGetTaxRatesQuery()

  const taxRate = useMemo(() => {
    if (taxRatesError) {
      return null
    }

    if (taxRatesData) {
      const taxRates = taxRatesData['hydra:member']
      return (
        taxRates.find(tax => tax.category === 'SERVICE') ||
        taxRates.find(tax => tax.category === 'BASE_STANDARD')
      )
    }

    return null
  }, [taxRatesData, taxRatesError])

  const [
    calculatePrice,
    {
      data: calculatePriceData,
      error: calculatePriceError,
      isLoading: calculatePriceIsLoading,
    },
  ] = useCalculatePriceMutation()

  const calculatePriceDebounced = useMemo(
    () => _.debounce(calculatePrice, 800),
    [calculatePrice],
  )

  const convertValuesToPayload = useCallback(
    values => {
      const infos = {
        store: storeNodeId,
        tasks: structuredClone(values.tasks),
      }
      return infos
    },
    [storeNodeId],
  )

  // Pass loading state to parent component
  useEffect(() => {
    setPriceLoading(calculatePriceIsLoading)
  }, [calculatePriceIsLoading, setPriceLoading])

  const calculateResponseData = useMemo(() => {
    const data = calculatePriceData
    const error = calculatePriceError

    if (error) {
      return error.data
    }

    if (data) {
      return data
    }

    return null
  }, [calculatePriceData, calculatePriceError])

  const priceErrorMessage = useMemo(() => {
    const error = calculatePriceError

    if (error) {
      return error.data['hydra:description']
    }

    return ''
  }, [calculatePriceError])

  const toggleOverridePrice = useCallback(
    value => {
      setOverridePrice(value)
      setOverridePriceOnParent(value)
      setNewPrice(0)
    },
    [setOverridePrice, setOverridePriceOnParent],
  )

  useEffect(() => {
    const data = calculatePriceData
    const error = calculatePriceError

    if (error) {
      setNewPrice(0)
    }

    if (data) {
      setNewPrice(data)
      setOrder(data.order)
    }
  }, [calculatePriceData, calculatePriceError, setOrder])

  useEffect(() => {
    if (mode === Mode.DELIVERY_UPDATE) {
      return
    }

    if (overridePrice) {
      return
    }

    // Don't calculate price until all tasks have an address
    if (!values.tasks.every(task => task.address.streetAddress)) {
      return
    }

    // Don't calculate price if a time slot (timeSlotUrl) is selected, but no choice (timeSlot) is made yet
    if (
      !values.tasks.every(
        task => (task.timeSlotUrl && task.timeSlot) || !task.timeSlotUrl,
      )
    ) {
      return
    }

    const infos = convertValuesToPayload(values)
    infos.tasks.forEach(task => {
      if (task['@id']) {
        delete task['@id']
      }
    })

    calculatePriceDebounced(infos)
  }, [
    mode,
    overridePrice,
    values,
    convertValuesToPayload,
    calculatePriceDebounced,
  ])

  useEffect(() => {
    if (overridePrice && newPrice.VAT > 0) {
      setFieldValue('variantIncVATPrice', Math.round(newPrice.VAT * 100))
    }

    if (!overridePrice) {
      setFieldValue('variantIncVATPrice', null)
      setFieldValue('variantName', null)
    }
  }, [newPrice, overridePrice, setFieldValue])

  return (
    <div>
      {mode === Mode.DELIVERY_UPDATE ? (
        <>
          <div className="font-weight-semi-bold mb-1 total__price">
            {overridePrice
              ? t('DELIVERY_FORM_OLD_PRICE')
              : t('DELIVERY_FORM_TOTAL_PRICE')}
          </div>
          <div>
            {overridePrice ? (
              <s>
                {money(currentPrice.exVAT)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}
              </s>
            ) : (
              <span>
                {money(currentPrice.exVAT)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}
              </span>
            )}
          </div>
          <div>
            {overridePrice ? (
              <s data-testid="tax-included-previous">
                {money(currentPrice.VAT)} {t('DELIVERY_FORM_TOTAL_VAT')}
              </s>
            ) : (
              <span data-testid="tax-included-previous">
                {money(currentPrice.VAT)} {t('DELIVERY_FORM_TOTAL_VAT')}
              </span>
            )}
          </div>
          {overridePrice && (
            <div className="mb-1">
              <div className="font-weight-bold mb-1 total__price">
                {t('DELIVERY_FORM_NEW_PRICE')}
              </div>

              <span>
                {money(newPrice.exVAT * 100 || 0)}{' '}
                {t('DELIVERY_FORM_TOTAL_EX_VAT')}
              </span>
              <br />
              <span data-testid="tax-included">
                {money(newPrice.VAT * 100 || 0)} {t('DELIVERY_FORM_TOTAL_VAT')}
              </span>
            </div>
          )}
        </>
      ) : (
        <>
          <li
            className={`list-group-item d-flex flex-column ${overridePrice ? 'text-decoration-line-through' : ''}`}>
            <div>
              <span className="font-weight-semi-bold">
                {t('DELIVERY_FORM_TOTAL_PRICE')}
              </span>
              {!priceErrorMessage ? (
                calculatePriceData && calculatePriceData.amount ? (
                  <TotalPrice
                    className={`pull-right ${overridePrice ? 'text-decoration-line-through' : ''}`}
                    priceWithTaxes={calculatePriceData.amount / 100}
                    priceWithoutTaxes={
                      (calculatePriceData.amount -
                        calculatePriceData.tax.amount) /
                      100
                    }
                  />
                ) : (
                  <TotalPrice
                    className={`pull-right ${overridePrice ? 'text-decoration-line-through' : ''}`}
                    priceWithTaxes={0}
                    priceWithoutTaxes={0}
                  />
                )
              ) : null}
            </div>
          </li>
          {!overridePrice && priceErrorMessage ? (
            <div className="alert alert-danger" role="alert">
              {isDispatcher
                ? t('DELIVERY_FORM_ADMIN_PRICE_ERROR')
                : t('DELIVERY_FORM_SHOP_PRICE_ERROR')}
            </div>
          ) : null}
        </>
      )}

      {!overridePrice &&
        (isDispatcher || isDebugPricing) &&
        Boolean(calculateResponseData) && (
          <PriceCalculation
            className="mt-2"
            isDebugPricing={isDebugPricing}
            calculation={calculateResponseData.calculation}
            order={calculateResponseData.order}
          />
        )}

      {isDispatcher && (
        <div className="mt-2">
          <div
            style={{ maxWidth: '100%', cursor: 'pointer' }}
            onClick={() => {
              toggleOverridePrice(!overridePrice)
            }}>
            <div>
              <span>{t('DELIVERY_FORM_SET_MANUALLY_PRICE')}</span>
              <Checkbox
                className="ml-4 mb-1"
                name="delivery.override_price"
                checked={overridePrice}
                onChange={e => {
                  e.stopPropagation()
                  toggleOverridePrice(e.target.checked)
                }}
              />
            </div>
          </div>
          {overridePrice && (
            <OverridePriceForm setPrice={setNewPrice} taxRate={taxRate} />
          )}
        </div>
      )}
    </div>
  )
}
