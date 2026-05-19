import React, { useState } from 'react'
import { connect } from 'react-redux'
import { useTranslation } from 'react-i18next'
import { Form, Button, Select } from 'antd'

import { sendToWarehouse } from '../redux/actions'
import { selectSelectedTasks } from '../redux/selectors'
import { selectAllWarehouses } from '../../../shared/src/logistics/redux/selectors'

const SendToWarehouseModalContent = ({ selectedTasks, warehouses, onSubmit }) => {
  const [warehouse, setWarehouse] = useState(null)
  const { t } = useTranslation()

  return (
    <div className="px-5 pt-5">
      <Form
        name="send-to-warehouse"
        labelCol={{ span: 8 }}
        wrapperCol={{ span: 16 }}
        onFinish={ () => warehouse && onSubmit(selectedTasks, warehouse) }
      >
        <Form.Item
          label={ t('ADMIN_DASHBOARD_CHOOSE_WAREHOUSE_LABEL') }
          name="warehouse"
          rules={[{ required: true }]}
        >
          <Select onChange={ (value) => setWarehouse(warehouses.find(w => w['@id'] === value)) }>
            {warehouses.map((w) => (
              <Select.Option key={w['@id']} value={w['@id']}>{w.name}</Select.Option>
            ))}
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
    warehouses: selectAllWarehouses(state),
    selectedTasks: selectSelectedTasks(state),
  }
}

function mapDispatchToProps(dispatch) {
  return {
    onSubmit: (tasks, warehouse) => dispatch(sendToWarehouse(tasks, warehouse)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(SendToWarehouseModalContent)
