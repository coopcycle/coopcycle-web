import React,{ useEffect, useState } from "react"
import Select, { createFilter } from 'react-select'

export default ({placeholder, onChange, selectOptions, defaultValue, isLoading}) => {

    const [excludeState, setExcludeState] = useState(false)
    const [options, setOptions] = useState([])

    useEffect(() => {
      setOptions(selectOptions) // force re-render when options change
    }, [selectOptions]);

    const [inputValue, setInputValue] = useState('')

    const onChangeHandler = (selected) => {
      onChange(selected)

      if (excludeState) { // reset exclusion
        setExcludeState(false)
        setOptions(options.map(opt => {return {...opt, label: opt.label.replace(/^(-)(.*)/, '$2'), isExclusion: false}}))
      }
    }

    const onInputChange = (value) => {
      if (value.startsWith('-') && !excludeState) {
        setExcludeState(true)
        setOptions(options.map(opt => {return {...opt, label: `-${opt.label}`, isExclusion: true}}))
      }
      setInputValue(value)
    }

    const filterConfig = {
      ignoreCase: true,
      ignoreAccents: true,
      trim: true,
    }

    return (<Select
      isMulti={true}
      // https://github.com/coopcycle/coopcycle-web/issues/774
      // https://github.com/JedWatson/react-select/issues/3030
      // https://react-select.com/advanced#portaling
      menuPortalTarget={document.body}
      styles={{ menuPortal: (base) => ({ ...base, zIndex: 9999 }) }}
      options={options}
      onChange={(selected) => { onChangeHandler(selected) }}
      placeholder={ placeholder }
      inputValue={inputValue}
      onInputChange={onInputChange}
      filterOption={createFilter(filterConfig)}
      defaultValue={defaultValue}
      classNamePrefix="dashboard"
      isLoading={isLoading}
    />)
}
