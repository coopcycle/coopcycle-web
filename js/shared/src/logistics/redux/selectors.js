import _ from 'lodash';
import { createSelector } from 'reselect';
import { mapToColor } from './taskUtils';
import { organizationAdapter, taskAdapter, taskListAdapter, tourAdapter, trailerAdapter, vehicleAdapter, warehouseAdapter } from './adapters'
import i18next from 'i18next';

const taskSelectors = taskAdapter.getSelectors((state) => state.logistics.entities.tasks)
export const taskListSelectors = taskListAdapter.getSelectors((state) => state.logistics.entities.taskLists)
const tourSelectors = tourAdapter.getSelectors((state) => state.logistics.entities.tours)
const organizationSelectors = organizationAdapter.getSelectors((state) => state.logistics.entities.organizations)
const vehiclesSelectors = vehicleAdapter.getSelectors((state) => state.logistics.entities.vehicles)
const trailersSelectors = trailerAdapter.getSelectors((state) => state.logistics.entities.trailers)
const warehousesSelectors = warehouseAdapter.getSelectors((state) => state.logistics.entities.warehouses)

export const selectVehicleById = vehiclesSelectors.selectById
export const selectAllVehicles = vehiclesSelectors.selectAll

export const selectTrailerById = trailersSelectors.selectById
export const selectAllTrailers = trailersSelectors.selectAll

export const selectWarehouseById = warehousesSelectors.selectById
export const selectAllWarehouses = warehousesSelectors.selectAll

export const selectAllOrganizations = organizationSelectors.selectAll
export const selectOrganizationsLoading = state => state.logistics.ui.organizationsLoading

export const selectSelectedDate = state => state.logistics.date

export const selectAllTasks = taskSelectors.selectAll


const selectTaskId = (state, taskId) => taskId

export const selectTaskById = createSelector(selectAllTasks, selectTaskId,
  (tasks, taskId) => tasks.find(t => t['@id'] === taskId)
)

const selectTasksId = (state, tasksId) => tasksId

export const selectTasksById = createSelector(
  selectAllTasks,
  selectTasksId,
  (allTasks, tasksId) => tasksId.map(taskId => allTasks.find(t => t['@id'] === taskId))
)

export const selectVehicleIdToTaskListIdMap = createSelector(
  taskListSelectors.selectAll,
  (allTaskList) => {
    let vehicleIdToTaskListId = new Map()
    allTaskList.forEach((taskList) => {
      if (taskList.vehicle) {
        vehicleIdToTaskListId.set(taskList.vehicle, taskList['username'])
      }
  })
  return vehicleIdToTaskListId
})

export const selectTrailerIdToTaskListIdMap = createSelector(
  taskListSelectors.selectAll,
  (allTaskList) => {
    let trailerIdToTaskListId = new Map()
    allTaskList.forEach((taskList) => {
      if (taskList.trailer) {
        trailerIdToTaskListId.set(taskList.trailer, taskList['username'])
      }
  })
  return trailerIdToTaskListId
})

export const selectTasksWithColor = createSelector(
  selectAllTasks,
  allTasks => mapToColor(allTasks)
)

export const selectTaskListByUsername = (state, props) => taskListSelectors.selectById(state, props.username)

const flattenTaskListItemsAsListOfTasks = (taskList, allTasks, allTours) => {
  return taskList.items.reduce((acc, it) => {
    if (it.startsWith('/api/tours')) {
      const tour = allTours.find(t => t['@id'] === it)
      if (!tour) {
        console.log(`Could not find tour at id ${it}`)
      } else {
        // filter out undefined values
        // may happen if we reschedule the task and it is improperly unlinked from tasklist in the backend
        acc = [...acc, ...tour.items.map(tId => allTasks.find(t => t['@id'] === tId)).filter( Boolean )]
      }
    } else {
      // filter out undefined values
      // may happen if we reschedule the task and it is improperly unlinked from tasklist in the backend
      const task = allTasks.find(t => t["@id"] === it)
      if (task === undefined) {
        console.error("Could not find task at id " + it)
      } else {
        acc.push(task)
      }
    }
    return acc
  }, [])
}

export const selectTaskListTasksByUsername = createSelector(
  selectTaskListByUsername,
  selectAllTasks,
  tourSelectors.selectAll,
  (taskList, allTasks, allTours) => flattenTaskListItemsAsListOfTasks(taskList, allTasks, allTours)
)

