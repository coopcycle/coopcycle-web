import React from 'react'
import Alert from './Alert'

export default {
  title: 'Design System/3. Alert',
  tags: ['autodocs'],
  component: Alert,

  decorators: [
    Story => {
      return <Story />
    },
  ],
}

export const Default = {
  args: {
    children: 'Default',
  },
}

export const Success = {
  args: {
    children: 'Success',
    success: true,
  },
}

export const Info = {
  args: {
    children: 'Info',
    info: true,
  },
}

export const Warning = {
  args: {
    children: 'Warning',
    warning: true,
  },
}

export const Danger = {
  args: {
    children: 'Danger',
    danger: true,
  },
}

export const Loading = {
  args: {
    children: 'Loading',
    loading: true,
  },
}

export const WithIcon = {
  args: {
    children: 'With Icon',
    icon: 'home',
  },
}
