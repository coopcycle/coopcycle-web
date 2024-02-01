import React, { forwardRef, useContext, useState } from 'react'

import {
  ProductOptionsModalContext,
} from './ProductOptionsModalContext'
import ProductImagesCarousel from './ProductImagesCarousel'
import ProductModalHeader from './ProductModalHeader'
import { OptionGroup } from './ProductOptionGroup'
import ProductInfo from './ProductInfo'

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
    <form id={ `${product.code}-options` }
      action={ formAction }
      onSubmit={ onSubmit }
      ref={ ref }
      className="product-modal-container p-4">
      <ProductModalHeader name={ product.name }
        onClickClose={ onClickClose } />
      <main>
        { images.length > 0 && (
          <ProductImagesCarousel images={ images } />
        ) }
        <ProductInfo product={ product } />
        <div>
        { options.map((option, index) => (
          <OptionGroup
            key={ `option-${index}` }
            index={ offsets[index] }
            option={ option } />
        )) }
        </div>
      </main>
      <footer className="border-top">
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
