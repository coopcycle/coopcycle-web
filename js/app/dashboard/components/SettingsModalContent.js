import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import { Form, Radio } from 'antd';

import {
  closeSettings,
  setPolylineStyle,
  setClustersEnabled } from '../redux/actions'

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
      polylineStyle: props.polylineStyle,
      clustersEnabled: props.clustersEnabled,
    }
  }

  handleSubmit() {
    this.props.setPolylineStyle(this.state.polylineStyle)
    this.props.setClustersEnabled(this.state.clustersEnabled)
    this.props.closeSettings()
  }

  render() {

    return (
      <Form layout="horizontal" colon={ false }>
        <Form.Item label={ this.props.t('ADMIN_DASHBOARD_SETTINGS_POLYLINE') } { ...formItemLayout }>
          <Radio.Group defaultValue={ this.props.polylineStyle }
            onChange={ (e) => this.setState({ polylineStyle: e.target.value }) }>
            <Radio.Button value="normal">Normal</Radio.Button>
            <Radio.Button value="as_the_crow_flies">
              { this.props.t('ADMIN_DASHBOARD_SETTINGS_POLYLINE_AS_THE_CROW_FLIES') }
            </Radio.Button>
          </Radio.Group>
        </Form.Item>
        <Form.Item label={ this.props.t('ADMIN_DASHBOARD_SETTINGS_CLUSTERS_ENABLED') } { ...formItemLayout }>
          <Radio.Group defaultValue={ this.props.clustersEnabled }
            onChange={ (e) => this.setState({ clustersEnabled: e.target.value }) }>
            <Radio.Button value={ true }>Yes</Radio.Button>
            <Radio.Button value={ false }>No</Radio.Button>
          </Radio.Group>
        </Form.Item>
        <Form.Item { ...buttonItemLayout }>
          <button type="button" className="btn btn-block btn-primary" onClick={ this.handleSubmit.bind(this) }>
            { this.props.t('ADMIN_DASHBOARD_FILTERS_APPLY') }
          </button>
        </Form.Item>
      </Form>
    )
  }
}

function mapStateToProps(state) {

  return {
    polylineStyle: state.settings.polylineStyle,
    clustersEnabled: state.settings.clustersEnabled,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    setPolylineStyle: style => dispatch(setPolylineStyle(style)),
    setClustersEnabled: enabled => dispatch(setClustersEnabled(enabled)),
    closeSettings: () => dispatch(closeSettings())
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(SettingsModalContent))
