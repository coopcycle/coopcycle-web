import _ from "lodash"
import { selectItemAssignedTo, selectTaskIdToTourIdMap, selectTaskListByUsername, selectTourById } from "../../../shared/src/logistics/redux/selectors"
import { setIsTourDragging, selectAllTasks } from "../../coopcycle-frontend-js/logistics/redux"
import { clearSelectedTasks,
  insertInUnassignedTasks,
  insertInUnassignedTours,
  putTaskListItems as putTaskListItemsAction,
  modifyTour as modifyTourAction,
  removeTasksFromTour as removeTasksFromTourAction,
  unassignTasks as unassignTasksAction,
  removePreviouslyAssignedTasks,
} from "./actions"
import { belongsToTour, selectGroups, selectOrderOfUnassignedTasks, selectSelectedTasks } from "./selectors"
import { isValidTasksMultiSelect, sortByPreviousChain, withOrderTasks } from "./utils"
import { toast } from 'react-toastify'
import i18next from "i18next"

const parseUsername = id => id.replace('assigned:', '')
const parseTourId   = id => id.replace('tour:', '')

/**
 * Sort tasks the way they appear in `order`. Tasks missing from it keep their relative
 * order and are put last - `findIndex` returning -1 would otherwise send them first.
 * Returns a new array: `selectedTasks` often comes straight out of a memoized selector,
 * sorting it in place would reorder derived state behind Redux's back.
 * @param {Array.Object} selectedTasks - Tasks to sort
 * @param {Array.string} order - Task IRIs, in the expected order
 */
function sortByOrder(selectedTasks, order) {
  const rank = task => {
    const index = order.findIndex(id => id === task['@id'])
    return index === -1 ? Number.MAX_SAFE_INTEGER : index
  }

  return [...selectedTasks].sort((a, b) => rank(a) - rank(b))
}

/**
 * @hello-pangea/dnd computes `index` as if the item being dragged had already been
 * taken out of the list, but not the other selected ones. When several items move at
 * once, every *other* selected item we remove from above the drop point shifts the list
 * up by one, so the drop index has to be corrected by as much. Without this, dragging a
 * multiple selection downwards inside a list lands too low.
 * @see https://github.com/hello-pangea/dnd/blob/main/docs/patterns/multi-drag.md
 * @param {number} index - The index dnd dropped at
 * @param {Array.string} previousItems - Items IRIs of the list, before the drop
 * @param {Array.Object} selectedItems - Items being moved
 * @param {?string} primaryItemId - IRI of the item actually being dragged
 */
function dropIndex(index, previousItems, selectedItems, primaryItemId) {
  const removedBeforeIndex = selectedItems.filter(item => {
    if (item['@id'] === primaryItemId) {
      return false
    }
    const itemIndex = previousItems.findIndex(it => it === item['@id'])

    return itemIndex > -1 && itemIndex < index
  }).length

  return index - removedBeforeIndex
}

function sortSelectedTasks(selectedTasks, result, getState, isTourDrag) {
  if (isTourDrag) return selectedTasks

  const { source } = result

  if (source.droppableId === 'unassigned') {
    return sortByOrder(selectedTasks, selectOrderOfUnassignedTasks(getState()))
  }
  if (source.droppableId.startsWith('tour') || result.draggableId.startsWith('tour')) {
    const tourId = result.draggableId.startsWith('tour')
      ? parseTourId(result.draggableId)
      : parseTourId(source.droppableId)
    const tour = selectTourById(getState(), tourId)

    return sortByOrder(selectedTasks, tour?.items ?? [])
  }
  if (source.droppableId.startsWith('assigned')) {
    const tasksList = selectTaskListByUsername(getState(), { username: parseUsername(source.droppableId) })

    return sortByOrder(selectedTasks, tasksList?.items ?? [])
  }
  return selectedTasks
}

export function handleDragStart(result) {
  return function(dispatch, getState) {

    const selectedTasksIds = selectSelectedTasks(getState()).map(t => t['@id'])

    // If the user is starting to drag something that is not selected then we need to clear the selection.
    // https://github.com/atlassian/@hello-pangea/dnd/blob/master/docs/patterns/multi-drag.md#dragging
    const isDraggableSelected = selectedTasksIds.includes(result.draggableId)

    if (!isDraggableSelected) {
      dispatch(clearSelectedTasks())
    }

    // prevent the user to drag a tour into a tour
    if (result.draggableId.startsWith('tour:')) {
      dispatch(setIsTourDragging(true))
    } else {
      dispatch(setIsTourDragging(false))
    }

  }
}

