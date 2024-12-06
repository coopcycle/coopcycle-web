import React from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { Modal } from 'antd'
import { resolveTaskConfirmation } from '../redux/actions'
import { useTranslation } from 'react-i18next'

function TaskConfirmationDialog() {
  const { t } = useTranslation()
  const dispatch = useDispatch()
  const confirmation = useSelector(state => state.taskConfirmation)
  if (!confirmation) return null
  const { taskConfirmation } = confirmation
  if (!taskConfirmation) return null

  const handleOk = () => {
    taskConfirmation.onResolve(true)
    dispatch(resolveTaskConfirmation(true))
  }

  const handleCancel = () => {
    taskConfirmation.onResolve(false)
    dispatch(resolveTaskConfirmation(false))
  }

  return (
    <Modal
      title={t('MODAL_PREREQUISITE_TASK_REQUIRED')}
      open={true}
      onOk={handleOk}
      onCancel={handleCancel}>
      <p>{t('MODAL_PREREQUISITE_TASK_REQUIRED_DESC')}</p>
      <p>{t(taskConfirmation.message, { task: taskConfirmation.taskId })}</p>
    </Modal>
  )
}

export default TaskConfirmationDialog
