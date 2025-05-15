import React, { useState, useEffect, useCallback } from 'react'
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

const baseURL = location.protocol + '//' + location.host

export default ({
  storeNodeId,
  order,
  isDebugPricing,
  isDispatcher,
  priceLoading,
  setPriceLoading,
}) => {
  const [calculateResponseData, setCalculateResponseData] = useState(null)
  const [calculatedPrice, setCalculatePrice] = useState(0)
  const [priceErrorMessage, setPriceErrorMessage] = useState('')
  const [overridePrice, setOverridePrice] = useState(false)

  const [taxRate, setTaxRate] = useState(null)

  const { t } = useTranslation()
  const { values, isCreateOrderMode, isModifyOrderMode, setFieldValue } = useDeliveryFormFormikContext()

  const { httpClient } = useHttpClient()

  let deliveryPrice

  if (isModifyOrderMode && order) {
    deliveryPrice = { exVAT: +order.total, VAT: +order.total - +order.taxTotal, }
  }

  const convertValuesToPayload = useCallback((values) => {
    const infos = {
      store: storeNodeId,
      tasks: structuredClone(values.tasks),
    };
    return infos
  }, [storeNodeId])

  const getPrice = _.debounce(
    (values) => {

      const infos = convertValuesToPayload(values)
      infos.tasks.forEach(task => {
        if (task["@id"]) {
          delete task["@id"]
        }
      })

      const calculatePrice = async () => {

        setPriceLoading(true)

        const url = `${baseURL}/api/retail_prices/calculate`
        const { response, error } = await httpClient.post(url, infos)

        if (error) {
          setCalculateResponseData(error.response.data)
          setPriceErrorMessage(error.response.data['hydra:description'])
          setCalculatePrice(0)
        }

        if (response) {
          setCalculateResponseData(response)
          setCalculatePrice(response)
          setPriceErrorMessage('')

        }

        setPriceLoading(false)

      }

      // Don't calculate price until all tasks have an address
      if (!values.tasks.every(task => task.address.streetAddress)) {
        return
      }

      // Don't calculate price if a time slot (timeSlotUrl) is selected, but no choice (timeSlot) is made yet
      if (!values.tasks.every(task => ((task.timeSlotUrl && task.timeSlot) || !task.timeSlotUrl))) {
        return
      }

      calculatePrice()
    },
    800
  )

  useEffect(() => {
    if (!overridePrice && isCreateOrderMode) {
      getPrice(values)
    }
  }, [values]);

  useEffect(() => {
    if (overridePrice && calculatedPrice.VAT > 0) {
      setFieldValue(
        'variantIncVATPrice',
        Math.round(calculatedPrice.VAT * 100),
      )
    }

    if (!overridePrice) {
      setFieldValue('variantIncVATPrice', null)
      setFieldValue('variantName', null)
    }
  }, [calculatedPrice, overridePrice, setFieldValue])

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
              {!overridePrice
                ? t('DELIVERY_FORM_TOTAL_PRICE')
                : t('DELIVERY_FORM_OLD_PRICE')}
            </div>
            <div>
              {overridePrice ?
                <s>{money(deliveryPrice.VAT)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}</s> :
                <span>{money(deliveryPrice.VAT)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}</span>
              }
            </div>
            <div>
              {overridePrice ?
                <s>{money(deliveryPrice.exVAT)} {t('DELIVERY_FORM_TOTAL_VAT')}</s> :
                <span data-testid="tax-included-previous">{money(deliveryPrice.exVAT)} {t('DELIVERY_FORM_TOTAL_VAT')}</span>
              }
            </div>
            {overridePrice && (
              <div className="mb-1">
                <div className="font-weight-bold mb-1 total__price">
                  {t('DELIVERY_FORM_NEW_PRICE')}
                </div>

                <span>
                  {money(calculatedPrice.exVAT * 100 || 0)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}
                </span><br />
                <span data-testid="tax-included">
                  {money(calculatedPrice.VAT * 100 || 0)} {t('DELIVERY_FORM_TOTAL_VAT')}
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
                priceLoading ?
                  <Spinner /> :
                  calculatedPrice.amount ?
                    <>
                      <span>{money(calculatedPrice.amount - calculatedPrice.tax.amount,)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}</span><br />
                      <span data-testid="tax-included">{money(calculatedPrice.amount)} {t('DELIVERY_FORM_TOTAL_VAT')}</span>
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
                setCalculatePrice(0)
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
                    setCalculatePrice(0)
                  }}></Checkbox>
              </div>
            </div>
            {overridePrice && (
              <OverridePriceForm
                setCalculatePrice={setCalculatePrice}
                taxRate={taxRate}
              />
            )}
          </div>
        )}
    </div>
  )
}