export const selectAssignedTasks = createSelector(
  taskListSelectors.selectAll,
  selectAllTasks,
  tourSelectors.selectAll,
  (taskLists, allTasks, allTours) => taskLists.reduce((acc, taskList) => {
    return acc.concat(flattenTaskListItemsAsListOfTasks(taskList, allTasks, allTours))
  }, [])
)

export const selectUnassignedTasks = createSelector(
  selectAllTasks,
  selectAssignedTasks,
  (allTasks, assignedTasks) => _.filter(allTasks, task => !assignedTasks.find(t => t['@id'] === task['@id']))
)

export const selectAllTours = createSelector(
  tourSelectors.selectAll,
  (allTours) => allTours
)

export const selectTourPolylines = createSelector(
  selectAllTours,
  (allTours) => {
    return allTours.reduce((acc, tour) => {
      acc[tour['@id']] = tour.polyline
      return acc
    }, {})
  }
)

const selectItemId = (state, itemId) => itemId


export const selectItemAssignedTo = createSelector(
  taskListSelectors.selectAll,
  selectItemId,
  (allTaskLists, itemId) => { // item can be a task or a tour (!)
    const tl = allTaskLists.find(tl => tl.items.includes(itemId))
    if (tl) {
      return tl.username
    }
  }
)

const selectTourId = (state, tourId) => tourId

export const selectTourById = createSelector(selectAllTours, selectTourId,
  (tours, tourId) => tours.find(t => t['@id'] === tourId)
)

export const selectUnassignedTours = createSelector(
  selectAllTours,
  taskListSelectors.selectAll,
  (allTours, allTaskLists) => {
    let unassignedTours = []
    _.map(allTours, tour => {
      const tl = allTaskLists.find(tl => tl.items.includes(tour['@id']))
      if (!tl) {
        unassignedTours.push(tour)
      }
    })
    return unassignedTours
  }
)

export const selectTaskIdToTourIdMap = createSelector(
  selectAllTours,
  (allTours) => {
    let taskIdToTourIdMap = new Map()
    allTours.forEach((tour) => {
      tour.items.forEach(taskId => {
        taskIdToTourIdMap.set(taskId, tour['@id'])
    })
  })
  return taskIdToTourIdMap
})

export const selectOrganizationsSelectOptions = createSelector(
  selectAllOrganizations,
  selectOrganizationsLoading,
  (allOrganizations, isOrganizationsLoading) => {
    if (!isOrganizationsLoading) {
      return allOrganizations.map(val => {return {...val, label: val.name, value: val.name}})
    } else {
      return [{value: '', label: `${i18next.t('ADMIN_DASHBOARD_LOADING_ORGS')}`, isDisabled: true}]
    }
  }
)

export const selectTourWeight = createSelector(
  selectTourById,
  selectAllTasks,
  (tour, allTasks) => tour.items.reduce(
    (acc, taskId) => {
      const task = allTasks.find(t => t['@id'] === taskId)
      // task can be undefined, see https://github.com/coopcycle/coopcycle-web/issues/4487
      if (task?.type === 'DROPOFF') {
        return acc + task.weight
      }
      return acc
    }, 0)
)

export const selectTaskListWeight = createSelector(
  selectTaskListTasksByUsername,
  (taskListTasks) => taskListTasks.reduce(
    (acc, task) => {
      if (task.type === 'DROPOFF') {
        return acc + task.weight
      }
      return acc
    }, 0)
)

export const getTaskVolumeUnits = (task) => task.packages ? task.packages.reduce((acc, pt) => acc + pt.quantity * pt.volume_per_package, 0) : 0

export const getTaskPackages = (task) => task.packages ? task.packages.reduce((acc, pt) => `${acc} ${pt.quantity} x ${pt.short_code}`, '') : ''

export const selectTourVolumeUnits = createSelector(
  selectTourById,
  selectAllTasks,
  (tour, allTasks) => {
    return tour.items.reduce(
    (acc, taskId) => {
      const task = allTasks.find(t => t['@id'] === taskId)
      // task can be undefined, see https://github.com/coopcycle/coopcycle-web/issues/4487
      if (task?.type === 'DROPOFF') {
        return acc + getTaskVolumeUnits(task)
      }
      return acc
    }, 0)
  }
)

export const selectTaskListVolumeUnits = createSelector(
  selectTaskListTasksByUsername,
  (taskListTasks) => taskListTasks.reduce(
    (acc, task) => {
      if (task.type === 'DROPOFF') {
        return acc + getTaskVolumeUnits(task)
      }
      return acc
    }, 0)
)
