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

export const TYPE_PICKUP = 'PICKUP'
export const TYPE_DROPOFF = 'DROPOFF'

export const TIMING_TOO_EARLY = 'TOO_EARLY'
export const TIMING_TOO_LATE = 'TOO_LATE'
export const TIMING_ON_TIME = 'ON_TIME'

export const getBackgroundColor = (type, timing) => {
  let value = ''
  switch (type) {
    case TYPE_PICKUP:
      switch (timing) {
        case TIMING_TOO_EARLY:
          value = '#86BCE3'
          break;
        case TIMING_TOO_LATE:
          value = '#FAB06F'
          break;
        case TIMING_ON_TIME:
          value = '#81c784'
          break;
      }
      break;
    case TYPE_DROPOFF:
      switch (timing) {
        case TIMING_TOO_EARLY:
          value = '#2B567F'
          break;
        case TIMING_TOO_LATE:
          value = '#A65331'
          break;
        case TIMING_ON_TIME:
          value = '#2e7d32'
          break;
      }
      break;
  }
  return value
}


export const formatDayDimension = (day) => moment(day).format('ll')
