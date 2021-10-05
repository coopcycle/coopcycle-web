import moment from "moment";

export function getLabel(measureKey) {
  let value = ''
  switch (measureKey) {
    case "Task.Percentage_of_TODO":
    case "Task.Total_TODO_tasks":
      value = 'TODO'
      break;
    case "Task.Percentage_of_DOING":
    case "Task.Total_DOING_tasks":
      value = 'DOING'
      break;
    case "Task.Percentage_of_FAILED":
    case "Task.Total_FAILED_tasks":
      value = 'FAILED'
      break;
    case "Task.Percentage_of_CANCELLED":
    case "Task.Total_CANCELLED_tasks":
      value = 'CANCELLED'
      break;
    case "Task.Percentage_of_DONE":
    case "Task.Total_DONE_tasks":
      value = 'DONE'
      break;
  }
  return value
}

export const getBackgroundColor = (measureKey) => {
  let value = ''
  switch (measureKey) {
    case "Task.Percentage_of_TODO":
    case "Task.Total_TODO_tasks":
      value = '#f6bd18'
      break;
    case "Task.Percentage_of_DOING":
    case "Task.Total_DOING_tasks":
      value = '#6f5efa'
      break;
    case "Task.Percentage_of_FAILED":
    case "Task.Total_FAILED_tasks":
      value = '#fe99c3'
      break;
    case "Task.Percentage_of_CANCELLED":
    case "Task.Total_CANCELLED_tasks":
      value = '#bbbbbb'
      break;
    case "Task.Percentage_of_DONE":
    case "Task.Total_DONE_tasks":
      value = '#5ad8a6'
      break;
  }
  return value
}

export const formatDayDimension = (day) => moment(day).format('ll')
