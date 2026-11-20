import { numericTypes, isNum, lineToString, linesToString } from '../expression-builder'
import { parseAST } from '../pricing-rule-parser'
import withDiffDaysPickup from './with-diff-days-pickup.json'
import withDiffDaysDropoff from './with-diff-days-dropoff.json'
import withDiffDaysDropoffWithRange from './with-diff-days-dropoff-in.json'
import withDiffHoursDropoff from './with-diff-hours-dropoff.json'

describe('isNum', () => {
  it('should return true for numeric types', () => {
    numericTypes.forEach(type => {
      expect(isNum(type)).toBe(true)
    })
  })

  it('should return false for non-numeric types', () => {
    const nonNumericTypes = ['address', 'name', 'type']
    nonNumericTypes.forEach(type => {
      expect(isNum(type)).toBe(false)
    })
  })
})

describe('lineToString', () => {
  describe('distance', () => {
    it('should handle < operator', () => {
      const result = lineToString({ left: 'distance', operator: '<', right: 3000 })
      expect(result).toEqual('distance < 3000')
    })

    it('should handle > operator', () => {
      const result = lineToString({ left: 'distance', operator: '>', right: 1000 })
      expect(result).toEqual('distance > 1000')
    })

    it('should handle in operator with range', () => {
      const result = lineToString({ left: 'distance', operator: 'in', right: [1000, 3000] })
      expect(result).toEqual('distance in 1000..3000')
    })
  })

  describe('weight', () => {
    it('should handle < operator', () => {
      const result = lineToString({ left: 'weight', operator: '<', right: 500 })
      expect(result).toEqual('weight < 500')
    })

    it('should handle > operator', () => {
      const result = lineToString({ left: 'weight', operator: '>', right: 1500 })
      expect(result).toEqual('weight > 1500')
    })

    it('should handle in operator with range', () => {
      const result = lineToString({ left: 'weight', operator: 'in', right: [500, 2000] })
      expect(result).toEqual('weight in 500..2000')
    })
  })

  describe('vehicle', () => {
    it('should handle == operator', () => {
      const result = lineToString({ left: 'vehicle', operator: '==', right: 'bike' })
      expect(result).toEqual('vehicle == "bike"')
    })
  })

  describe('pickup.address', () => {
    it('should handle in_zone operator', () => {
      const result = lineToString({ left: 'pickup.address', operator: 'in_zone', right: 'paris_est' })
      expect(result).toEqual('in_zone(pickup.address, "paris_est")')
    })

    it('should handle out_zone operator', () => {
      const result = lineToString({ left: 'pickup.address', operator: 'out_zone', right: 'paris_ouest' })
      expect(result).toEqual('out_zone(pickup.address, "paris_ouest")')
    })
  })

  describe('dropoff.address', () => {
    it('should handle in_zone operator', () => {
      const result = lineToString({ left: 'dropoff.address', operator: 'in_zone', right: 'paris_sud' })
      expect(result).toEqual('in_zone(dropoff.address, "paris_sud")')
    })

    it('should handle out_zone operator', () => {
      const result = lineToString({ left: 'dropoff.address', operator: 'out_zone', right: 'paris_nord' })
      expect(result).toEqual('out_zone(dropoff.address, "paris_nord")')
    })
  })

  describe('diff_days(pickup)', () => {
    it('should handle == operator', () => {
      const result = lineToString({ left: 'diff_days(pickup)', operator: '==', right: 1 })
      expect(result).toEqual('diff_days(pickup, \'== 1\')')
    })

    it('should handle < operator', () => {
      const result = lineToString({ left: 'diff_days(pickup)', operator: '<', right: 3 })
      expect(result).toEqual('diff_days(pickup, \'< 3\')')
    })

    it('should handle > operator', () => {
      const result = lineToString({ left: 'diff_days(pickup)', operator: '>', right: 0 })
      expect(result).toEqual('diff_days(pickup, \'> 0\')')
    })

    it('should handle in operator with range', () => {
      const result = lineToString({ left: 'diff_days(pickup)', operator: 'in', right: [0, 3] })
      expect(result).toEqual('diff_days(pickup, \'in 0..3\')')
    })
  })

  describe('diff_hours(pickup)', () => {
    it('should handle == operator', () => {
      const result = lineToString({ left: 'diff_hours(pickup)', operator: '==', right: 2 })
      expect(result).toEqual('diff_hours(pickup, \'== 2\')')
    })

    it('should handle < operator', () => {
      const result = lineToString({ left: 'diff_hours(pickup)', operator: '<', right: 12 })
      expect(result).toEqual('diff_hours(pickup, \'< 12\')')
    })

    it('should handle > operator', () => {
      const result = lineToString({ left: 'diff_hours(pickup)', operator: '>', right: 6 })
      expect(result).toEqual('diff_hours(pickup, \'> 6\')')
    })
  })

  describe('diff_days(dropoff)', () => {
    it('should handle < operator', () => {
      const result = lineToString({ left: 'diff_days(dropoff)', operator: '<', right: 1 })
      expect(result).toEqual('diff_days(dropoff, \'< 1\')')
    })

    it('should handle in operator with range', () => {
      const result = lineToString({ left: 'diff_days(dropoff)', operator: 'in', right: [0, 1] })
      expect(result).toEqual('diff_days(dropoff, \'in 0..1\')')
    })
  })

  describe('diff_hours(dropoff)', () => {
    it('should handle < operator', () => {
      const result = lineToString({ left: 'diff_hours(dropoff)', operator: '<', right: 3 })
      expect(result).toEqual('diff_hours(dropoff, \'< 3\')')
    })
  })

  describe('round trip from the AST sent by the server', () => {
    // A rule saved again without being edited must keep its expression
    it.each([
      [ withDiffDaysPickup, 'diff_days(pickup, \'< 1\')' ],
      [ withDiffDaysDropoff, 'diff_days(dropoff, \'< 1\')' ],
      [ withDiffDaysDropoffWithRange, 'diff_days(dropoff, \'in 0..1\')' ],
      [ withDiffHoursDropoff, 'diff_hours(dropoff, \'< 3\')' ],
    ])('should rebuild %#', (ast, expression) => {
      expect(linesToString(parseAST(ast))).toEqual(expression)
    })
  })

  describe('dropoff.doorstep', () => {
    it('should handle == operator with boolean', () => {
      const result = lineToString({ left: 'dropoff.doorstep', operator: '==', right: true })
      expect(result).toEqual('dropoff.doorstep == true')
    })
  })

  describe('packages', () => {
    it('should handle containsAtLeastOne operator', () => {
      const result = lineToString({ left: 'packages', operator: 'containsAtLeastOne', right: 'large_box' })
      expect(result).toEqual('packages.containsAtLeastOne("large_box")')
    })
  })

  describe('order.itemsTotal', () => {
    it('should handle == operator', () => {
      const result = lineToString({ left: 'order.itemsTotal', operator: '==', right: 50 })
      expect(result).toEqual('order.itemsTotal == 50')
    })

    it('should handle < operator', () => {
      const result = lineToString({ left: 'order.itemsTotal', operator: '<', right: 100 })
      expect(result).toEqual('order.itemsTotal < 100')
    })

    it('should handle > operator', () => {
      const result = lineToString({ left: 'order.itemsTotal', operator: '>', right: 30 })
      expect(result).toEqual('order.itemsTotal > 30')
    })

    it('should handle in operator with range', () => {
      const result = lineToString({ left: 'order.itemsTotal', operator: 'in', right: [20, 50] })
      expect(result).toEqual('order.itemsTotal in 20..50')
    })
  })

  describe('task.dropoffRank', () => {
    it('should handle == operator', () => {
      const result = lineToString({ left: 'task.dropoffRank', operator: '==', right: 2 })
      expect(result).toEqual('task.dropoffRank == 2')
    })

    it('should handle < operator', () => {
      const result = lineToString({ left: 'task.dropoffRank', operator: '<', right: 3 })
      expect(result).toEqual('task.dropoffRank < 3')
    })

    it('should handle > operator', () => {
      const result = lineToString({ left: 'task.dropoffRank', operator: '>', right: 1 })
      expect(result).toEqual('task.dropoffRank > 1')
    })

    it('should handle in operator with range', () => {
      const result = lineToString({ left: 'task.dropoffRank', operator: 'in', right: ['2', '3'] })
      expect(result).toEqual('task.dropoffRank in 2..3')
    })

    it('should be combined with the task type', () => {
      const result = linesToString([
        { left: 'task.type', operator: '==', right: 'DROPOFF' },
        { left: 'task.dropoffRank', operator: '>', right: 1 },
      ])
      expect(result).toEqual('task.type == "DROPOFF" and task.dropoffRank > 1')
    })
  })

  describe('task.pickupRank', () => {
    it('should handle == operator', () => {
      const result = lineToString({ left: 'task.pickupRank', operator: '==', right: 2 })
      expect(result).toEqual('task.pickupRank == 2')
    })

    it('should handle > operator', () => {
      const result = lineToString({ left: 'task.pickupRank', operator: '>', right: 1 })
      expect(result).toEqual('task.pickupRank > 1')
    })

    it('should handle in operator with range', () => {
      const result = lineToString({ left: 'task.pickupRank', operator: 'in', right: ['2', '3'] })
      expect(result).toEqual('task.pickupRank in 2..3')
    })
  })

  describe('delivery.packages.totalVolumeUnits()', () => {
    it('should build a greater than expression', () => {
      const result = lineToString({ left: 'delivery.packages.totalVolumeUnits()', operator: '>', right: 5 })
      expect(result).toEqual('delivery.packages.totalVolumeUnits() > 5')
    })

    it('should build a range expression', () => {
      const result = lineToString({ left: 'delivery.packages.totalVolumeUnits()', operator: 'in', right: [3, 8] })
      expect(result).toEqual('delivery.packages.totalVolumeUnits() in 3..8')
    })
  })

  describe('packages.totalVolumeUnits()', () => {
    it('should handle < operator', () => {
      const result = lineToString({ left: 'packages.totalVolumeUnits()', operator: '<', right: 10 })
      expect(result).toEqual('packages.totalVolumeUnits() < 10')
    })

    it('should handle > operator', () => {
      const result = lineToString({ left: 'packages.totalVolumeUnits()', operator: '>', right: 5 })
      expect(result).toEqual('packages.totalVolumeUnits() > 5')
    })

    it('should handle in operator with range', () => {
      const result = lineToString({ left: 'packages.totalVolumeUnits()', operator: 'in', right: [3, 8] })
      expect(result).toEqual('packages.totalVolumeUnits() in 3..8')
    })
  })

  describe('time_range_length(pickup, \'hours\')', () => {
    it('should handle < operator', () => {
      const result = lineToString({ left: 'time_range_length(pickup, \'hours\')', operator: '<', right: 4 })
      expect(result).toEqual('time_range_length(pickup, \'hours\', \'< 4\')')
    })

    it('should handle > operator', () => {
      const result = lineToString({ left: 'time_range_length(pickup, \'hours\')', operator: '>', right: 2 })
      expect(result).toEqual('time_range_length(pickup, \'hours\', \'> 2\')')
    })

    it('should handle in operator with range', () => {
      const result = lineToString({ left: 'time_range_length(pickup, \'hours\')', operator: 'in', right: [1, 3] })
      expect(result).toEqual('time_range_length(pickup, \'hours\', \'in 1..3\')')
    })
  })

  describe('time_range_length(dropoff, \'hours\')', () => {
    it('should handle < operator', () => {
      const result = lineToString({ left: 'time_range_length(dropoff, \'hours\')', operator: '<', right: 4 })
      expect(result).toEqual('time_range_length(dropoff, \'hours\', \'< 4\')')
    })

    it('should handle > operator', () => {
      const result = lineToString({ left: 'time_range_length(dropoff, \'hours\')', operator: '>', right: 2 })
      expect(result).toEqual('time_range_length(dropoff, \'hours\', \'> 2\')')
    })

    it('should handle in operator with range', () => {
      const result = lineToString({ left: 'time_range_length(dropoff, \'hours\')', operator: 'in', right: [1, 3] })
      expect(result).toEqual('time_range_length(dropoff, \'hours\', \'in 1..3\')')
    })
  })

  describe('task.type', () => {
    it('should handle == operator', () => {
      const result = lineToString({ left: 'task.type', operator: '==', right: 'PICKUP' })
      expect(result).toEqual('task.type == "PICKUP"')
    })
  })

  describe('time_slot', () => {
    it('should handle == operator', () => {
      const result = lineToString({ left: 'time_slot', operator: '==', right: '/api/time_slots/1' })
      expect(result).toEqual('time_slot == "/api/time_slots/1"')
    })

    it('should handle != operator', () => {
      const result = lineToString({ left: 'time_slot', operator: '!=', right: '/api/time_slots/1' })
      expect(result).toEqual('time_slot != "/api/time_slots/1"')
    })
  })
})
