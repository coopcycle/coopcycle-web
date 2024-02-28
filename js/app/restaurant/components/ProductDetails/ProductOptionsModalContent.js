import React, { forwardRef, useContext } from 'react'
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

export default forwardRef(({ product, options, images, formAction, onSubmit, onClickClose }, ref) => {

  const [ state ] = useContext(ProductOptionsModalContext)
  const offsets = getOffsets(options)

  const { t } = useTranslation()

  // Scroll to the next option
  // useEffect(() => {
  //   const first = _.first(state.options)
  //   const firstInvalid = _.find(state.options, opt => !opt.valid)
  //   if (firstInvalid && firstInvalid !== first) {
  //     document.getElementById(`product-option-group-${firstInvalid.code}`)
  //       .scrollIntoView({ behavior: 'smooth' })
  //   }
  // }, [ state ]);

  const sumTotal = (state.total * state.quantity) / 100

  return (
    // FIXME
    // The id is used in Cypress tests
    // It would be better to use data attributes
    <form id={ `${product.code}-options` }
      action={ formAction }
      onSubmit={ onSubmit }
      ref={ ref }
      className="product-modal-container">
      <ProductModalHeader name={ product.name }
        onClickClose={ onClickClose } />
      <main className='modal-body'>
        { images.length > 0 && (
          <ProductImagesCarousel images={ images } />
        ) }
        <ProductInfo product={ product } />
        <ProductQuantity />
        { options.map((option, index) => (
          <OptionGroup
            key={ `option-${index}` }
            index={ offsets[index] }
            option={ option } />
        )) }
      </main>
      <footer className="modal-footer">
        {state.missingMandatoryOptions === 0 ? (
          <button type="submit" className="btn btn-lg btn-block btn-primary">
            <span data-product-total className="button-composite">
              <i className="fa fa-shopping-cart"></i>
              <span>{t('ADD_TO_CART')}</span>
              <span>{(sumTotal).formatMoney()}</span>
            </span>
          </button>
        ) : (
          <Alert message={t('CART_PRODUCT_OPTIONS_MANDATORY', { count: state.missingMandatoryOptions })}
                 type="info" showIcon/>)
        }
      </footer>
    </form>
  )
})