export function handleDragEnd(
  result,
  putTaskListItems=putTaskListItemsAction,
  modifyTour=modifyTourAction,
  unassignTasks=unassignTasksAction,
  removeTasksFromTour=removeTasksFromTourAction) {

  return function(dispatch, getState) {

    /**
     * @param {Object} tasksList - TaskList to be modified
     * @param {Array.Objects} selectedItems - Items to be assigned, list of tasks and tours to be assigned
     * @param {number} index - The index at which we drop
     * @param {?string} primaryItemId - IRI of the item actually being dragged, when it
     *   belongs to `tasksList`. @hello-pangea/dnd computes `index` as if that one item
     *   had already been taken out of the list, but not the other selected ones.
    */
    const handleDropInTaskList = async (tasksList, selectedItems, index, primaryItemId = null) => {
      const previousItems = tasksList.items
      let newTasksListItems = [...previousItems]

      selectedItems.forEach((t) => {
        let itemIndex = newTasksListItems.findIndex((item) => item === t['@id'])
        // if the item was already in the tasklist, remove from its original place
        if (itemIndex > -1) {
          newTasksListItems.splice(itemIndex, 1)
        }

      })

      newTasksListItems.splice(
        dropIndex(index, previousItems, selectedItems, primaryItemId),
        0,
        ...selectedItems.map(it => it['@id'])
      )

      // Tasks may have been moved between couriers
      // No need to unassign via API, because the PUT operation will take care of this
      // This action just removes the previously assigned tasks, if any
      // It does *NOT* perform any HTTP request, it is to reflect change visually
      // It returns what is needed to undo it, should the PUT below fail
      const removedFromOtherTaskLists = dispatch(removePreviouslyAssignedTasks(tasksList.username, selectedItems)) ?? []

      // This will actually perform the PUT operation
      dispatch(putTaskListItems(tasksList.username, newTasksListItems, removedFromOtherTaskLists))
    }

    // dropped nowhere
    if (!result.destination) {
      return;
    }

    const source = result.source;
    const destination = result.destination;

    // did not move anywhere - can bail early
    if (
      source.droppableId === destination.droppableId &&
      source.index === destination.index
    ) {
      return;
    }

    if (source.droppableId.startsWith('group:') && destination.droppableId.startsWith('group:') && source.droppableId !== destination.droppableId) {
      toast.warn(i18next.t("ADMIN_DASHBOARD_CAN_NOT_MOVE_TASKS_BETWEEN_GROUPS"))
      return
    }

    if (source.droppableId.startsWith('group:') && destination.droppableId.startsWith('assigned:')) {
      toast.warn(i18next.t("ADMIN_DASHBOARD_CAN_NOT_MOVE_FROM_GROUP_TO_ASSIGNED"))
      return
    }

    if (result.draggableId.startsWith('group:') && destination.droppableId.startsWith('unassigned')) {
      toast.warn(i18next.t("ADMIN_DASHBOARD_GROUP_TO_UNASSIGNED"))
      return
    }

    const allTasks = selectAllTasks(getState()),
      isTourDrag = result.draggableId.startsWith('tour')

    // FIXME : if a tour or a group is selected, selectSelectedTasks yields [ undefined ] so we test > 1 no > 0
    let selectedTasks = selectSelectedTasks(getState()).length > 1 ? selectSelectedTasks(getState()) : [_.find(allTasks, t => t['@id'] === result.draggableId)]

    // we are moving a whole group, override selectedTasks
    if (result.draggableId.startsWith('group')) {
      let groupId = result.draggableId.split(':')[1]
      selectedTasks = selectGroups(getState()).find(g => g['@id'] == groupId).tasks
    }

    if (selectedTasks.length === 0) return // can happen, for example dropping empty tour

    const taskIdToTourIdMap = selectTaskIdToTourIdMap(getState())

    if(!isTourDrag && !isValidTasksMultiSelect(selectedTasks, taskIdToTourIdMap)){
      toast.warn(i18next.t('ADMIN_DASHBOARD_INVALID_TASKS_SELECTION'), {autoclose: 15000})
      return
    }

    // when we drag n drop we want all tasks of the order/delivery to move alongside
    // except from tour or group, keep them as they are organized
    if (source.droppableId !== destination.droppableId && !result.draggableId.startsWith('tour') && !result.draggableId.startsWith('group')) {
      selectedTasks = withOrderTasks(selectedTasks, allTasks, taskIdToTourIdMap)
    }

    selectedTasks = sortSelectedTasks(selectedTasks, result, getState, isTourDrag)
    if (!isTourDrag) {
      selectedTasks = sortByPreviousChain(selectedTasks)
    }

    // --- REORDERING (same source and destination) ---
    if (source.droppableId === destination.droppableId) {
      if (source.droppableId === 'unassigned_tours') {
        const itemId = result.draggableId.startsWith('tour:')
          ? parseTourId(result.draggableId)
          : result.draggableId.replace('group:', '')
        dispatch(insertInUnassignedTours({ itemId, index: destination.index }))
        return
      }
      if (source.droppableId === 'unassigned') {
        dispatch(insertInUnassignedTasks({ tasksToInsert: selectedTasks, index: destination.index }))
        return
      }
      if (source.droppableId.startsWith('assigned:')) {
        const tasksList = selectTaskListByUsername(getState(), { username: parseUsername(destination.droppableId) })
        const items = isTourDrag
          ? [selectTourById(getState(), parseTourId(result.draggableId))]
          : selectedTasks
        const primaryItemId = isTourDrag ? parseTourId(result.draggableId) : result.draggableId
        handleDropInTaskList(tasksList, items, destination.index, primaryItemId)
        return
      }
      // source === dest === 'tour:X': falls through to TASK DRAG below
    }

    // --- TOUR DRAG ---
    if (isTourDrag) {
      if (destination.droppableId === 'unassigned_tours') {
        dispatch(unassignTasks(parseUsername(source.droppableId), [selectTourById(getState(), parseTourId(result.draggableId))]))
        return
      }
      // destination is assigned:
      const tasksList = selectTaskListByUsername(getState(), { username: parseUsername(destination.droppableId) })
      handleDropInTaskList(
        tasksList,
        [selectTourById(getState(), parseTourId(result.draggableId))],
        destination.index,
        parseTourId(result.draggableId)
      )
      return
    }

    // --- TASK DRAG ---
    if (destination.droppableId === 'unassigned') {
      if (!belongsToTour(selectedTasks[0])(getState())) {
        dispatch(unassignTasks(selectItemAssignedTo(getState(), selectedTasks[0]['@id']), selectedTasks))
      } else {
        const tourId = selectTaskIdToTourIdMap(getState()).get(selectedTasks[0]['@id'])
        dispatch(removeTasksFromTour(selectTourById(getState(), tourId), selectedTasks))
      }
      return
    }

    if (destination.droppableId.startsWith('tour:')) {
      const tour = selectTourById(getState(), parseTourId(destination.droppableId))
      const previousTourItems = tour.items
      const newTourItems = [...previousTourItems]

      if (source.droppableId === destination.droppableId) {
        _.remove(newTourItems, t => selectedTasks.find(st => st['@id'] === t))
      } else if (source.droppableId.startsWith('tour:')) {
        dispatch(removeTasksFromTour(selectTourById(getState(), parseTourId(source.droppableId)), selectedTasks))
      } else if (source.droppableId.startsWith('assigned:')) {
        dispatch(unassignTasks(parseUsername(source.droppableId), selectedTasks))
      }

      newTourItems.splice(
        dropIndex(destination.index, previousTourItems, selectedTasks, result.draggableId),
        0,
        ...selectedTasks.map(t => t['@id'])
      )
      dispatch(modifyTour(tour, newTourItems))
      return
    }

    // destination is assigned:
    if (source.droppableId.startsWith('tour:')) {
      dispatch(removeTasksFromTour(selectTourById(getState(), parseTourId(source.droppableId)), selectedTasks))
    }
    handleDropInTaskList(
      selectTaskListByUsername(getState(), { username: parseUsername(destination.droppableId) }),
      selectedTasks,
      destination.index,
      result.draggableId
    )
  }
}
