import React from 'react'

export function taskTypeColor(type) {
  switch (type.toUpperCase()) {
    case 'PICKUP':
      return '#E74C3C'
    case 'DROPOFF':
      return '#2ECC71'
    default:
      // fallback color; should not happen normally
      return '#777'
  }
}

export function taskColor(type, status) {
  if (status === 'CANCELLED') {
    return '#777'
  }

  switch (type.toUpperCase()) {
    case 'PICKUP':
      return '#E74C3C'
    case 'DROPOFF':
      return '#2ECC71'
    default:
      // fallback color; should not happen normally
      return '#777'
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

export function taskMapIcon(type, status) {
  switch (status) {
    case 'TODO':
      return taskTypeMapIcon(type)
    case 'DOING':
      return 'play'
    case 'DONE':
      return 'check'
    case 'FAILED':
      return 'remove'
    case 'CANCELLED':
      return 'ban'
  }

  return 'question'
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

export function taskListIcon(type, status) {
  switch (status) {
    case 'TODO':
      return taskTypeListIcon(type)
    case 'DOING':
      return 'fa-play'
    case 'DONE':
      return 'fa-check'
    case 'FAILED':
      return 'fa-remove'
    case 'CANCELLED':
      return 'fa-ban'
  }

  return 'fa-question'
}
