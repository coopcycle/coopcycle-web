import React, { useEffect, useRef, useState } from 'react'
import { Button, Input } from 'antd'
import { useFormikContext } from 'formik'
import { useTranslation } from 'react-i18next'

import './Packages.scss'

export default ({ index, packages, deliveryId }) => {
  const { setFieldValue, errors, values } = useFormikContext()

  let picked = []

  for (const p of packages) {
    const newPackages = {
      type: p.name,
      quantity: 0,
    }
    picked.push(newPackages)
  }

  if (deliveryId) {
    const packagesToEdit = values.tasks[index].packages
    const newPackagesArray = picked.map(p => {
      const match = packagesToEdit.find(item => item.type === p.type)
      return match || p
    })
    picked = newPackagesArray
  }

  const [packagesPicked, setPackagesPicked] = useState(picked)

  console.log('packagesPicked', packagesPicked)

  const { t } = useTranslation()

  /** Insert the data in edit mode */

  useEffect(() => {
    const filteredPackages = packagesPicked.filter(p => p.quantity > 0)
    if (filteredPackages.length > 0) {
      setFieldValue(`tasks[${index}].packages`, filteredPackages)
    }
  }, [packagesPicked, setFieldValue, index])

  const triggerOnChangeEvent = (newValue) => {
    // Trigger manually the onchange event for the input when pressing the button, as we expect it from the form.onChange handler
    // https://stackoverflow.com/questions/23892547/what-is-the-best-way-to-trigger-change-or-input-event-in-react-js
    const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
      window.HTMLInputElement.prototype,
      'value').set;
    nativeInputValueSetter.call(inputRef.current.input, newValue);
    const event = new Event('input', { bubbles: true });
    inputRef.current.input.dispatchEvent(event);
  }

  const handlePlusButton = (item) => {

    const pack = packagesPicked.find(p => p.type === item.name)
    const index = packagesPicked.findIndex(p => p === pack)
    if (index !== -1) {
      const newPackagesPicked = [...packagesPicked]
      const newQuantity = pack.quantity + 1
      newPackagesPicked[index] = {
        type: pack.type,
        quantity: newQuantity,
      }

      triggerOnChangeEvent(newQuantity)
      setPackagesPicked(newPackagesPicked)
    }
  }

  const handleMinusButton = item => {
    const pack = packagesPicked.find(p => p.type === item.name)
    const index = packagesPicked.findIndex(p => p === pack)

    if (index !== -1) {
      const newPackagesPicked = [...packagesPicked]
      const newQuantity = pack.quantity > 0 ? pack.quantity - 1 : 0
      newPackagesPicked[index] = {
        type: pack.type,
        quantity: newQuantity,
      }

      setPackagesPicked(newPackagesPicked)
      triggerOnChangeEvent(newQuantity)
    }
  }

  /**Used to make the input a controlated field */
  const getPackagesItems = item => {
    const sameTypePackage = packagesPicked.find(p => p.type === item.name)
    return sameTypePackage.quantity
  }

  return (
    <>
      <div className="mb-2 font-weight-bold">{t('DELIVERY_FORM_PACKAGES')}</div>
      {packages.map(item => (
        <div key={item['@id']} className="packages-item mb-2">
          <div className="packages-item__quantity ">
            <Button
              className="packages-item__quantity__button"
              onClick={() => handleMinusButton(item)}>
              -
            </Button>

            <Input
              className="packages-item__quantity__input text-center"
              value={getPackagesItems(item)}
              style={
                getPackagesItems(item) !== 0 ? { fontWeight: '700' } : null
              }
              ref={inputRef}
              onChange={e => {
                const packageIndex = packagesPicked.findIndex(
                  p => p.type === item.name,
                )
                const newPackagesPicked = [...packagesPicked]
                newPackagesPicked[packageIndex] = {
                  type: item.name,
                  quantity: e.target.value,
                }
                console.log('new', newPackagesPicked)
                setPackagesPicked(newPackagesPicked)
              }}
            />

            <Button
              className="packages-item__quantity__button"
              onClick={() => {
                handlePlusButton(item)
              }}>
              +
            </Button>
          </div>
          <span className="packages-item__name border pl-3 pt-1 pb-1">
            {item.name}
          </span>
        </div>
      ))}
      {errors.tasks?.[index]?.packages && (
        <div className="text-danger">{errors.tasks[index].packages}</div>
      )}
    </>
  )
}
