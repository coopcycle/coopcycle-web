const { taskModalURL } = window.AppData.Delivery

$('[data-task]').on('click', e => {
  e.preventDefault()
  const taskID = $(e.currentTarget).data('task')
  $('#task-edit-modal')
    .load(taskModalURL.replace('__TASK_ID__', taskID), () => $('#task-edit-modal').modal({ show: true }))
})
