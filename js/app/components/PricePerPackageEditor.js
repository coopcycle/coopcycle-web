import React, { useState } from 'react'
import { getCurrencySymbol } from '../i18n'
import { useTranslation } from 'react-i18next'

export default ({ packages, defaultValue, onChange }) => {

  const { t } = useTranslation()

  const [ unitPrice, setUnitPrice ] = useState(defaultValue.unitPrice || 0)
  const [ packageName, setPackageName ] = useState(defaultValue.packageName || packages[0])
  const [ offset, setOffset ] = useState(defaultValue.offset || 0)
  const [ discountPrice, setDiscountPrice ] = useState(defaultValue.discountPrice || 0)
  const [ withDiscount, setWithDiscount ] = useState(defaultValue.offset > 0)

  return (
    <div>
      <div>
        <label className="mr-2">
          <input
            type="number"
            defaultValue={ unitPrice / 100 }
            size="4"
            min="0"
            step=".001"
            className="form-control d-inline-block"
            style={{ width: '80px' }}
            onChange={ e => {
              setUnitPrice(e.target.value * 100)
              onChange({
                packageName,
                unitPrice: e.target.value * 100,
                offset,
                discountPrice,
              })
            }} />
            <span className="ml-2">{ getCurrencySymbol() }</span>
        </label>
        <label className="mr-2">
          <span className="mx-2">{ t('PRICE_RANGE_EDITOR.PER_PACKAGE') }</span>
          <select
            defaultValue={ packageName }
            onChange={ e => {
              setPackageName(e.target.value)
              onChange({
                packageName: e.target.value,
                unitPrice,
                offset,
                discountPrice,
              })
            }}>
            { packages.map(pkg => (<option key={ pkg }>{ pkg }</option>)) }
          </select>
        </label>
      </div>
      { withDiscount && (
        <div>
          <label className="mr-2">
            <input
              type="number"
              defaultValue={ discountPrice / 100 }
              size="4"
              min="0"
              step=".1"
              className="form-control d-inline-block"
              style={{ width: '80px' }}
              onChange={ e => {
                setDiscountPrice(e.target.value * 100)
                onChange({
                  packageName,
                  unitPrice,
                  offset,
                  discountPrice: e.target.value * 100,
                })
              }} />
              <span className="ml-2">{ getCurrencySymbol() }</span>
          </label>
          <label className="mr-2">
            <span className="mx-2">{ t('PRICE_RANGE_EDITOR.PER_PACKAGE_STARTING') }</span>
            <input
              type="number"
              defaultValue={ offset }
              size="4"
              min="2"
              step="1"
              className="form-control d-inline-block"
              style={{ width: '80px' }}
              onChange={ e => {
                setOffset(parseInt(e.target.value, 10))
                onChange({
                  packageName,
                  unitPrice: e.target.value * 100,
                  offset: parseInt(e.target.value, 10),
                  discountPrice
                })
              }} />
          </label>
          <button type="button" className="btn btn-xs btn-default"
            onClick={ () => {
              setWithDiscount(false)
              setOffset(0)
              onChange({
                packageName,
                unitPrice,
                offset: 0,
                discountPrice,
              })
            }}>
            <i className="fa fa-times mr-1"></i>
            <span>{ t('PRICE_RANGE_EDITOR.PER_PACKAGE_DEL_DISCOUNT') }</span>
          </button>
        </div>
      ) }
      { !withDiscount && (
        <button type="button" className="btn btn-xs btn-default"
          onClick={ () => {
            setWithDiscount(true)
            setOffset(2)
            setDiscountPrice(100)
            onChange({
              packageName,
              unitPrice,
              offset: 2,
              discountPrice: 100,
            })
          }}>
          <i className="fa fa-plus mr-1"></i>
          <span>{ t('PRICE_RANGE_EDITOR.PER_PACKAGE_ADD_DISCOUNT') }</span>
        </button>
      )}
    </div>
  )
}
