import { AutoComplete, Input, Select } from 'antd'
import React, { useState } from 'react'
import { useTranslation } from 'react-i18next'

export const SavedAddressesBox = ({ addresses, onSelected }) => {

  const [results, setResults] = useState(addresses)
  const { t } = useTranslation()

  const resultsDisplay = results.map((address, index) => {
    return (
      <Select.Option key={index} >
        {address.name} - {address.streetAddress}
      </Select.Option>
    )})

  const [inputValue, setInputValue] = useState('')

  const search = (inputValue) => {
    const lowerCasedInputValue = inputValue.toLowerCase()

    setResults(
      addresses.filter(address =>
        address.name?.toLowerCase().includes(lowerCasedInputValue) || address.streetAddress?.toLowerCase().includes(lowerCasedInputValue)
      )
    )
  }

  return (
    <AutoComplete
      style={{"width": "100%"}}
      className="mb-2"
      value={ inputValue }
      onSearch={ value => {
        search(value)
        setInputValue(value)
      }}
      onSelect={(index) => {
        const result = results[index]
        setInputValue(result.name)
        onSelected(results[index])
      }}
      dataSource={resultsDisplay}
      dropdownStyle={{zIndex: 1}}
    >
      <Input
        addonBefore={<i className="fa fa-search"></i>}
        placeholder={ t('TASK_FORM_SEARCH_SAVED_ADDRESS_BY_NAME') }
      />
    </AutoComplete>
  )
}