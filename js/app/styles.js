import React from 'react'

export function taskTypeColor(type) {
  switch (type.toUpperCase()) {
    case 'PICKUP':
      return '#E74C3C'
    case 'DROPOFF':
      return '#2ECC71'
    default:
      // fallback color; should not happen normally
      return '#CCC'
  }
}

export function taskTypeMapIcon(type) {
  switch (type.toUpperCase()) {
    case 'PICKUP':
      return 'cube'
    case 'DROPOFF':
      return 'arrow-down'
    default:
      // fallback icon; should not happen normally
      return 'question'
  }
}

export function taskTypeListIcon(type) {
  switch (type.toUpperCase()) {
    case 'PICKUP':
      return 'fa-cube'
    case 'DROPOFF':
      return 'fa-arrow-down'
    default:
      // fallback icon; should not happen normally
      return 'fa-question'
  }
}
