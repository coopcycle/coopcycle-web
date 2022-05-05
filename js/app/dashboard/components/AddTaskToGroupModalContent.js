import React, { useState } from 'react'
import { connect } from 'react-redux'
import { useTranslation } from 'react-i18next'
import { Form, Button, Select } from 'antd'

import { addTaskToGroup } from '../redux/actions'
import { selectGroups, selectSelectedTasks } from '../redux/selectors'

const AddTaskToGroupModalContent = ({ selectedTasks, groups, addTaskToGroup }) => {
  const [taskGroupId, setTaskGroupId] = useState(null)
  const taskId = selectedTasks[0] ? selectedTasks[0].id : null

  const { t } = useTranslation()

  return (
    <div className="px-5 pt-5">
      <Form
        name="basic"
        labelCol={{ span: 8 }}
        wrapperCol={{ span: 16 }}
        onFinish={ () => addTaskToGroup(taskId, taskGroupId) }
        autoComplete="off"
      >
        <Form.Item
          label={ t('ADMIN_DASHBOARD_CHOOSE_GROUP_LABEL') }
          name="name"
          rules={[{ required: true }]}
        >
          <Select onChange={ (value) => setTaskGroupId(value) }>
            {groups.map((group) => {
              return (
                <Select.Option key={group.id} value={group.id}>{group.name}</Select.Option>
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
    groups: selectGroups(state),
    selectedTasks: selectSelectedTasks(state),
  }
}

function mapDispatchToProps(dispatch) {

  return {
    addTaskToGroup: (taskId, taskGroupId) => dispatch(addTaskToGroup(taskId, taskGroupId)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(AddTaskToGroupModalContent)
