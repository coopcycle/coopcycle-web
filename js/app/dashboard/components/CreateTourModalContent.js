import React from 'react'
import { connect } from 'react-redux'
import { useTranslation } from 'react-i18next'
import { Form, Button, Input } from 'antd'

import { createTour } from '../redux/actions'
import { selectSelectedTasks } from '../redux/selectors'
import { selectSelectedDate } from '../../coopcycle-frontend-js/logistics/redux'

const ModalContent = ({ selectedTasks, createTour, date, isCreateTourButtonLoading }) => {
  const { t } = useTranslation()

  return (
    <div className="px-5 pt-5">
      <Form
        name="basic"
        labelCol={{ span: 8 }}
        wrapperCol={{ span: 16 }}
        onFinish={ (values) => {createTour(selectedTasks, values.name, date)} }
        autoComplete="off"
      >
        <Form.Item
          label={ t('ADMIN_DASHBOARD_TOUR_NAME') }
          name="name"
          rules={[{ required: true }]}
        >
          <Input placeholder="" />
        </Form.Item>
        <Form.Item wrapperCol={{ offset: 8, span: 16 }}>
          <Button type="primary" htmlType="submit" loading={isCreateTourButtonLoading}>
            { t('ADMIN_DASHBOARD_TASK_FORM_SAVE') }
          </Button>
        </Form.Item>
      </Form>
    </div>
  )
}

function mapStateToProps(state) {

  return {
    selectedTasks: selectSelectedTasks(state),
    date: selectSelectedDate(state),
    isCreateTourButtonLoading: state.isCreateTourButtonLoading
  }
}

function mapDispatchToProps(dispatch) {

  return {
    createTour: (tasks, name, date) => dispatch(createTour(tasks, name, date)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(ModalContent)
