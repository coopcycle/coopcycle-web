import _ from 'lodash'

export const numericTypes = [
  'distance',
  'weight',
  'diff_days(pickup)',
  'diff_hours(pickup)',
  'order.itemsTotal',
]

export const isNum = (type) => _.includes(numericTypes, type)

const convertToRange = (value) => {
  if (Array.isArray(value) && value.length === 2) {
    return `${value[0]}..${value[1]}`
  }

  return value
}

export function lineToString(line) {
  /*
  Build the expression line from the user's input stored in state.
  `line` is an object with the following properties:
  {
    left // the variable the rule is built upon
    operator // the operator/function used to build the rule
    right // the value(s) which complete the rule
  }
  Returns nothing if we can't build the line.
  */

  if (line.left === 'diff_days(pickup)') {
    return `diff_days(pickup, '${line.operator} ${convertToRange(line.right)}')`
  }

  if (line.left === 'diff_hours(pickup)') {
    return `diff_hours(pickup, '${line.operator} ${convertToRange(line.right)}')`
  }

  if (line.left === `time_range_length(pickup, 'hours')`) {
    return `time_range_length(pickup, 'hours', '${line.operator} ${convertToRange(line.right)}')`
  }

  if (line.left === `time_range_length(dropoff, 'hours')`) {
    return `time_range_length(dropoff, 'hours', '${line.operator} ${convertToRange(line.right)}')`
  }

  if (line.operator === 'in' && Array.isArray(line.right) && line.right.length === 2) {
    return `${line.left} in ${line.right[0]}..${line.right[1]}`
  }

  if (line.left === 'packages' && line.operator === 'containsAtLeastOne') {
    return `packages.containsAtLeastOne("${line.right}")`
  }

  switch (line.operator) {
    case '<':
    case '>':
      return `${line.left} ${line.operator} ${line.right}`
    case 'in_zone':
    case 'out_zone':
      return `${line.operator}(${line.left}, "${line.right}")`
    case '==':
    case '!=':
      if (line.left === 'dropoff.doorstep' || _.includes(numericTypes, line.left)) {
        return `${line.left} ${line.operator} ${line.right}`
      }
      return `${line.left} ${line.operator} "${line.right}"`
  }
}
