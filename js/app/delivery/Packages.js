import axios from 'axios'
import React, { useEffect, useState } from 'react'
import { Button, Input } from 'antd'
import { useFormikContext } from 'formik'
import { useTranslation } from 'react-i18next'

const baseURL = location.protocol + '//' + location.host

export default ({ storeId, index }) => {
  const { setFieldValue, errors } = useFormikContext()

  const [packagesType, setPackagesType] = useState([])
  const [packagesPicked, setPackagesPicked] = useState([])

  const {t} = useTranslation()

  useEffect(() => {
    /** Fetch packages type and format the data in order to use them with the pickers.  */
    const getPackagesType = async () => {
      const jwtResp = await $.getJSON(window.Routing.generate('profile_jwt'))
      const jwt = jwtResp.jwt
      const url = `${baseURL}/api/stores/${storeId}/packages`

      const response = await axios.get(url, {
        headers: {
          Authorization: `Bearer ${jwt}`,
        },
      })
      const packages = await response.data['hydra:member']

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
    }

    if (storeId) {
      getPackagesType()
    }
  }, [storeId])

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
  const calculateValue = item => {
    const sameTypePackage = packagesPicked.find(p => p.type === item.name)
    return sameTypePackage.quantity
  }

  return (
    <>
      <div className="mb-2 font-weight-bold">{t("DELIVERY_FORM_PACKAGES")}</div>
      {packagesType.map(item => (
        <div key={item['@id']} className="row mb-2">
          <div className="col-xs-4" style={{ display: 'flex' }}>
            
              <Button
                style={{ backgroundColor: '#f5f5f5' }}
                color="default"
                variant="filled"
                onClick={() => handleMinusButton(item)}>
                -
              </Button>
          
              <div style={{ minWidth: '50%'}}>
              <Input
                className="text-center"
                value={calculateValue(item)}
                style={
                  calculateValue(item) !== 0 ? { fontWeight: '700'} : null
                }
              />
              </div>
      
            
              <Button
                style={{ backgroundColor: '#f5f5f5' }}
                color="default"
                variant="filled"
                onClick={() => {
                  handlePlusButton(item)
                }}>
                +
              </Button>
           
          </div>
          <div className="col-xs-8">
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
