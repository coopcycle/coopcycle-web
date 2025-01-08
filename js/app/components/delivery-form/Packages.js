import React, { useEffect, useState } from 'react'
import { Button, Input } from 'antd'
import { useFormikContext } from 'formik'
import { useTranslation } from 'react-i18next'

import './Packages.scss'

export default ({ storeId, index, packages }) => {
  const { setFieldValue, errors } = useFormikContext()

  const [packagesType, setPackagesType] = useState([])
  const [packagesPicked, setPackagesPicked] = useState([])

  const { t } = useTranslation()

  useEffect(() => {
    /** format the data in order to use them with the pickers.  */
    const picked = []

    for (const p of packages) {
      const newPackages = {
        type: p.name,
        quantity: 0,
      }
      picked.push(newPackages)
    }

    setPackagesType(packages)
    setPackagesPicked(picked)
  }, [storeId, packages])

  useEffect(() => {
    setFieldValue(`tasks[${index}].packages`, packagesPicked)
  }, [packagesPicked, setFieldValue, index])

  const handlePlusButton = item => {
    const pack = packagesPicked.find(p => p.type === item.name)
    const index = packagesPicked.findIndex(p => p === pack)
    if (index !== -1) {
      const newPackagesPicked = [...packagesPicked]
      newPackagesPicked[index] = {
        type: pack.type,
        quantity: pack.quantity + 1,
      }
      setPackagesPicked(newPackagesPicked)
    }
  }

  const handleMinusButton = item => {
    const pack = packagesPicked.find(p => p.type === item.name)
    const index = packagesPicked.findIndex(p => p === pack)

    if (index !== -1) {
      const newPackagesPicked = [...packagesPicked]
      newPackagesPicked[index] = {
        type: pack.type,
        quantity: pack.quantity > 0 ? pack.quantity - 1 : 0,
      }
      setPackagesPicked(newPackagesPicked)
    }
  }

  /**Used to make the input a controlated field */
  const gatPackageQuantity = item => {
    const sameTypePackage = packagesPicked.find(p => p.type === item.name)
    return sameTypePackage.quantity
  }

  return (
    <>
      <div className="mb-2 font-weight-bold">{t('DELIVERY_FORM_PACKAGES')}</div>
      {packagesType.map(item => (
        <div key={item['@id']} className="packages-item mb-2">
          <div className="packages-item__quantity ">
            <div className="packages-item__quantity__button">
              <Button onClick={() => handleMinusButton(item)}>-</Button>
            </div>

            <div className="packages-item__quantity__input text-center">
              <Input
                value={gatPackageQuantity(item)}
                style={
                  gatPackageQuantity(item) !== 0 ? { fontWeight: '700' } : null
                }
              />
            </div>

            <div className="packages-item__quantity__button">
              <Button
                onClick={() => {
                  handlePlusButton(item)
                }}>
                +
              </Button>
            </div>
          </div>
          <div className="packages-item__name ">
            <Input readOnly value={item.name} />
          </div>
        </div>
      ))}
      {errors.tasks?.[index]?.packages && (
        <div className="text-danger">{errors.tasks[index].packages}</div>
      )}
    </>
  )
}
