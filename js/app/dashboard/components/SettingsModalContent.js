import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import Radio from 'antd/lib/radio'
import Form from 'antd/lib/form'

import {
  closeSettings,
  setPolylineStyle } from '../redux/actions'

const formItemLayout = {
  labelCol: { span: 10 },
  wrapperCol: { span: 14 },
}
const buttonItemLayout = {
  wrapperCol: { span: 24 },
}

class SettingsModalContent extends React.Component {

  constructor (props) {
    super(props)
    this.state = {
      value: 'normal'
    }
  }

  handleSubmit(e) {
    e.preventDefault()
    this.props.setPolylineStyle(this.state.value)
    this.props.closeSettings()
  }

  render() {

    return (
      <Form layout="horizontal" colon={ false }
        onSubmit={ this.handleSubmit.bind(this) }>
        <Form.Item label={ this.props.t('ADMIN_DASHBOARD_SETTINGS_POLYLINE') } { ...formItemLayout }>
          <Radio.Group defaultValue={ this.props.polylineStyle }
            onChange={ (e) => this.setState({ value: e.target.value }) }>
            <Radio.Button value="normal">Normal</Radio.Button>
            <Radio.Button value="as_the_crow_flies">
              { this.props.t('ADMIN_DASHBOARD_SETTINGS_POLYLINE_AS_THE_CROW_FLIES') }
            </Radio.Button>
          </Radio.Group>
        </Form.Item>
        <Form.Item { ...buttonItemLayout }>
          <button type="submit" className="btn btn-block btn-primary">
            { this.props.t('ADMIN_DASHBOARD_FILTERS_APPLY') }
          </button>
        </Form.Item>
      </Form>
    )
  }
}

function mapStateToProps(state) {

  return {
    polylineStyle: state.polylineStyle
  }
}

function mapDispatchToProps(dispatch) {

  return {
    setPolylineStyle: style => dispatch(setPolylineStyle(style)),
    closeSettings: () => dispatch(closeSettings())
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(SettingsModalContent))
