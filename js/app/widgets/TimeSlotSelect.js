import React from "react"

export default ({ initialChoices }) => {
    return <span>{initialChoices.map(choice => <span key={choice.value}>{choice.value}</span>) }</span>
}
