import React from 'react'
import Spinner from './Spinner'

export default {
  title: 'Design System/3. Spinner',
  tags: ['autodocs'],
  component: Spinner,

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
