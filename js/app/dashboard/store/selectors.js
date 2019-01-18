import { createSelector } from 'reselect'
import { differenceWith, filter, forEach, includes, intersectionBy } from 'lodash'

const getFilters = state => ({
  showFinishedTasks: state.taskFinishedFilter,
  showCancelledTasks: state.taskCancelledFilter,
  showUntaggedTasks: state.tagsFilter.showUntaggedTasks,
  selectedTags: state.tagsFilter.selectedTagsList,
})

export const selectTasks = state => {
  const tasks = state.unassignedTasks.slice(0)
  forEach(state.taskLists, taskList => taskList.items.forEach(task => tasks.push(task)))

  return tasks
}

export const selectFilteredTasks = createSelector(
  selectTasks,
  getFilters,
  (tasks, filters) => {

    let tasksFiltered = tasks.slice(0)

    const {
      showFinishedTasks,
      showCancelledTasks,
      showUntaggedTasks,
      selectedTags,
    } = filters

    if (!showFinishedTasks) {
      tasksFiltered =
        filter(tasksFiltered, task => !includes(['DONE', 'FAILED'], task.status))
    }

    if (!showCancelledTasks) {
      tasksFiltered =
        filter(tasksFiltered, task => 'CANCELLED' !== task.status)
    }

    if (!showUntaggedTasks) {
      tasksFiltered =
        filter(tasksFiltered, task => task.tags.length > 0)
    }

    if (selectedTags.length > 0) {

      const tasksNotTagged =
        filter(tasksFiltered, task => task.tags.length > 0 && intersectionBy(task.tags, selectedTags, 'name').length === 0)
      // const tasksTagged =
      //   filter(tasksFiltered, task => task.tags.length > 0 && intersectionBy(task.tags, selectedTags, 'name').length > 0)

      tasksFiltered =
        differenceWith(tasksFiltered, tasksNotTagged, (a, b) => a['@id'] === b['@id'])
    }

    return tasksFiltered
  }
)
