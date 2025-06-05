import React, { useMemo, useState } from 'react'
import { Field } from 'formik'
import AddressBookNew from './AddressBook'
import { Input, Button } from 'antd'
import Packages from './Packages'
import { useTranslation } from 'react-i18next'
import TotalWeight from './TotalWeight'

import './Task.scss'
import TagsSelect from '../TagsSelect'
import { TaskDateTime } from './TaskDateTime'
import { useDeliveryFormFormikContext } from './hooks/useDeliveryFormFormikContext'
import {
  useGetStorePackagesQuery,
  useGetStoreTimeSlotsQuery,
} from '../../api/slice'
import { Mode } from './Mode'
import { useSelector } from 'react-redux'
import { selectMode } from './redux/formSlice'


export default ({
  isDispatcher,
  storeNodeId,
  addresses,
  index,
  storeDeliveryInfos,
  onRemove,
  showRemoveButton,
  tags,
}) => {
  const { t } = useTranslation()

  const mode = useSelector(selectMode)
  const {
    values,
    taskValues,
    setFieldValue,
  } = useDeliveryFormFormikContext({
    taskIndex: index,
  })

  const [showLess, setShowLess] = useState(
    taskValues.type === 'DROPOFF' && values.tasks.length > 2,
  )

  const { data: timeSlotsData } = useGetStoreTimeSlotsQuery(storeNodeId)
  const { data: packagesData } = useGetStorePackagesQuery(storeNodeId)

  const timeSlotLabels = useMemo(() => {
    if (timeSlotsData) {
      return timeSlotsData['hydra:member']
    }
    return []
  }, [timeSlotsData])

  const packages = useMemo(() => {
    if (packagesData) {
      return packagesData['hydra:member']
    }
    return null
  }, [packagesData])

  return (
    <div className="task border p-4 mb-4" data-testid={`form-task-${index}`}>
      <div
        className={
          taskValues.type === 'PICKUP'
            ? 'task__header task__header--pickup'
            : 'task__header task__header--dropoff'
        }
        onClick={() => setShowLess(!showLess)}>
        {taskValues.type === 'PICKUP' ? (
          <i className="fa fa-arrow-up"></i>
        ) : (
          <i className="fa fa-arrow-down"></i>
        )}
        <h4 className="task__header__title ml-2 mb-4">
          {taskValues.address?.streetAddress
            ? taskValues.address.streetAddress
            : taskValues.type === 'PICKUP'
              ? t('DELIVERY_FORM_PICKUP_INFORMATIONS')
              : t('DELIVERY_FORM_DROPOFF_INFORMATIONS')}
        </h4>

        <button
          data-testid="toggle-button"
          type="button"
          className="task__button">
          <i
            className={!showLess ? 'fa fa-chevron-up' : 'fa fa-chevron-down'}
            title={
              showLess
                ? t('DELIVERY_FORM_SHOW_MORE')
                : t('DELIVERY_FORM_SHOW_LESS')
            }></i>
        </button>

        {showRemoveButton && (
          <i
            className="fa fa-trash cursor-pointer"
            onClick={() => onRemove(index)}
            type="button"
          />
        )}
      </div>

      <div
        className={!showLess ? 'task__body' : 'task__body task__body--hidden'}>
        <AddressBookNew
          addresses={addresses}
          index={index}
          storeDeliveryInfos={storeDeliveryInfos}
          shallPrefillAddress={Boolean(
            taskValues.type === 'PICKUP' &&
              (mode === Mode.DELIVERY_CREATE) &&
              storeDeliveryInfos.prefillPickupAddress,
          )}
        />

        <TaskDateTime
          isDispatcher={isDispatcher}
          storeNodeId={storeNodeId}
          timeSlots={timeSlotLabels}
          index={index}
        />

        {taskValues.type === 'DROPOFF' ? (
          <div className="mt-4">
            {packages && packages.length ? (
              <Packages
                index={index}
                packages={packages}
              />
            ) : null}
            <TotalWeight index={index} />
          </div>
        ) : null}

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

        {isDispatcher && (
          <div className="mt-4 mb-4">
            <div className="tags__title block mb-2 font-weight-bold">Tags</div>
            <div data-testid="tags-select">
              <TagsSelect
                tags={tags}
                defaultValue={values.tasks[index].tags || []}
                onChange={values => {
                  const tags = values.map(tag => tag.value)
                  setFieldValue(`tasks[${index}].tags`, tags)
                }}
              />
            </div>
          </div>
        )}
      </div>
      {taskValues.type === 'DROPOFF' && (
        <div className={!showLess ? 'task__footer' : 'task__footer--hidden'}>
          {showRemoveButton && (
            <Button
              onClick={() => onRemove(index)}
              type="button"
              className="mb-4">
              {t('DELIVERY_FORM_REMOVE_DROPOFF')}
            </Button>
          )}
        </div>
      )}
    </div>
  )
}
