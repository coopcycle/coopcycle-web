import React from 'react'
import IncludeExcludeMultiSelect from './IncludeExcludeMultiSelect'

export default {
  title: 'Dispatch/1. Filter',
  tags: [ 'autodocs' ],
  component: IncludeExcludeMultiSelect,

  decorators: [
    (Story) => {
      return (
        <Story />
      )
    },
  ],
}

export const Basic = {
  args: {
    placeholder: 'Placeholder text',
    onChange: (selected) => {
        console.debug('List of selected options')
        console.debug(selected)
    },
    selectOptions: [{value: 'value', label: 'Displayed label', prop1: 'prop11', prop2: 'prop12'}, {value: 'value2', label: 'Displayed label 2', prop1: 'prop11', prop2: 'prop12'}],
    isLoading: false
  }
}
