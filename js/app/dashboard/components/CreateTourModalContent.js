import React, { useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'
import { Form, Button, Input, Checkbox } from 'antd'

import { createTour } from '../redux/actions'
import { selectSelectedTasks } from '../redux/selectors'
import { selectSelectedDate } from '../../coopcycle-frontend-js/logistics/redux'
import { withOrderTasks } from '../redux/utils'
import { selectAllTasks, selectTaskIdToTourIdMap } from '../../../shared/src/logistics/redux/selectors'

export default () => {
  const { t } = useTranslation()

  const selectedTasks = useSelector(selectSelectedTasks)
  const date = useSelector(selectSelectedDate)
  const isCreateTourButtonLoading = useSelector( state => state.isCreateTourButtonLoading)
  const allTasks = useSelector(selectAllTasks)
  const taskIdToTourIdMap = useSelector(selectTaskIdToTourIdMap)


  const [includeOrderTasks, setIncludeOrderTasks ] = useState(Boolean(window.localStorage.getItem(`cpccl__dshbd__create_tour_include_order_tasks`)))

  const dispatch = useDispatch()

  const onSubmit = (values) => {
    if (Array.isArray(selectedTasks) && selectedTasks.length > 0 && includeOrderTasks) {
      dispatch(createTour(withOrderTasks(selectedTasks, allTasks, taskIdToTourIdMap), values.name, date))
    } else {
       dispatch(createTour(selectedTasks, values.name, date))
    }
    window.localStorage.setItem(`cpccl__dshbd__create_tour_include_order_tasks`, JSON.stringify(includeOrderTasks))
  }

  return (
    <div className="px-5 pt-5">
      <Form
        name="basic"
        labelCol={{ span: 12 }}
        wrapperCol={{ span: 16 }}
        onFinish={ onSubmit }
        autoComplete="off"
      >
        <Form.Item
          label={ t('ADMIN_DASHBOARD_TOUR_NAME') }
          name="name"
          rules={[{ required: true }]}
        >
          <Input placeholder="" />
        </Form.Item>
        { selectedTasks.length > 0 ?
          <Form.Item
            label={ t('ADMIN_DASHBOARD_INCLUDE_TASKS_FROM_ORDER') }
            name="include_order_tasks"
            checked={includeOrderTasks}
            onChange={(e) => setIncludeOrderTasks(e.target.checked)}
            ><Checkbox defaultChecked={includeOrderTasks}></Checkbox>
          </Form.Item>
          : null
        }
        <Form.Item wrapperCol={{ offset: 8, span: 16 }}>
          <Button type="primary" htmlType="submit" loading={isCreateTourButtonLoading}>
            { t('ADMIN_DASHBOARD_TASK_FORM_SAVE') }
          </Button>
        </Form.Item>
      </Form>
    </div>
  )
}
