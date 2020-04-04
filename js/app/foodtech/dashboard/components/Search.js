import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import { Input } from 'antd'
import _ from 'lodash'

import { search } from '../redux/actions'

class Search extends React.Component {

  constructor(props) {
    super(props)

    this.state = {
      q: '',
      results: []
    }

    this.search = _.debounce(this.props.search.bind(this), 150)
  }

  render() {

    return (
      <Input.Search
        value={ this.state.q }
        placeholder={ this.props.t('RESTAURANT_DASHBOARD_SEARCH_PLACEHOLDER') }
        onSearch={ value => {
          this.setState({ q: value })
          this.search(value)
        }}
        onChange={ e => {
          this.setState({ q: e.target.value })
          this.search(e.target.value)
        }} />
    )
  }
}

function mapStateToProps() {

  return {}
}

function mapDispatchToProps(dispatch) {

  return {
    search: q => dispatch(search(q))
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(Search))
