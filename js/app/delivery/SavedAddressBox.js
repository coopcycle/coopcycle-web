import React, { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useCombobox } from 'downshift'

function getAddressesFilter(inputValue) {
  const lowerCasedInputValue = inputValue.toLowerCase()

  return function addressesFilter(address) {
    return (
      !inputValue ||
      address.name?.toLowerCase().includes(lowerCasedInputValue)
    )
  }
}

export const SavedAddressesBox = ({ addresses, address, onSelected }) => {
  const [items, setItems] = useState([])
  const { t } = useTranslation()
  const {
    isOpen,
    getMenuProps,
    getInputProps,
    getItemProps,
  } = useCombobox({
    onInputValueChange({ inputValue }) {
      setItems(addresses.filter(getAddressesFilter(inputValue)))
    },
    items,
    itemToString(item) {
      return item ? `${item.name} - ${item.streetAddress}` : ''
    },
    onSelectedItemChange({ selectedItem }) {
      onSelected(selectedItem)
    }
  })

  return (
    <div className="mb-2">
      <label className="control-label">{t('TASK_FORM_SEARCH_SAVED_ADDRESS_BY_NAME')}</label>
      <div className="form-group-search">
        <input
          placeholder={t('TASK_FORM_SEARCH_ADDRESS_NAME_PLACEHOLDER')}
          className="form-control"
          {...getInputProps()} />
      </div>
      <ul
        className={`SavedAddressesBox__Results list-unstyled bg-white shadow-md ${!isOpen && 'hidden'}`}
        {...getMenuProps()}
      >
        {isOpen &&
          items.map((item, index) => (
            <li
              className={`py-2 px-3 shadow-sm ${(address && address["@id"] === item["@id"]) && 'font-weight-bold'}`}
              key={item['@id']}
              {...getItemProps({ item, index })}
            >
              <span>{item.name} - {item.streetAddress}</span>
            </li>
          ))}
      </ul>
    </div>
  )
}
