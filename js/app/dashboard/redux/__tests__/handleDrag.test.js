import { storeFixture } from './storeFixture'
import { handleDragStart, handleDragEnd } from '../handleDrag';
import { clearSelectedTasks, insertInUnassignedTasks, insertInUnassignedTours, toggleTask } from '../actions';
import { setIsTourDragging } from '../../../coopcycle-frontend-js/logistics/redux'
import { createStoreFromPreloadedState } from '../store';

describe('handleDragEnd', () => {
    let store = createStoreFromPreloadedState(storeFixture)

    it ('should assign a tour at the beginning of a tasklist', () => {
      const dispatch = jest.fn(),
        mockModifyTaskList = jest.fn(),
        mockModifyTour = jest.fn()

      let result = {
        draggableId: 'tour:/api/tours/114',
        source: {droppableId: 'unassigned_tours'}, destination: {droppableId: 'assigned:admin', index: 0},
      }

      handleDragEnd(result, mockModifyTaskList, mockModifyTour)(dispatch, store.getState)

      expect(mockModifyTaskList).toHaveBeenCalledTimes(1)

      expect(mockModifyTaskList).toHaveBeenLastCalledWith(
        "admin",
        [
          '/api/tours/114',
          '/api/tours/111',
        ]
      )

      expect(mockModifyTour).toHaveBeenCalledTimes(0)

    })

    it ('should assign a tour inside a tasklist at given index', () => {
      const dispatch = jest.fn(),
        mockModifyTaskList = jest.fn(),
        mockModifyTour = jest.fn()

      let result = {
        draggableId: 'tour:/api/tours/114',
        source: {droppableId: 'unassigned_tours'},
        destination: {droppableId: 'assigned:admin', index: 1}
      }

      handleDragEnd(result, mockModifyTaskList, mockModifyTour)(dispatch, store.getState)

      expect(mockModifyTaskList).toHaveBeenLastCalledWith(
        "admin",
        [
          '/api/tours/111',
          '/api/tours/114',
        ]
      )
      expect(mockModifyTour).toHaveBeenCalledTimes(0)

    })

    it ('should assign a tour at the beginning of a tasklist then reorder', () => {
      const dispatch = jest.fn(),
        mockModifyTaskList = jest.fn(),
        mockModifyTour = jest.fn()

      let result

      result = {
        draggableId: 'tour:/api/tours/114',
        source: {droppableId: 'unassigned_tours'}, destination: {droppableId: 'assigned:admin', index: 0},
      }

      handleDragEnd(result, mockModifyTaskList, mockModifyTour)(dispatch, store.getState)

      result = {
        draggableId: 'tour:/api/tours/114',
        source: {droppableId: 'assigned:admin'}, destination: {droppableId: 'assigned:admin', index: 1},
      }

      handleDragEnd(result, mockModifyTaskList, mockModifyTour)(dispatch, store.getState)

      expect(mockModifyTaskList).toHaveBeenCalledTimes(2)

      expect(mockModifyTaskList).toHaveBeenLastCalledWith(
        "admin",
        [
          '/api/tours/111',
          '/api/tours/114',
        ]
      )

      expect(mockModifyTour).toHaveBeenCalledTimes(0)

    })

    it ('should unassign a tour', () => {
      const dispatch = jest.fn(),
        mockModifyTaskList = jest.fn(),
        mockModifyTour = jest.fn(),
        mockUnassignTasks = jest.fn()

      let result = {
        draggableId: 'tour:/api/tours/111',
        source: {droppableId: 'assigned:admin'},
        destination: {droppableId: 'unassigned_tours', index: 1}
      }

      handleDragEnd(result, mockModifyTaskList, mockModifyTour, mockUnassignTasks)(dispatch, store.getState)

      expect(mockUnassignTasks).toHaveBeenLastCalledWith(
        "admin",
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tours/111'}),
        ])
      )

      expect(mockModifyTaskList).toHaveBeenCalledTimes(0)
      expect(mockModifyTour).toHaveBeenCalledTimes(0)

    })

    it ('should assign a group at the end of a tasklist',  () => {
      const dispatch = jest.fn(),
        mockModifyTaskList = jest.fn(),
        mockModifyTour = jest.fn()

      let result = {
        draggableId: 'group:/api/task_groups/23',
        source: {droppableId: 'unassigned:'}, destination: {droppableId: 'assigned:admin', index: 1}
      }

      handleDragEnd(result, mockModifyTaskList, mockModifyTour)(dispatch, store.getState)

      expect(mockModifyTaskList).toHaveBeenLastCalledWith(
        "admin",
        [
          '/api/tours/111',
          '/api/tasks/736'
        ]
      )
      expect(mockModifyTour).toHaveBeenCalledTimes(0)


    })

    it ('should assign a task in a tour which is already assigned', async () => {
      const dispatch = jest.fn(),
        mockModifyTaskList = jest.fn(),
        mockModifyTour = jest.fn()

      let result = {
        draggableId: '/api/tasks/734',
        source: {droppableId: 'unassigned'}, destination: {droppableId: 'tour:/api/tours/111', index: 1}
      }

      handleDragEnd(result, mockModifyTaskList, mockModifyTour)(dispatch, store.getState)

      expect(mockModifyTaskList).toHaveBeenCalledTimes(0)

      expect(mockModifyTour).toHaveBeenCalledTimes(1)
      expect(mockModifyTour).toHaveBeenLastCalledWith(
        expect.objectContaining({'@id': '/api/tours/111'}),
        [
          '/api/tasks/729',
          '/api/tasks/734',
          '/api/tasks/735',
          '/api/tasks/730',
          '/api/tasks/731',
          '/api/tasks/727',
        ]
      )

    })

    it ('should unassign a task from assigned tour to unassigned tour', () => {
      const dispatch = jest.fn(),
        mockModifyTaskList = jest.fn(),
        mockModifyTour = jest.fn(),
        mockUnassignTasks = jest.fn(),
        mockRemoveTasksFromTour = jest.fn()

      let result = {
        draggableId: '/api/tasks/731',
        source: {droppableId: 'tour:/api/tours/111'}, destination: {droppableId: 'tour:/api/tours/114', index: 1}
      }

      handleDragEnd(result, mockModifyTaskList, mockModifyTour, mockUnassignTasks, mockRemoveTasksFromTour)(dispatch, store.getState)

      expect(mockModifyTaskList).toHaveBeenCalledTimes(0)

      expect(mockModifyTour).toHaveBeenCalledTimes(1)
      expect(mockModifyTour).toHaveBeenCalledWith(
        expect.objectContaining({'@id': '/api/tours/114'}),
        [
          '/api/tasks/733',
          '/api/tasks/730', // linked task moving together
          '/api/tasks/731',
          '/api/tasks/732',
        ]
      )

      expect(mockRemoveTasksFromTour).toHaveBeenCalledTimes(1)
      expect(mockRemoveTasksFromTour).toHaveBeenCalledWith(
        expect.objectContaining({'@id': '/api/tours/111'}),
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tasks/731'}),
        ]),
      )

    })

    it ('should move a task from unassigned tour to assigned tour', () => {
      const dispatch = jest.fn(),
        mockModifyTaskList = jest.fn(),
        mockModifyTour = jest.fn(),
        mockUnassignTasks = jest.fn(),
        mockRemoveTasksFromTour = jest.fn()

      // tour 111 is assigned, tour 114 is unassigned
      let result = {
        draggableId: '/api/tasks/733',
        source: {droppableId: 'tour:/api/tours/114'}, destination: {droppableId: 'tour:/api/tours/111', index: 1}
      }

      handleDragEnd(result, mockModifyTaskList, mockModifyTour, mockUnassignTasks, mockRemoveTasksFromTour)(dispatch, store.getState)

      expect(mockModifyTaskList).toHaveBeenCalledTimes(0)

      expect(mockModifyTour).toHaveBeenCalledTimes(1)
      expect(mockModifyTour).toHaveBeenLastCalledWith(
        expect.objectContaining({'@id': '/api/tours/111'}),
        [
          '/api/tasks/729',
          '/api/tasks/733',
          '/api/tasks/730',
          '/api/tasks/731',
          '/api/tasks/727',
        ]
      )

      expect(mockRemoveTasksFromTour).toHaveBeenCalledWith(
        expect.objectContaining({'@id': '/api/tours/114'}),
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tasks/733'}),
        ])
      )
    })

    it ('should move unassigned tasks in the same order they are in the unassigned tasks panel when moving to tour', async () => {
      const dispatch = jest.fn(),
        mockModifyTaskList = jest.fn(),
        mockModifyTour = jest.fn()

      let result = {
        draggableId: '/api/tasks/737',
        source: {droppableId: 'unassigned'}, destination: {droppableId: 'tour:/api/tours/114', index: 1}
      }

      handleDragEnd(result, mockModifyTaskList, mockModifyTour)(dispatch, store.getState)

      expect(mockModifyTour).toHaveBeenCalledTimes(1)
      expect(mockModifyTour).toHaveBeenLastCalledWith(
        expect.objectContaining({'@id': '/api/tours/114'}),
        [
          '/api/tasks/733',
          '/api/tasks/737',
          '/api/tasks/738',
          '/api/tasks/732'
        ]
      )
      expect(mockModifyTaskList).toHaveBeenCalledTimes(0)
    })

    it ('should move unassigned tasks in the order they are in the unassigned tasks panel when they were reordered', async () => {

      // revert order of tasks in unassigned tasks compared to previous test
      store.dispatch(insertInUnassignedTasks({tasksToInsert: [{'@id': '/api/tasks/738'}], index: 1}))


      const dispatch = jest.fn(),
        mockModifyTaskList = jest.fn(),
        mockModifyTour = jest.fn()

      let result = {
        draggableId: '/api/tasks/737',
        source: {droppableId: 'unassigned'}, destination: {droppableId: 'tour:/api/tours/114', index: 1}
      }

      handleDragEnd(result, mockModifyTaskList, mockModifyTour)(dispatch, store.getState)

      expect(mockModifyTour).toHaveBeenCalledTimes(1)
      expect(mockModifyTour).toHaveBeenLastCalledWith(
        expect.objectContaining({'@id': '/api/tours/114'}),
        [
          '/api/tasks/733',
          '/api/tasks/738', // reverted compare to previous test
          '/api/tasks/737',
          '/api/tasks/732'
        ]
      )
      expect(mockModifyTaskList).toHaveBeenCalledTimes(0)
    })

    it ('should move individual tasks from tour in the order they are in the tour when assigning', async () => {

      store.dispatch(toggleTask({'@id': '/api/tasks/732'}, true))

      const dispatch = jest.fn(),
        mockModifyTaskList = jest.fn(),
        mockModifyTour = jest.fn()

      let result = {
        draggableId: '/api/tasks/732',
        source: {droppableId: 'tour:/api/tours/114'}, destination: {droppableId: 'assigned:admin', index: 0}
      }

      handleDragEnd(result, mockModifyTaskList, mockModifyTour)(dispatch, store.getState)

      expect(mockModifyTaskList).toHaveBeenCalledWith(
        "admin",
        [
         '/api/tasks/733',
         '/api/tasks/732',
         '/api/tours/111'
        ]
      )
    })
  })

