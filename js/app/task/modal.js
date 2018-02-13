import TaskRangePicker from '../widgets/TaskRangePicker'

window.CoopCycle = window.CoopCycle || {}
window.CoopCycle.TaskModal = (formName) => {

  new CoopCycle.AddressInput(document.querySelector(`#${formName}_address_streetAddress`), {
    elements: {
      latitude: document.querySelector(`#${formName}_address_latitude`),
      longitude: document.querySelector(`#${formName}_address_longitude`),
      postalCode: document.querySelector(`#${formName}_address_postalCode`),
      addressLocality: document.querySelector(`#${formName}_address_addressLocality`)
    }
  })

  new TaskRangePicker(document.querySelector(`#${formName}_rangepicker`), [
    document.querySelector(`#${formName}_doneAfter`),
    document.querySelector(`#${formName}_doneBefore`)
  ])

  const timelineEl = document.querySelector(`form[name="${formName}"] ul[data-render="timeline"]`)
  new CoopCycle.Timeline(timelineEl)

}
