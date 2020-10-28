import React, {
  useState,
  createRef,
  useImperativeHandle,
  forwardRef } from 'react'

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

const OptionValue = ({ index, option, optionValue, onClick }) => (
  <div className="radio nomargin">
    <label className="d-flex align-items-center">
      <input
        type="radio"
        name={ `options[${index}][code]` }
        value={ optionValue.code }
        onClick={ onClick } />
      <OptionValueLabel option={ option } optionValue={ optionValue } />
    </label>
  </div>
)

const AdditionalOptionValue = forwardRef(({ index, valueIndex, option, optionValue, onChange }, ref) => {

  if (option.additional && option.valuesRange) {

    const [ quantity, setQuantity ] = useState(0)

    useImperativeHandle(ref, () => ({
      getQuantity: () => quantity,
      getTotal: () => optionValue.price * quantity,
    }))

    let inputProps = {}
    if (!option.valuesRange.isUpperInfinite) {
      inputProps = { ...inputProps, max: option.valuesRange.upper }
    }

    const realIndex = index + valueIndex

    return (
      <div className="product-option-item-range" ref={ ref }>
        <input type="hidden" name={ `options[${realIndex}][code]` } value={ optionValue.code } />
        <input
          name={ `options[${realIndex}][quantity]` }
          type="number"
          step="1"
          min="0"
          value={ quantity }
          { ...inputProps } />
        <label htmlFor={ '' } onClick={ () => {
          setQuantity(quantity + 1)
          setTimeout(() => onChange(), 0)
        }}>
          <OptionValueLabel option={ option } optionValue={ optionValue } />
        </label>
        <div className="product-option-item-range-buttons">
          <button className="button-icon--decrement" type="button" onClick={ () => {
            quantity > 0 && setQuantity(quantity - 1)
            setTimeout(() => onChange(), 0)
          }}>
            <i className="fa fa-lg fa-minus-circle"></i>
          </button>
          <button className="button-icon--increment" type="button" onClick={ () => {
            setQuantity(quantity + 1)
            setTimeout(() => onChange(), 0)
          }}>
            <i className="fa fa-lg fa-plus-circle"></i>
          </button>
        </div>
      </div>
    )
  }
})

export const OptionGroup = forwardRef(({ index, option, onChange }, ref) => {

  const [ valid, setValid ] = useState(false)

  const optionValueRefs = []

  useImperativeHandle(ref, () => ({
    isValid: () => valid,
    getTotal: () => {
      return optionValueRefs.reduce(
        (acc, ref) => acc + (ref.current ? ref.current.getTotal() : 0),
        0
      )
    }
  }))

  return (
    <div ref={ ref }>
      <h4>{ option.name }</h4>
      <div className="list-group">
        { option.values.map((optionValue, optionValueIndex) => {

          const optionValueRef = createRef()
          optionValueRefs.push(optionValueRef)

          return (
            <div className="list-group-item product-option-item" key={ `option-value-${optionValueIndex}` }>
              { !option.additional && <OptionValue
                option={ option }
                optionValue={ optionValue }
                index={ index }
                onClick={ () => {
                  setValid(true)
                  setTimeout(() => onChange(), 0)
                }} /> }
              { option.additional && <AdditionalOptionValue
                ref={ optionValueRef }
                option={ option }
                optionValue={ optionValue }
                index={ index }
                valueIndex={ optionValueIndex }
                onChange={ () => {
                  const totalQuantity = optionValueRefs.reduce(
                    (acc, ref) => acc + ref.current.getQuantity(),
                    0
                  )

                  let isValid = true
                  if (option.valuesRange) {

                    const min = parseInt(option.valuesRange.lower, 10)

                    if (totalQuantity < min) {
                      isValid = false
                    }
                    if (!option.valuesRange.isUpperInfinite) {
                      const max = parseInt(option.valuesRange.upper, 10)
                      if (totalQuantity > max) {
                        isValid = false
                      }
                    }
                  }
                  setValid(isValid)
                  setTimeout(() => onChange(), 0)
                }} />
              }
            </div>
          )

        })}
      </div>
    </div>
  )
})

const getOffset = (option, prev, index) => {

  if (!prev || !prev.additional) {
    return index
  }

  return (index + prev.values.length) - 1
}

const getOffsets = (options) => options.map((option, index) => {

  let prev = null
  if (index > 0) {
    prev = options[index - 1]
  }

  return getOffset(option, prev, index)
})

export default ({ code, price, options, formAction, onSubmit }) => {

  const [ quantity, setQuantity ] = useState(1)
  const [ total, setTotal ] = useState(price * quantity)
  const [ disabled, setDisabled ] = useState(true)

  const optionGroups = []

  const offsets = getOffsets(options)

  return (
    <div id={ `${code}-options` }>
      <form key={ `product-${code}` } data-product-options action={ formAction } onSubmit={ onSubmit }>
        { options.map((option, index) => {

          const ref = createRef()
          optionGroups.push(ref)

          return (
            <OptionGroup ref={ ref }
              key={ `option-${index}` }
              index={ offsets[index] }
              option={ option }
              onChange={ () => {
                const invalidGroups = optionGroups.filter(optionGroup => !optionGroup.current.isValid())
                setDisabled(invalidGroups.length > 0)
                setTotal(optionGroups.reduce(
                  (acc, ref) => acc + (ref.current ? ref.current.getTotal() : 0),
                  price
                ))
              } } />
          )
        }) }
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
                <input type="number" min="1" step="1" value={ quantity } data-product-quantity />
                <button className="quantity-input-group__increment" type="button"
                  onClick={ () => setQuantity(quantity + 1) }>
                  <i className="fa fa-2x fa-plus-circle"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
        <button type="submit" className="btn btn-block btn-primary" disabled={ disabled }>
          <span data-product-total>{ ((total * quantity) / 100).formatMoney() }</span>
        </button>
      </form>
    </div>
  )
}
