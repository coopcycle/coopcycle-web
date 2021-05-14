import React, { useState, useContext, forwardRef } from 'react'
import { useTranslation } from 'react-i18next'

import ProductImagesCarousel from './ProductImagesCarousel'
import ProductModalHeader from './ProductModalHeader'
import {
  ProductOptionsModalContext,
  useProductOptions,
  getValuesRange } from './ProductOptionsModalContext'

const OptionValueLabel = ({ option, optionValue }) => (
  <span>
    <span>{ optionValue.value }</span>
    { (option.strategy === 'option_value' && optionValue.price > 0) && (
      <span>
        <br />
        <small className="text-muted">+{ (optionValue.price / 100).formatMoney() }</small>
      </span>
    ) }
  </span>
)

const OptionValue = ({ index, option, optionValue }) => {

  const { setValueQuantity } = useProductOptions()

  return (
    <div className="radio m-0">
      <label className="d-flex align-items-center">
        <input
          type="radio"
          name={ `options[${index}][code]` }
          value={ optionValue.code }
          onClick={ () => {
            window._paq.push(['trackEvent', 'Checkout', 'selectOption'])
            setValueQuantity(option, optionValue, 1)
          }} />
        <OptionValueLabel option={ option } optionValue={ optionValue } />
      </label>
    </div>
  )
}

const AdditionalOptionValue = ({ index, valueIndex, option, optionValue }) => {

  const { setValueQuantity, getValueQuantity } = useProductOptions()
  const valuesRange = getValuesRange(option)
  const quantity = getValueQuantity(option, optionValue)

  let inputProps = {}
  if (!valuesRange.isUpperInfinite) {
    inputProps = { ...inputProps, max: valuesRange.upper }
  }

  const realIndex = index + valueIndex

  return (
    <div className="product-option-item-range">
      <input type="hidden" name={ `options[${realIndex}][code]` } value={ optionValue.code } />
      <input
        name={ `options[${realIndex}][quantity]` }
        type="number"
        step="1"
        min="0"
        value={ quantity }
        onChange={ e => {
          setValueQuantity(option, optionValue, parseInt(e.currentTarget.value, 10))
        }}
        { ...inputProps } />
      <label htmlFor={ '' } onClick={ () => {
        setValueQuantity(option, optionValue, quantity + 1)
      }}>
        <OptionValueLabel option={ option } optionValue={ optionValue } />
      </label>
      <div className="product-option-item-range-buttons">
        <button className="button-icon--decrement" type="button" onClick={ () => {
          quantity > 0 && setValueQuantity(option, optionValue, quantity - 1)
        }}>
          <i className="fa fa-lg fa-minus-circle"></i>
        </button>
        <button className="button-icon--increment" type="button" onClick={ () => {
          setValueQuantity(option, optionValue, quantity + 1)
        }}>
          <i className="fa fa-lg fa-plus-circle"></i>
        </button>
      </div>
    </div>
  )
}

const ValuesRange = ({ option }) => {

  const { t } = useTranslation()

  if (option.additional) {

    const valuesRange = getValuesRange(option)

    const min = parseInt(valuesRange.lower, 10)
    const max = valuesRange.isUpperInfinite ? Infinity : parseInt(valuesRange.upper, 10)

    if (min === 0 && max !== Infinity) {
      return (
        <small className="ml-2">{ t('CART_PRODUCT_OPTIONS_VALUES_RANGE_UP_TO', {
          count: max,
        }) }</small>
      )
    }

    if (min > 0 && max === Infinity) {
      return (
        <small className="ml-2">{ t('CART_PRODUCT_OPTIONS_VALUES_RANGE_AT_LEAST', {
          count: min,
        }) }</small>
      )
    }

    if (min > 0 && max !== Infinity) {
      return (
        <small className="ml-2">{ t('CART_PRODUCT_OPTIONS_VALUES_RANGE', {
          min,
          max,
        }) }</small>
      )
    }
  }

  return null
}

export const OptionGroup = ({ index, option }) => (
  <div id={ `product-option-group-${option.code}` } className="pt-4">
    <h4 className="m-0 mb-4">
      <span>{ option.name }</span>
      <ValuesRange option={ option } />
    </h4>
    <div className="list-group">
      { option.values.map((optionValue, optionValueIndex) => {

        return (
          <div className="list-group-item product-option-item" key={ `option-value-${optionValueIndex}` }>
            { !option.additional && <OptionValue
              option={ option }
              optionValue={ optionValue }
              index={ index }
              valueIndex={ optionValueIndex } />
            }
            { option.additional && <AdditionalOptionValue
              option={ option }
              optionValue={ optionValue }
              index={ index }
              valueIndex={ optionValueIndex } />
            }
          </div>
        )
      })}
    </div>
  </div>
)

const getOffset = (options, index) => {

  if (index === 0) {
    return 0
  }

  const prevOption = options[index - 1]
  const prevOffset = getOffset(options, (index - 1))

  return prevOffset + (prevOption.additional ? prevOption.values.length : 1)
}

/* Exported to be able to test it */
export const getOffsets = (options) => options.map((option, index) => getOffset(options, index))

export default forwardRef(({ name, code, options, images, formAction, onSubmit, onClickClose }, ref) => {

  const [ quantity, setQuantity ] = useState(1)
  const [ state ] = useContext(ProductOptionsModalContext)
  const offsets = getOffsets(options)

  // Scroll to the next option
  // useEffect(() => {
  //   const first = _.first(state.options)
  //   const firstInvalid = _.find(state.options, opt => !opt.valid)
  //   if (firstInvalid && firstInvalid !== first) {
  //     document.getElementById(`product-option-group-${firstInvalid.code}`)
  //       .scrollIntoView({ behavior: 'smooth' })
  //   }
  // }, [ state ]);

  return (
    // FIXME
    // The id is used in Cypress tests
    // It would be better to use data attributes
    <form id={ `${code}-options` }
      action={ formAction }
      onSubmit={ onSubmit }
      ref={ ref }
      className="product-modal-container">
      <ProductModalHeader name={ name }
        onClickClose={ onClickClose } />
      <main>
        { images.length > 1 && (
          <ProductImagesCarousel images={ images } />
        ) }
        <div className="px-4">
        { options.map((option, index) => (
          <OptionGroup
            key={ `option-${index}` }
            index={ offsets[index] }
            option={ option } />
        )) }
        </div>
      </main>
      <footer className="p-4 border-top">
        <div className="row">
          <div className="col-xs-12 col-sm-6 col-sm-offset-3">
            <div className="form-group">
              <div className="quantity-input-group">
                <button className="quantity-input-group__decrement" type="button"
                  onClick={ () => {
                    if (quantity > 1) {
                      setQuantity(quantity - 1)
                    }
                  } }>
                  <i className="fa fa-2x fa-minus-circle"></i>
                </button>
                <input type="number" min="1" step="1" value={ quantity }
                  data-product-quantity
                  onChange={ e => setQuantity(parseInt(e.currentTarget.value, 10)) }  />
                <button className="quantity-input-group__increment" type="button"
                  onClick={ () => setQuantity(quantity + 1) }>
                  <i className="fa fa-2x fa-plus-circle"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
        <button type="submit" className="btn btn-lg btn-block btn-primary" disabled={ state.disabled }>
          <span data-product-total>{ ((state.total * quantity) / 100).formatMoney() }</span>
        </button>
      </footer>
    </form>
  )
})
