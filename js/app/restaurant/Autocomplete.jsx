import React, { Component } from 'react'
import Autosuggest from 'react-autosuggest';

const getSuggestionValue = suggestion => suggestion.name;

const renderSuggestion = suggestion => (
  <span>
    {suggestion.name}
  </span>
);

export default class extends Component {

  constructor(props) {
    super(props);

    this.state = {
      value: '',
      suggestions: []
    };
  }

  onChange(event, { newValue }) {
    this.setState({
      value: newValue
    });
  };

  shouldRenderSuggestions(value) {
    return value.trim().length >= 3;
  }

  onSuggestionsFetchRequested({ value }) {
    $.getJSON(this.props.baseURL, { q: value }, data => {
      this.setState({
        suggestions: data
      });
    });
  };

  onSuggestionSelected(event, { suggestion, suggestionValue, suggestionIndex, sectionIndex, method }) {
    this.props.onRestaurantSelected(suggestion);
    this.setState({
      value: '',
      suggestions: []
    });
  }

  onSuggestionsClearRequested() {
    this.setState({
      suggestions: []
    });
  };

  render() {
    const { value, suggestions } = this.state;

    const inputProps = {
      placeholder: this.props.placeholder,
      value,
      onChange: this.onChange.bind(this),
      className: 'form-control'
    };

    return (
      <Autosuggest
        suggestions={suggestions}
        onSuggestionsFetchRequested={this.onSuggestionsFetchRequested.bind(this)}
        onSuggestionsClearRequested={this.onSuggestionsClearRequested.bind(this)}
        onSuggestionSelected={this.onSuggestionSelected.bind(this)}
        getSuggestionValue={getSuggestionValue}
        renderSuggestion={renderSuggestion}
        shouldRenderSuggestions={this.shouldRenderSuggestions.bind(this)}
        inputProps={inputProps}
      />
    );
  }
}
