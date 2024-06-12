import { START_TASKS_MULTI, ADD_TO_GROUP, ASSIGN_MULTI, ASSIGN_WITH_LINKED_TASKS_MULTI, CANCEL_MULTI, CREATE_DELIVERY, CREATE_GROUP, CREATE_TOUR, MOVE_TO_BOTTOM, MOVE_TO_NEXT_DAY_MULTI, MOVE_TO_NEXT_WORKING_DAY_MULTI, MOVE_TO_TOP, REMOVE_FROM_GROUP, UNASSIGN_MULTI, UNASSIGN_SINGLE, RESCHEDULE, REPORT_INCIDENT, getAvailableActionsForTasks } from '../../components/context-menus/TasksContextMenu'


describe('updateTask', () => {
    it('should return actions for a single unassigned task', () => {

        const selectedTasks = [{'@id': '/api/tasks/730', assignedTo: '', previous: null, next: null, packages: [], tour: null}]
        const unassignedTasks = [{'@id': '/api/tasks/730', assignedTo: '', previous: null, next: null, packages: [], tour: null}]
        const linkedTasksIds = []

        const actions = getAvailableActionsForTasks(selectedTasks, unassignedTasks, linkedTasksIds)

        expect(actions).toStrictEqual([ASSIGN_MULTI, ASSIGN_WITH_LINKED_TASKS_MULTI, CREATE_GROUP, CREATE_TOUR, CANCEL_MULTI, START_TASKS_MULTI, RESCHEDULE, REPORT_INCIDENT, MOVE_TO_NEXT_DAY_MULTI, MOVE_TO_NEXT_WORKING_DAY_MULTI, ADD_TO_GROUP])
    })

    it('should return actions for one unassigned pickup and one unassigned dropoff into a group', () => {
        const selectedTasks = [
            {'@id': '/api/tasks/730', type: 'PICKUP', assignedTo: '', previous: null, next: null, packages: [], tour: null, group: {'@id': '/api/task_groups/22' }},
            {'@id': '/api/tasks/731', type: 'DROPOFF', assignedTo: '', previous: null, next: null, packages: [], tour: null, group: {'@id': '/api/task_groups/22' }}
        ]
        const unassignedTasks = [
            {'@id': '/api/tasks/730', type: 'PICKUP', assignedTo: '', previous: null, next: null, packages: [], tour: null, group: {'@id': '/api/task_groups/22' }},
            {'@id': '/api/tasks/731', type: 'DROPOFF', assignedTo: '', previous: null, next: null, packages: [], tour: null, group: {'@id': '/api/task_groups/22' }}]
        const linkedTasksIds = []

        const actions = getAvailableActionsForTasks(selectedTasks, unassignedTasks, linkedTasksIds)

        expect(actions).toStrictEqual([START_TASKS_MULTI, ASSIGN_MULTI, ASSIGN_WITH_LINKED_TASKS_MULTI, CANCEL_MULTI, CREATE_GROUP, CREATE_TOUR, REMOVE_FROM_GROUP, CREATE_DELIVERY, MOVE_TO_NEXT_DAY_MULTI, MOVE_TO_NEXT_WORKING_DAY_MULTI])

    })

    it('should return actions for a single assigned task', () => {

        const selectedTasks = [{'@id': '/api/tasks/730', assignedTo: 'admin', isAssigned: true, previous: null, next: null, packages: [], tour: null}]
        const unassignedTasks = []
        const linkedTasksIds = []

        const actions = getAvailableActionsForTasks(selectedTasks, unassignedTasks, linkedTasksIds)

        expect(actions).toStrictEqual([UNASSIGN_SINGLE, MOVE_TO_TOP, MOVE_TO_BOTTOM, CANCEL_MULTI, START_TASKS_MULTI, RESCHEDULE, REPORT_INCIDENT])
    })

    it('should return actions for several assigned tasks', () => {

        const selectedTasks = [
            {'@id': '/api/tasks/730', assignedTo: 'admin', isAssigned: true, previous: null, next: null, packages: [], tour: null},
            {'@id': '/api/tasks/731', assignedTo: 'admin', isAssigned: true, previous: null, next: null, packages: [], tour: null},
        ]
        const unassignedTasks = []
        const linkedTasksIds = []

        const actions = getAvailableActionsForTasks(selectedTasks, unassignedTasks, linkedTasksIds)

        expect(actions).toStrictEqual([START_TASKS_MULTI, UNASSIGN_MULTI])
    })

    it('should return actions for a task into an unassigned tour', () => {
        const selectedTasks = [{'@id': '/api/tasks/730', assignedTo: '', previous: null, next: null, packages: [], tour: {'@id': '/api/tours/111'}}]
        const unassignedTasks = [{'@id': '/api/tasks/730', assignedTo: '', previous: null, next: null, packages: [], tour: {'@id': '/api/tours/111'}}]
        const linkedTasksIds = []

        const actions = getAvailableActionsForTasks(selectedTasks, unassignedTasks, linkedTasksIds, true)

        expect(actions).toStrictEqual([])
    })

    it('should return actions for several tasks into an unassigned tour', () => {
        const selectedTasks = [
            {'@id': '/api/tasks/730', assignedTo: '', previous: null, next: null, packages: [], tour: {'@id': '/api/tours/111'}},
            {'@id': '/api/tasks/731', assignedTo: '', previous: null, next: null, packages: [], tour: {'@id': '/api/tours/111'}}
        ]
        const unassignedTasks = [
            {'@id': '/api/tasks/730', assignedTo: '', previous: null, next: null, packages: [], tour: {'@id': '/api/tours/111'}},
            {'@id': '/api/tasks/731', assignedTo: '', previous: null, next: null, packages: [], tour: {'@id': '/api/tours/111'}}
        ]
        const linkedTasksIds = []

        const actions = getAvailableActionsForTasks(selectedTasks, unassignedTasks, linkedTasksIds, true)

        expect(actions).toStrictEqual([])
    })

    it('should return actions for several tasks into an assigned tour', () => {
        const selectedTasks = [
            {'@id': '/api/tasks/730', assignedTo: 'admin', isAssigned: true, previous: null, next: null, packages: [], tour: {'@id': '/api/tours/111'}},
            {'@id': '/api/tasks/731', assignedTo: 'admin', isAssigned: true, previous: null, next: null, packages: [], tour: {'@id': '/api/tours/111'}}
        ]
        const unassignedTasks = []
        const linkedTasksIds = []

        const actions = getAvailableActionsForTasks(selectedTasks, unassignedTasks, linkedTasksIds, true)

        expect(actions).toStrictEqual([])
    })
})
