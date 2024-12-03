import React, { useState } from "react"

export default ({ initialChoices, onChange }) => {

    const [value, setValue] = useState(initialChoices[0].value)

    return <select
        onChange={e => {setValue(e.target.value); onChange(e.target.value)}}
        value={value}
        >
        {initialChoices.map(choice => <option key={choice.value} value={choice.value}>{choice.value}</option>) }
    </select>
}
