import React, { useCallback, useEffect, useState } from 'react'
import { useFormikContext, Field } from 'formik'
import AddressBookNew from './AddressBookNew'
import SwitchTimeSlotFreePicker from './SwitchTimeSlotFreePicker'
import { Input } from 'antd'
import DateRangePicker from './DateRangePicker'
import Packages from './Packages'
import { useTranslation } from 'react-i18next'
import TotalWeight from './TotalWeight'

import './Task.scss'

const baseURL = location.protocol + '//' + location.host

export default ({ addresses, storeId, index, storeDeliveryInfos }) => {
  
  const httpClient = new window._auth.httpClient()

  const [packages, setPackages] = useState(null)

  const { t } = useTranslation()

  const { values } = useFormikContext()
  const task = values.tasks[index]

  const format = 'LL'

  useEffect(() => {
    const getPackages = async () => {
      const url = `${baseURL}/api/stores/${storeId}/packages`

      const { response } = await httpClient.get(url)

      if (response) {
        const packages = response['hydra:member']
        if (packages?.length > 0) {
          setPackages(packages)
        }
      }
    }
    getPackages()
  }, [storeId])

  const areDefinedTimeSlots = useCallback(() => {
    return (
      storeDeliveryInfos &&
      Array.isArray(storeDeliveryInfos.timeSlots) &&
      storeDeliveryInfos.timeSlots.length > 0
    )
  }, [storeDeliveryInfos])

  return (
    <div className="task">
      <div className="task__header">
        {task.type === 'PICKUP' ? (
          <i className="fa fa-arrow-up"></i>
        ) : (
          <i className="fa fa-arrow-down"></i>
        )}
        <h3 className="task__header__title mb-4">
          {task.type === 'PICKUP'
            ? t('DELIVERY_FORM_PICKUP_INFORMATIONS')
            : t('DELIVERY_FORM_DROPOFF_INFORMATIONS')}
        </h3>
      </div>

      <div className="task__body">
        <AddressBookNew addresses={addresses} index={index} />

        {task.type === 'DROPOFF' ? (
          <div>
            {packages ? (
              <Packages storeId={storeId} index={index} packages={packages} />
            ) : null}
            <TotalWeight index={index} />
          </div>
        ) : null}

        {areDefinedTimeSlots() ? (
          <SwitchTimeSlotFreePicker
            storeId={storeId}
            storeDeliveryInfos={storeDeliveryInfos}
            index={index}
            format={format}
          />
        ) : (
          <DateRangePicker format={format} index={index} />
        )}
        <div className="mt-4 mb-4">
          <label
            htmlFor={`tasks[${index}].comments`}
            className="block mb-2 font-weight-bold">
            {t('ADMIN_DASHBOARD_TASK_FORM_COMMENTS_LABEL')}
          </label>
          <Field
            as={Input.TextArea}
            name={`tasks[${index}].comments`}
            placeholder={t('ADMIN_DASHBOARD_TASK_FORM_COMMENTS_PLACEHOLDER')}
            rows={4}
            style={{ resize: 'none' }}
          />
        </div>
      </div>
    </div>
  )
}
