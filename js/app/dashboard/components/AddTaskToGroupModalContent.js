import React, { useState } from 'react'
import { connect } from 'react-redux'
import { useTranslation } from 'react-i18next'
import { Form, Button, Select } from 'antd'

import { addTasksToGroup } from '../redux/actions'
import { selectGroups, selectSelectedTasks } from '../redux/selectors'

const AddTaskToGroupModalContent = ({ selectedTasks, groups, addTasksToGroup }) => {
  const [taskGroup, setTaskGroup] = useState(null)

  const { t } = useTranslation()

  const onGroupSelected = (groupId) => {
    const groupSelected = groups.find((g) => g['@id'] == groupId)
    setTaskGroup(groupSelected)
  }

  return (
    <div className="px-5 pt-5">
      <Form
        name="basic"
        labelCol={{ span: 8 }}
        wrapperCol={{ span: 16 }}
        onFinish={ () => addTasksToGroup(selectedTasks, taskGroup) }
        autoComplete="off"
      >
        <Form.Item
          label={ t('ADMIN_DASHBOARD_CHOOSE_GROUP_LABEL') }
          name="name"
          rules={[{ required: true }]}
        >
          <Select onChange={ (value) => onGroupSelected(value) }>
            {groups.map((group) => {
              return (
                <Select.Option key={group['@id']} value={group['@id']}>{group.name}</Select.Option>
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
    addTasksToGroup: (selectedTasks, taskGroup) => dispatch(addTasksToGroup(selectedTasks, taskGroup)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(AddTaskToGroupModalContent)
