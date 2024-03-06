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
        mockModifyTaskList = jest.fn()
  
      let result = {
        draggableId: 'tour:/api/tours/114',
        source: {droppableId: 'unassigned:'}, destination: {droppableId: 'assigned:admin', index: 0},
      }
    
      handleDragEnd(result, mockModifyTaskList)(dispatch, store.getState)
  
  
      expect(mockModifyTaskList).toHaveBeenLastCalledWith(
        "admin",
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tasks/733' }),
          expect.objectContaining({"@id": '/api/tasks/732'}),
          expect.objectContaining({"@id": '/api/tasks/729'}),
          expect.objectContaining({"@id": '/api/tasks/730'}),
          expect.objectContaining({"@id": '/api/tasks/731'}),
          expect.objectContaining({"@id": '/api/tasks/727'})
        ]),
        null
      )
        
    })
  
    it ('should reorder a tour inside a tasklist', () => {
      const dispatch = jest.fn(),
        mockModifyTaskList = jest.fn()
  
      let result = {
        draggableId: 'tour:/api/tours/114',
        source: {droppableId: 'unassigned:'},
        destination: {droppableId: 'assigned:admin', index: 1}
      }
    
      handleDragEnd(result, mockModifyTaskList)(dispatch, store.getState)
  
      expect(mockModifyTaskList).toHaveBeenLastCalledWith(
        "admin",
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tasks/729'}),
          expect.objectContaining({"@id": '/api/tasks/730'}),
          expect.objectContaining({"@id": '/api/tasks/731'}),
          expect.objectContaining({"@id": '/api/tasks/727'}),
          expect.objectContaining({"@id": '/api/tasks/733' }),
          expect.objectContaining({"@id": '/api/tasks/732'}),
        ]),
        null
      )
        
    })
  
    it ('should assign a group at the end of a tasklist',  () => {
      const dispatch = jest.fn(),
        mockModifyTaskList = jest.fn()
  
      let result = {
        draggableId: 'group:23',
        source: {droppableId: 'unassigned:'}, destination: {droppableId: 'assigned:admin', index: 1}
      }
    
      handleDragEnd(result, mockModifyTaskList)(dispatch, store.getState)
  
      expect(mockModifyTaskList).toHaveBeenLastCalledWith(
        "admin",
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tasks/729'}),
          expect.objectContaining({"@id": '/api/tasks/730'}),
          expect.objectContaining({"@id": '/api/tasks/731'}),
          expect.objectContaining({"@id": '/api/tasks/727'}),
          expect.objectContaining(
            {"@id": '/api/tasks/736', 'group': expect.objectContaining({"@id": "/api/task_groups/23"})})
        ]),
        null
      )
        
    })
  
    it ('should assign a task in a tour which is already assigned', async () => {
      const dispatch = jest.fn(),
        mockModifyTaskList = jest.fn(),
        mockModifyTour = jest.fn()
  
      dispatch.mockReturnValue().mockResolvedValueOnce({}).mockReturnValue()
  
      let result = {
        draggableId: '/api/tasks/734',
        source: {droppableId: 'unassigned'}, destination: {droppableId: 'tour:/api/tours/111', index: 1}
      }
    
      handleDragEnd(result, mockModifyTaskList, mockModifyTour)(dispatch, store.getState)
  
      expect(mockModifyTaskList).toHaveBeenCalledWith(
        "admin",
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tasks/729'}),
          expect.objectContaining({"@id": '/api/tasks/734'}),
          expect.objectContaining({"@id": '/api/tasks/735'}),
          expect.objectContaining({"@id": '/api/tasks/730'}),
          expect.objectContaining({"@id": '/api/tasks/731'}),
          expect.objectContaining({"@id": '/api/tasks/727'}),
        ]),
        expect.any(Function)
  
      )
      
      // kind of hackish way to wait for mockModifyTaskList.then(...) to reolve before testing the call to modifyTour
      await Promise.resolve();
  
      expect(mockModifyTour).toHaveBeenLastCalledWith(
        expect.objectContaining({'@id': '/api/tours/111'}),
        expect.arrayContaining([
          expect.objectContaining({"@id": '/api/tasks/729'}),
          expect.objectContaining({"@id": '/api/tasks/734'}),
          expect.objectContaining({"@id": '/api/tasks/735'}),
          expect.objectContaining({"@id": '/api/tasks/730'}),
          expect.objectContaining({"@id": '/api/tasks/731'}),
          expect.objectContaining({"@id": '/api/tasks/727'}),
        ]),
        true
      )
        
    })

    it ('should not unassign a task from tasklist to unassigned tour', () => {
      const dispatch = jest.fn()

      let result = {
        draggableId: '/api/tasks/731',
        source: {droppableId: 'assigned:admin'}, destination: {droppableId: 'tour:/api/tours/114', index: 1}
      }

      handleDragEnd(result)(dispatch, store.getState)

      expect(dispatch).toHaveBeenCalledTimes(0)

    })
  
    
  
})
  