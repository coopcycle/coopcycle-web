import React, { useState, useEffect, useCallback, useMemo } from 'react'
import { Checkbox } from 'antd'
import { useTranslation } from 'react-i18next'

import { money } from '../../../../assets/react/controllers/Incident/utils'
import Spinner from '../core/Spinner'
import { PriceCalculation } from '../../delivery/PriceCalculation'

import './ShowPrice.scss'
import { useHttpClient } from '../../user/useHttpClient'
import {
  useDeliveryFormFormikContext
} from './hooks/useDeliveryFormFormikContext'
import _ from 'lodash'
import OverridePriceForm from './OverridePriceForm'
import { useCalculatePriceMutation } from '../../api/slice'

const baseURL = location.protocol + '//' + location.host

export default ({
  storeNodeId,
  order,
  isDebugPricing,
  isDispatcher,
  setPriceLoading,
}) => {
  const { values, isCreateOrderMode, isModifyOrderMode, setFieldValue } = useDeliveryFormFormikContext()

  const [overridePrice, setOverridePrice] = useState(() => {
    if (isCreateOrderMode) {
      // when cloning an order that has an arbitrary price
      if (values.variantIncVATPrice !== undefined && values.variantIncVATPrice !== null) {
        return true
      } else {
        return false
      }
    } else {
      return false
    }
  })

  const [taxRate, setTaxRate] = useState(null)

  // aka "old price"
  const currentPrice = useMemo(() => {
    if (isModifyOrderMode && order) {
      return { exVAT: +order.total - +order.taxTotal, VAT: +order.total, }
    }
  }, [order, isModifyOrderMode])

  const [newPrice, setNewPrice] = useState(0)

  const [calculatePrice, {
    data: calculatePriceData,
    error: calculatePriceError,
    isLoading: calculatePriceIsLoading,
  }] = useCalculatePriceMutation()

  const calculatePriceDebounced = useMemo(
    () => _.debounce(calculatePrice, 800)
    , [calculatePrice]);

  const { t } = useTranslation()

  const { httpClient } = useHttpClient()

  const convertValuesToPayload = useCallback((values) => {
    const infos = {
      store: storeNodeId,
      tasks: structuredClone(values.tasks),
    };
    return infos
  }, [storeNodeId])

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

  useEffect(() => {
    const data = calculatePriceData
    const error = calculatePriceError

    if (error) {
      setNewPrice(0)
    }

    if (data) {
      setNewPrice(data)
    }

  }, [calculatePriceData, calculatePriceError])

  useEffect(() => {
    if (isModifyOrderMode) {
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
    if (!values.tasks.every(task => ((task.timeSlotUrl && task.timeSlot) || !task.timeSlotUrl))) {
      return
    }

    const infos = convertValuesToPayload(values)
    infos.tasks.forEach(task => {
      if (task["@id"]) {
        delete task["@id"]
      }
    })

    calculatePriceDebounced(infos)

  }, [isModifyOrderMode, overridePrice, values, convertValuesToPayload, calculatePriceDebounced]);

  useEffect(() => {
    if (overridePrice && newPrice.VAT > 0) {
      setFieldValue(
        'variantIncVATPrice',
        Math.round(newPrice.VAT * 100),
      )
    }

    if (!overridePrice) {
      setFieldValue('variantIncVATPrice', null)
      setFieldValue('variantName', null)
    }
  }, [newPrice, overridePrice, setFieldValue])

  useEffect(() => {
    const getDeliveryTaxs = async () => {
      const { response, error } = await httpClient.get(
        `${baseURL}/api/tax_rates`
      )

      if (error) {
        return
      }

      if (response) {
        const taxRates = await response['hydra:member']
        setTaxRate(
          taxRates.find(tax => tax.category === 'SERVICE') ||
            taxRates.find(tax => tax.category === 'BASE_STANDARD'),
        )
      }
    }
    getDeliveryTaxs()
  }, [httpClient])

  return (
    <div className="pl-2">
      {
        isModifyOrderMode ?
          <>
            <div className="font-weight-bold mb-1 total__price">
              {overridePrice
                ? t('DELIVERY_FORM_OLD_PRICE')
                : t('DELIVERY_FORM_TOTAL_PRICE')}
            </div>
            <div>
              {overridePrice ?
                <s>{money(currentPrice.exVAT)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}</s> :
                <span>{money(currentPrice.exVAT)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}</span>
              }
            </div>
            <div>
              {overridePrice ?
                <s data-testid="tax-included-previous">{money(currentPrice.VAT)} {t('DELIVERY_FORM_TOTAL_VAT')}</s> :
                <span data-testid="tax-included-previous">{money(currentPrice.VAT)} {t('DELIVERY_FORM_TOTAL_VAT')}</span>
              }
            </div>
            {overridePrice && (
              <div className="mb-1">
                <div className="font-weight-bold mb-1 total__price">
                  {t('DELIVERY_FORM_NEW_PRICE')}
                </div>

                <span>
                  {money(newPrice.exVAT * 100 || 0)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}
                </span><br />
                <span data-testid="tax-included">
                  {money(newPrice.VAT * 100 || 0)} {t('DELIVERY_FORM_TOTAL_VAT')}
                </span>
              </div>
            )}
          </>
        :
          <>
            <div className="font-weight-bold mb-1 total__price">
              {t('DELIVERY_FORM_TOTAL_PRICE')}
            </div>
            {
              priceErrorMessage && !overridePrice ? (
                <div className="alert alert-danger" role="alert">
                  {isDispatcher ? t('DELIVERY_FORM_ADMIN_PRICE_ERROR') : t('DELIVERY_FORM_SHOP_PRICE_ERROR')}
                </div>
              ) :
              !overridePrice ?
                calculatePriceIsLoading ?
                  <Spinner /> :
                  newPrice.amount ?
                    <>
                      <span>{money(newPrice.amount - newPrice.tax.amount,)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}</span><br />
                      <span data-testid="tax-included">{money(newPrice.amount)} {t('DELIVERY_FORM_TOTAL_VAT')}</span>
                    </> :
                    <>
                      <span>{money(0)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}</span><br/>
                      <span>{money(0)} {t('DELIVERY_FORM_TOTAL_VAT')}</span>
                    </>
                : null
            }
          </>
        }

        {!overridePrice && (isDispatcher || isDebugPricing) && Boolean(calculateResponseData) && (
          <PriceCalculation
            className="mt-2"
            isDebugPricing={isDebugPricing}
            calculation={calculateResponseData.calculation}
            orderItems={calculateResponseData.items}
            itemsTotal={calculateResponseData.amount}
          />
        )}

        {isDispatcher && (
          <div className="mt-2">
            <div
              style={{ maxWidth: '100%', cursor: 'pointer' }}
              onClick={() => {
                setOverridePrice(!overridePrice)
                setNewPrice(0)
              }}
            >
              <div>
                <span>{t('DELIVERY_FORM_SET_MANUALLY_PRICE')}</span>
                <Checkbox
                  className="ml-4 mb-1"
                  name="delivery.override_price"
                  checked={overridePrice}
                  onChange={e => {
                    e.stopPropagation()
                    setOverridePrice(e.target.checked)
                    setNewPrice(0)
                  }}></Checkbox>
              </div>
            </div>
            {overridePrice && (
              <OverridePriceForm
                setPrice={setNewPrice}
                taxRate={taxRate}
              />
            )}
          </div>
        )}
    </div>
  )
}
