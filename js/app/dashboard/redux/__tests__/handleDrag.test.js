import configureStore from 'redux-mock-store'
import thunk from 'redux-thunk'

import { storeFixture } from './storeFixture'
import { handleDragEnd } from '../handleDrag';

// https://github.com/dmitry-zaets/redux-mock-store#asynchronous-actions
const middlewares = [ thunk]
const mockStore = configureStore(middlewares)

describe('handleDragEnd', () => {
    const store = mockStore(storeFixture)

    it ('should assign a tour at the beginning of a tasklist', () => {
      const dispatch = jest.fn(),
        mockModifyTaskList = jest.fn(),
        mockModifyTour = jest.fn()

      let result = {
        draggableId: 'tour:/api/tours/114',
        source: {droppableId: 'unassigned:'}, destination: {droppableId: 'assigned:admin', index: 0},
      }

      handleDragEnd(result, mockModifyTaskList, mockModifyTour)(dispatch, store.getState)

      expect(mockModifyTaskList).toHaveBeenCalledTimes(1)

      expect(mockModifyTaskList).toHaveBeenLastCalledWith(
        "admin",
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tasks/733' }),
          expect.objectContaining({"@id": '/api/tasks/732'}),
          expect.objectContaining({"@id": '/api/tasks/729'}),
          expect.objectContaining({"@id": '/api/tasks/730'}),
          expect.objectContaining({"@id": '/api/tasks/731'}),
          expect.objectContaining({"@id": '/api/tasks/727'})
        ])
      )

      expect(mockModifyTour).toHaveBeenCalledTimes(0)

    })

    it ('should assign a tour inside a tasklist at given index', () => {
      const dispatch = jest.fn(),
        mockModifyTaskList = jest.fn(),
        mockModifyTour = jest.fn()

      let result = {
        draggableId: 'tour:/api/tours/114',
        source: {droppableId: 'unassigned:'},
        destination: {droppableId: 'assigned:admin', index: 1}
      }

      handleDragEnd(result, mockModifyTaskList, mockModifyTour)(dispatch, store.getState)

      expect(mockModifyTaskList).toHaveBeenLastCalledWith(
        "admin",
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tasks/729'}),
          expect.objectContaining({"@id": '/api/tasks/730'}),
          expect.objectContaining({"@id": '/api/tasks/731'}),
          expect.objectContaining({"@id": '/api/tasks/727'}),
          expect.objectContaining({"@id": '/api/tasks/733' }),
          expect.objectContaining({"@id": '/api/tasks/732'}),
        ])
      )
      expect(mockModifyTour).toHaveBeenCalledTimes(0)

    })

    it ('should assign a group at the end of a tasklist',  () => {
      const dispatch = jest.fn(),
        mockModifyTaskList = jest.fn(),
        mockModifyTour = jest.fn()

      let result = {
        draggableId: 'group:23',
        source: {droppableId: 'unassigned:'}, destination: {droppableId: 'assigned:admin', index: 1}
      }

      handleDragEnd(result, mockModifyTaskList, mockModifyTour)(dispatch, store.getState)

      expect(mockModifyTaskList).toHaveBeenLastCalledWith(
        "admin",
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tasks/729'}),
          expect.objectContaining({"@id": '/api/tasks/730'}),
          expect.objectContaining({"@id": '/api/tasks/731'}),
          expect.objectContaining({"@id": '/api/tasks/727'}),
          expect.objectContaining(
            {"@id": '/api/tasks/736', 'group': expect.objectContaining({"@id": "/api/task_groups/23"})})
        ])
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

      expect(mockModifyTaskList).toHaveBeenCalledTimes(1)
      expect(mockModifyTaskList).toHaveBeenCalledWith(
        "admin",
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tasks/729'}),
          expect.objectContaining({"@id": '/api/tasks/734'}),
          expect.objectContaining({"@id": '/api/tasks/735'}),
          expect.objectContaining({"@id": '/api/tasks/730'}),
          expect.objectContaining({"@id": '/api/tasks/731'}),
          expect.objectContaining({"@id": '/api/tasks/727'}),
        ])
      )

      expect(mockModifyTour).toHaveBeenCalledTimes(1)
      expect(mockModifyTour).toHaveBeenLastCalledWith(
        expect.objectContaining({'@id': '/api/tours/111'}),
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tasks/729'}),
          expect.objectContaining({"@id": '/api/tasks/734'}),
          expect.objectContaining({"@id": '/api/tasks/735'}),
          expect.objectContaining({"@id": '/api/tasks/730'}),
          expect.objectContaining({"@id": '/api/tasks/731'}),
          expect.objectContaining({"@id": '/api/tasks/727'}),
        ])
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
      expect(mockUnassignTasks).toHaveBeenCalledTimes(1)

      expect(mockUnassignTasks).toHaveBeenCalledWith(
        "admin",
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tasks/731'}),
        ])
      )

      expect(mockModifyTour).toHaveBeenCalledTimes(1)
      expect(mockModifyTour).toHaveBeenCalledWith(
        expect.objectContaining({'@id': '/api/tours/114'}),
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tasks/732'}),
          expect.objectContaining({"@id": '/api/tasks/731'}),
          expect.objectContaining({"@id": '/api/tasks/733'}),
        ])
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

      expect(mockModifyTaskList).toHaveBeenCalledTimes(1)
      expect(mockModifyTaskList).toHaveBeenCalledWith(
        "admin",
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tasks/729'}),
          expect.objectContaining({"@id": '/api/tasks/733'}),
          expect.objectContaining({"@id": '/api/tasks/730'}),
          expect.objectContaining({"@id": '/api/tasks/731'}),
          expect.objectContaining({"@id": '/api/tasks/727'}),
        ])
      )

      expect(mockModifyTour).toHaveBeenCalledTimes(1)
      expect(mockModifyTour).toHaveBeenLastCalledWith(
        expect.objectContaining({'@id': '/api/tours/111'}),
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tasks/729'}),
          expect.objectContaining({"@id": '/api/tasks/733'}),
          expect.objectContaining({"@id": '/api/tasks/730'}),
          expect.objectContaining({"@id": '/api/tasks/731'}),
          expect.objectContaining({"@id": '/api/tasks/727'}),
        ])
      )

      expect(mockRemoveTasksFromTour).toHaveBeenCalledWith(
        expect.objectContaining({'@id': '/api/tours/114'}),
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tasks/733'}),
        ])
      )
    })

  })
