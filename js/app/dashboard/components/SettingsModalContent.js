import React, { useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'
import { Form, Radio } from 'antd';

import {
  closeSettings, setGeneralSettings,

} from '../redux/actions'
import { selectSettings } from '../redux/selectors';

const formItemLayout = {
  labelCol: { span: 14 },
  wrapperCol: { span: 10 },
}
const buttonItemLayout = {
  wrapperCol: { span: 24 },
}

export default () => {

  const { polylineStyle, clustersEnabled, useAvatarColors, showWeightAndVolumeUnit, showDistanceAndTime } = useSelector(selectSettings)
  const [polylineStyleValue, setPolylineStyleLocal] = useState(polylineStyle)
  const [clustersEnabledValue, setClustersEnabledLocal] = useState(clustersEnabled)
  const [useAvatarColorsValue, setUseAvatarColorsLocal] = useState(useAvatarColors)
  const [showWeightAndVolumeUnitValue, setShowWeightAndVolumeUnitLocal] = useState(showWeightAndVolumeUnit)
  const [showDistanceAndTimeValue, setshowDistanceAndTimeLocal] = useState(showDistanceAndTime)
  const dispatch = useDispatch()

  const { t } = useTranslation()

  const handleSubmit = () => {
    dispatch(setGeneralSettings({
      polylineStyle: polylineStyleValue,
      clustersEnabled: clustersEnabledValue,
      useAvatarColors: useAvatarColorsValue,
      showWeightAndVolumeUnit: showWeightAndVolumeUnitValue,
      showDistanceAndTime: showDistanceAndTimeValue
    }))
    dispatch(closeSettings())
  }

  return (
    <Form layout="horizontal" colon={ false } labelWrap>
      <Form.Item label={ t('ADMIN_DASHBOARD_SETTINGS_POLYLINE') } { ...formItemLayout }>
        <Radio.Group defaultValue={ polylineStyle }
          onChange={ (e) => setPolylineStyleLocal(e.target.value) }>
          <Radio.Button value="normal">Normal</Radio.Button>
          <Radio.Button value="as_the_crow_flies">
            { t('ADMIN_DASHBOARD_SETTINGS_POLYLINE_AS_THE_CROW_FLIES') }
          </Radio.Button>
        </Radio.Group>
      </Form.Item>
      <Form.Item label={ t('ADMIN_DASHBOARD_SETTINGS_CLUSTERS_ENABLED') } { ...formItemLayout }>
        <Radio.Group defaultValue={ clustersEnabled }
          onChange={ (e) => setClustersEnabledLocal(e.target.value) }>
          <Radio.Button value={ true }>{ t('ADMIN_DASHBOARD_SETTINGS_YES') }</Radio.Button>
          <Radio.Button value={ false }>{ t('ADMIN_DASHBOARD_SETTINGS_NO') }</Radio.Button>
        </Radio.Group>
      </Form.Item>
      <Form.Item label={ t('ADMIN_DASHBOARD_SETTINGS_SHOW_DISTANCE_AND_TIME') } { ...formItemLayout }>
        <Radio.Group defaultValue={ showDistanceAndTimeValue }
          onChange={ (e) => setshowDistanceAndTimeLocal(e.target.value) }>
          <Radio.Button value={ true }>{ t('ADMIN_DASHBOARD_SETTINGS_YES') }</Radio.Button>
          <Radio.Button value={ false }>{ t('ADMIN_DASHBOARD_SETTINGS_NO') }</Radio.Button>
        </Radio.Group>
      </Form.Item>
      <Form.Item label={ t('ADMIN_DASHBOARD_SETTINGS_SHOW_WEIGHT') } { ...formItemLayout }>
        <Radio.Group defaultValue={ showWeightAndVolumeUnitValue }
          onChange={ (e) => setShowWeightAndVolumeUnitLocal(e.target.value) }>
          <Radio.Button value={ true }>{ t('ADMIN_DASHBOARD_SETTINGS_YES') }</Radio.Button>
          <Radio.Button value={ false }>{ t('ADMIN_DASHBOARD_SETTINGS_NO') }</Radio.Button>
        </Radio.Group>
      </Form.Item>
      <Form.Item label={ t('ADMIN_DASHBOARD_SETTINGS_AVATAR_COLORS_ENABLED') } { ...formItemLayout }>
        <Radio.Group defaultValue={ useAvatarColors }
          onChange={ (e) => setUseAvatarColorsLocal(e.target.value) }>
          <Radio.Button value={ true }>{ t('ADMIN_DASHBOARD_SETTINGS_YES') }</Radio.Button>
          <Radio.Button value={ false }>{ t('ADMIN_DASHBOARD_SETTINGS_NO') }</Radio.Button>
        </Radio.Group>
      </Form.Item>
      <Form.Item { ...buttonItemLayout }>
        <button type="button" className="btn btn-block btn-primary" onClick={ () => handleSubmit() }>
          { t('ADMIN_DASHBOARD_FILTERS_APPLY') }
        </button>
      </Form.Item>
    </Form>
  )
}
