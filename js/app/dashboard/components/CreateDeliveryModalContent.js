import React, { useState } from 'react'
import { connect } from 'react-redux'
import { useTranslation } from 'react-i18next'
import { Form, Button, Select } from 'antd'

import { createDelivery } from '../redux/actions'
import { selectSelectedTasks } from '../redux/selectors'

const ModalContent = ({ selectedTasks, stores, createDelivery }) => {
  const [store, setStore] = useState(null)

  const { t } = useTranslation()

  const onSelect = (storeId) => {
    const selectedStore = stores.find((s) => s['@id'] == storeId)
    setStore(selectedStore)
  }

  return (
    <div className="px-5 pt-5">
      <Form
        name="basic"
        labelCol={{ span: 8 }}
        wrapperCol={{ span: 16 }}
        onFinish={ () => createDelivery(selectedTasks, store) }
        autoComplete="off"
      >
        <Form.Item
          label={ t('ADMIN_DASHBOARD_CHOOSE_STORE_LABEL') }
          name="name"
          rules={[{ required: true }]}
        >
          <Select onChange={ (value) => onSelect(value) }>
            {stores.map((store) => {
              return (
                <Select.Option key={store['@id']} value={store['@id']}>{store.name}</Select.Option>
              )
            })}
          </Select>
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
    stores: state.config.stores,
    selectedTasks: selectSelectedTasks(state),
  }
}

function mapDispatchToProps(dispatch) {

  return {
    createDelivery: (tasks, store) => dispatch(createDelivery(tasks, store)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(ModalContent)
