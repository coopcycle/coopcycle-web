import React from 'react'
import Button from './Button'

export default {
  title: 'Design System/2. Button',
  tags: ['autodocs'],
  component: Button,

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

export const Primary = {
  args: {
    children: 'Primary',
    primary: true,
  },
}

export const Success = {
  args: {
    children: 'Success',
    success: true,
  },
}

export const Danger = {
  args: {
    children: 'Danger',
    danger: true,
  },
}

export const Link = {
  args: {
    children: 'Link',
    link: true,
  },
}

export const Loading = {
  args: {
    children: 'Default',
    loading: true,
  },
}

export const WithIcon = {
  args: {
    children: 'With Icon',
    icon: 'home',
  },
}
