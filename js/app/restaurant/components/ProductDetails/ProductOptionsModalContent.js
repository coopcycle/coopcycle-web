import React, { forwardRef, useContext, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Alert } from 'antd'

import {
  ProductOptionsModalContext,
} from './ProductOptionsModalContext'
import ProductImagesCarousel from './ProductImagesCarousel'
import ProductModalHeader from './ProductModalHeader'
import { OptionGroup } from './ProductOptionGroup'
import ProductInfo from './ProductInfo'
import ProductQuantity from './ProductQuantity'
import { isMandatory, isValid } from './useProductOptions'

const getOffset = (options, index) => {

  if (index === 0) {
    return 0
  }

  const prevOption = options[index - 1]
  const prevOffset = getOffset(options, (index - 1))

  return prevOffset + (prevOption.additional ? prevOption.values.length : 1)
}

/* Exported to be able to test it */
export const getOffsets = (options) => options.map(
  (option, index) => getOffset(options, index))

export default forwardRef((props, ref) => {
  const { product, images, formAction, onSubmit, onClickClose } = props
  const [ state ] = useContext(ProductOptionsModalContext)
  const options = state.options
  const sumTotal = (state.total * state.quantity) / 100

  const missingMandatoryOptions = state.options.filter(
    opt => isMandatory(opt) && !isValid(opt)).length
  const invalidOptions = state.options.filter(opt => !isValid(opt)).length

  const offsets = getOffsets(options)

  const { t } = useTranslation()

  const alertMessage = useMemo(() => {
    if (missingMandatoryOptions > 0) {
      return t('CART_PRODUCT_OPTIONS_MANDATORY',
        { count: missingMandatoryOptions })
    } else if (invalidOptions > 0) {
      return t('CART_PRODUCT_OPTIONS_INVALID')
    } else {
      return ''
    }
  }, [ missingMandatoryOptions, invalidOptions ])

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
    <form id={ `${ product.code }-options` }
          action={ formAction }
          onSubmit={ onSubmit }
          ref={ ref }
          className="product-modal-container">
      <ProductModalHeader
        name={ product.name }
        onClickClose={ onClickClose } />
      <main className="modal-body">
        { images.length > 0 && (
          <ProductImagesCarousel images={ images } />
        ) }
        <ProductInfo product={ product } />
        <ProductQuantity />
        { options.map((option, index) => (
          <OptionGroup
            key={ `option-${ index }` }
            index={ offsets[index] }
            option={ option } />
        )) }
      </main>
      <footer className="modal-footer">
        { (missingMandatoryOptions === 0 && invalidOptions === 0) ? (
          <button type="submit" className="btn btn-lg btn-block btn-primary">
              <span data-product-total className="button-composite">
                <i className="fa fa-shopping-cart"></i>
                <span>{ t('ADD_TO_CART') }</span>
                <span>{ (sumTotal).formatMoney() }</span>
              </span>
          </button>
        ) : (
          <Alert
            message={ alertMessage }
            type="info"
            showIcon />) }
      </footer>
    </form>
  )
})
