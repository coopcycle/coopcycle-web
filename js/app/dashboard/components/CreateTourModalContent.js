import React from 'react'
import { connect } from 'react-redux'
import { useTranslation } from 'react-i18next'
import { Form, Button, Input } from 'antd'

import { createTour } from '../redux/actions'
import { selectSelectedTasks } from '../redux/selectors'

const ModalContent = ({ selectedTasks, createTour }) => {

  const { t } = useTranslation()

  return (
    <div className="px-5 pt-5">
      <Form
        name="basic"
        labelCol={{ span: 8 }}
        wrapperCol={{ span: 16 }}
        onFinish={ (values) => createTour(selectedTasks, values.name) }
        autoComplete="off"
      >
        <Form.Item
          label={ t('ADMIN_DASHBOARD_TOUR_NAME') }
          name="name"
          rules={[{ required: true }]}
        >
          <Input placeholder="Basic usage" />
        </Form.Item>
        <Form.Item wrapperCol={{ offset: 8, span: 16 }}>
          <Button type="primary" htmlType="submit">
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
  }
}

function mapDispatchToProps(dispatch) {

  return {
    createTour: (tasks, name) => dispatch(createTour(tasks, name)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(ModalContent)
