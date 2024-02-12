import React from 'react'
import { connect } from 'react-redux'
import { useTranslation } from 'react-i18next'
import { Form, Input, Button } from 'antd'

import { createGroup } from '../redux/actions'

const ModalContent = ({ createGroup, isCreateGroupButtonLoading }) => {

  const { t } = useTranslation()

  const initialValues = {
    name: ''
  }

  return (
    <div className="px-5 pt-5">
      <Form
        name="basic"
        labelCol={{ span: 8 }}
        wrapperCol={{ span: 16 }}
        initialValues={ initialValues }
        onFinish={ (values) => createGroup(values.name) }
        autoComplete="off"
      >
        <Form.Item
          label={ t('ADMIN_DASHBOARD_GROUP_NAME_LABEL') }
          name="name"
          rules={[{ required: true }]}
        >
          <Input />
        </Form.Item>
        <Form.Item wrapperCol={{ offset: 8, span: 16 }}>
          <Button type="primary" htmlType="submit" loading={isCreateGroupButtonLoading}>
            { t('ADMIN_DASHBOARD_TASK_FORM_SAVE') }
          </Button>
        </Form.Item>
      </Form>
    </div>
  )
}

function mapStateToProps(state) {

  return {
    isCreateGroupButtonLoading: state.isCreateGroupButtonLoading
  }
}

function mapDispatchToProps(dispatch) {

  return {
    createGroup: (name) => dispatch(createGroup(name)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(ModalContent)
