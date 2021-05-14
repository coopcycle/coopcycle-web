import React, { Component } from 'react'
import Autosuggest from 'react-autosuggest'
import { defaultTheme } from 'react-autosuggest/dist/theme'
import { debounce } from 'lodash'

// ------------------ Autosuggest ------------------


const getSuggestionValue = suggestion => suggestion.name

const renderSuggestion = suggestion => (<span>{suggestion.name}</span>)

const loadingTheme = {
  ...defaultTheme,
  container: `${defaultTheme.container} react-autosuggest__container--loading`,
}

export default class extends Component {

  constructor(props) {
    super(props)

    this.state = {
      value: '',
      suggestions: [],
      isFetching: false,
    }

    this.onSuggestionsFetchRequested = debounce(
      this.onSuggestionsFetchRequested.bind(this),
      350
    )
  }

  onChange(event, { newValue }) {
    this.setState({
      value: newValue
    })
  }

  shouldRenderSuggestions(value) {
    return value.trim().length >= 3
  }

  onSuggestionsFetchRequested({ value }) {
    this.setState({ isFetching: true })
    $.getJSON(this.props.baseURL, { q: value }, data => {
      this.setState({
        suggestions: this.props.responseProp ? data[this.props.responseProp] : data,
        isFetching: false,
      })
    })
  }

  onSuggestionSelected(event, { suggestion }) {
    const { clearOnSelect } = this.props
    let newState = {
      suggestions: []
    }
    if (clearOnSelect) {
      newState.value = ''
    }
    this.props.onSuggestionSelected(suggestion)
    this.setState(newState)
  }

  onSuggestionsClearRequested() {
    this.setState({
      suggestions: []
    })
  }

  render() {
    const { value, suggestions, isFetching } = this.state

    const inputProps = {
      placeholder: this.props.placeholder,
      value,
      onChange: this.onChange.bind(this),
      className: 'form-control'
    }

    return (
      <Autosuggest
        theme={ isFetching ? loadingTheme : defaultTheme }
        suggestions={suggestions}
        onSuggestionsFetchRequested={this.onSuggestionsFetchRequested}
        onSuggestionsClearRequested={this.onSuggestionsClearRequested.bind(this)}
        onSuggestionSelected={this.onSuggestionSelected.bind(this)}
        getSuggestionValue={getSuggestionValue}
        renderSuggestion={ this.props.renderSuggestion || renderSuggestion }
        shouldRenderSuggestions={this.shouldRenderSuggestions.bind(this)}
        inputProps={inputProps}
      />
    )
  }
}