describe('handleDragStart', () => {
  // fixture has selectedTasks: ['/api/tasks/733']

  it('should clear selection when dragging an unselected item', () => {
    const store = createStoreFromPreloadedState(storeFixture)
    const dispatch = jest.fn()
    handleDragStart({ draggableId: '/api/tasks/729' })(dispatch, store.getState)
    expect(dispatch).toHaveBeenCalledWith(clearSelectedTasks())
  })

  it('should not clear selection when dragging the selected item', () => {
    const store = createStoreFromPreloadedState(storeFixture)
    const dispatch = jest.fn()
    handleDragStart({ draggableId: '/api/tasks/733' })(dispatch, store.getState)
    expect(dispatch).not.toHaveBeenCalledWith(clearSelectedTasks())
  })

  it('should set isTourDragging when dragging a tour', () => {
    const store = createStoreFromPreloadedState(storeFixture)
    const dispatch = jest.fn()
    handleDragStart({ draggableId: 'tour:/api/tours/111' })(dispatch, store.getState)
    expect(dispatch).toHaveBeenCalledWith(setIsTourDragging(true))
  })

  it('should clear isTourDragging when dragging a task', () => {
    const store = createStoreFromPreloadedState(storeFixture)
    const dispatch = jest.fn()
    handleDragStart({ draggableId: '/api/tasks/729' })(dispatch, store.getState)
    expect(dispatch).toHaveBeenCalledWith(setIsTourDragging(false))
  })
})

describe('handleDragEnd - edge cases', () => {
  it('should do nothing when dropped outside any droppable', () => {
    const store = createStoreFromPreloadedState(storeFixture)
    const dispatch = jest.fn()
    const mockModifyTaskList = jest.fn()
    const mockModifyTour = jest.fn()

    handleDragEnd(
      { draggableId: '/api/tasks/729', source: { droppableId: 'assigned:admin', index: 0 }, destination: null },
      mockModifyTaskList, mockModifyTour
    )(dispatch, store.getState)

    expect(dispatch).not.toHaveBeenCalled()
    expect(mockModifyTaskList).not.toHaveBeenCalled()
    expect(mockModifyTour).not.toHaveBeenCalled()
  })

  it('should do nothing when dropped in the same position', () => {
    const store = createStoreFromPreloadedState(storeFixture)
    const dispatch = jest.fn()
    const mockModifyTaskList = jest.fn()
    const mockModifyTour = jest.fn()

    handleDragEnd(
      {
        draggableId: '/api/tasks/729',
        source: { droppableId: 'assigned:admin', index: 0 },
        destination: { droppableId: 'assigned:admin', index: 0 },
      },
      mockModifyTaskList, mockModifyTour
    )(dispatch, store.getState)

    expect(dispatch).not.toHaveBeenCalled()
    expect(mockModifyTaskList).not.toHaveBeenCalled()
    expect(mockModifyTour).not.toHaveBeenCalled()
  })

  it('should reorder a tour within the unassigned tours panel', () => {
    const store = createStoreFromPreloadedState(storeFixture)
    const dispatch = jest.fn()

    handleDragEnd({
      draggableId: 'tour:/api/tours/114',
      source: { droppableId: 'unassigned_tours', index: 0 },
      destination: { droppableId: 'unassigned_tours', index: 1 },
    })(dispatch, store.getState)

    expect(dispatch).toHaveBeenCalledWith(
      expect.objectContaining({
        type: insertInUnassignedTours.type,
        payload: { itemId: '/api/tours/114', index: 1 },
      })
    )
  })

  it('should reorder tasks within the unassigned tasks panel', () => {
    const store = createStoreFromPreloadedState(storeFixture)
    const dispatch = jest.fn()

    // task 733 is at index 2 in unassignedTasksIdsOrder; move it to the front
    handleDragEnd({
      draggableId: '/api/tasks/733',
      source: { droppableId: 'unassigned', index: 2 },
      destination: { droppableId: 'unassigned', index: 0 },
    })(dispatch, store.getState)

    expect(dispatch).toHaveBeenCalledWith(
      expect.objectContaining({
        type: insertInUnassignedTasks.type,
        payload: expect.objectContaining({
          index: 0,
          tasksToInsert: expect.arrayContaining([
            expect.objectContaining({ '@id': '/api/tasks/733' }),
          ]),
        }),
      })
    )
  })

  it('should reorder a task within a courier tasklist', () => {
    const store = createStoreFromPreloadedState(storeFixture)
    const dispatch = jest.fn()
    const mockModifyTaskList = jest.fn()

    // Admin tasklist items: ['/api/tours/111']
    // Dragging task 729 at the root level of admin's list to index 1 appends it after the tour
    handleDragEnd(
      {
        draggableId: '/api/tasks/729',
        source: { droppableId: 'assigned:admin', index: 0 },
        destination: { droppableId: 'assigned:admin', index: 1 },
      },
      mockModifyTaskList
    )(dispatch, store.getState)

    expect(mockModifyTaskList).toHaveBeenCalledWith(
      'admin',
      ['/api/tours/111', '/api/tasks/729']
    )
  })

  it('should remove a task from a tour when dragged to unassigned', () => {
    const store = createStoreFromPreloadedState(storeFixture)
    const dispatch = jest.fn()
    const mockModifyTaskList = jest.fn()
    const mockModifyTour = jest.fn()
    const mockUnassignTasks = jest.fn()
    const mockRemoveTasksFromTour = jest.fn()

    // task 729 is in tour 111; it has no linked tasks (no previous/next)
    handleDragEnd(
      {
        draggableId: '/api/tasks/729',
        source: { droppableId: 'tour:/api/tours/111', index: 0 },
        destination: { droppableId: 'unassigned' },
      },
      mockModifyTaskList, mockModifyTour, mockUnassignTasks, mockRemoveTasksFromTour
    )(dispatch, store.getState)

    expect(mockRemoveTasksFromTour).toHaveBeenCalledWith(
      expect.objectContaining({ '@id': '/api/tours/111' }),
      expect.arrayContaining([expect.objectContaining({ '@id': '/api/tasks/729' })])
    )
    expect(mockUnassignTasks).not.toHaveBeenCalled()
    expect(mockModifyTaskList).not.toHaveBeenCalled()
  })

  it('should reorder a task within the same tour', () => {
    const store = createStoreFromPreloadedState(storeFixture)
    const dispatch = jest.fn()
    const mockModifyTaskList = jest.fn()
    const mockModifyTour = jest.fn()

    // Tour 111 items: [729, 730, 731, 727]; move task 731 from index 2 to index 0
    handleDragEnd(
      {
        draggableId: '/api/tasks/731',
        source: { droppableId: 'tour:/api/tours/111', index: 2 },
        destination: { droppableId: 'tour:/api/tours/111', index: 0 },
      },
      mockModifyTaskList, mockModifyTour
    )(dispatch, store.getState)

    expect(mockModifyTour).toHaveBeenCalledWith(
      expect.objectContaining({ '@id': '/api/tours/111' }),
      [
        '/api/tasks/731',
        '/api/tasks/729',
        '/api/tasks/730',
        '/api/tasks/727',
      ]
    )
    expect(mockModifyTaskList).not.toHaveBeenCalled()
  })

  it('should move a task from a courier tasklist into a tour', () => {
    const store = createStoreFromPreloadedState(storeFixture)
    const dispatch = jest.fn()
    const mockModifyTaskList = jest.fn()
    const mockModifyTour = jest.fn()
    const mockUnassignTasks = jest.fn()

    // tasks 737 and 738 are linked (737.next = 738); both assigned to admin, neither in a tour
    // Tour 114 items: [733, 732]; dropping at index 1
    handleDragEnd(
      {
        draggableId: '/api/tasks/737',
        source: { droppableId: 'assigned:admin', index: 0 },
        destination: { droppableId: 'tour:/api/tours/114', index: 1 },
      },
      mockModifyTaskList, mockModifyTour, mockUnassignTasks
    )(dispatch, store.getState)

    expect(mockUnassignTasks).toHaveBeenCalledWith(
      'admin',
      expect.arrayContaining([
        expect.objectContaining({ '@id': '/api/tasks/737' }),
        expect.objectContaining({ '@id': '/api/tasks/738' }),
      ])
    )
    expect(mockModifyTour).toHaveBeenCalledWith(
      expect.objectContaining({ '@id': '/api/tours/114' }),
      [
        '/api/tasks/733',
        '/api/tasks/737',
        '/api/tasks/738',
        '/api/tasks/732',
      ]
    )
    expect(mockModifyTaskList).not.toHaveBeenCalled()
  })
})
