import React from 'react'
import moment from 'moment'
import { withTranslation } from 'react-i18next'
import { Alert, Button, List, Flex, Typography } from 'antd'
import { CloseOutlined } from '@ant-design/icons';

moment.locale($('html').attr('lang'))

class NotificationList extends React.Component {

  constructor() {
    super()

    this.state = {
      loading: false,
    }

    this.onDeleteAll = this.onDeleteAll.bind(this)
  }

  onDeleteAll() {
    this.setState({loading: true})
    this.props.onDeleteAll()
      .then(() => {
        this.setState({loading: false})
      })
  }

  render() {

    const { notifications, count, onSeeAll, onRemove, onDeleteAll } = this.props

    if (notifications.length === 0) {
      return (
        <Alert type="warning" message={ this.props.t('NOTIFICATIONS_EMPTY') } />
      )
    }

    return (
      <Flex vertical gap="middle">
        <List
          style={{ height: '60vh', overflow: 'auto' }}
          dataSource={notifications}
          renderItem={(notification) => (
            <List.Item>
              <Flex style={{ width: '100%' }} align="center" justify="space-between">
                <span>
                  { notification.message }
                  <br />
                  <small>{ moment.unix(notification.timestamp).fromNow() }</small>
                </span>
                <Button
                  icon={<CloseOutlined />}
                  type="link"
                  onClick={() => onRemove(notification)}
                  disabled={ this.state.loading } />
              </Flex>
            </List.Item>
          )}>
        </List>
        <Flex justify="space-between">
          <Button disabled={ this.state.loading } color="primary" variant="outlined" onClick={ onSeeAll }>
            { this.props.t('SEE_ALL') } ({count})
          </Button>
          <Button disabled={ this.state.loading } color="danger" variant="outlined" onClick={ onDeleteAll } loading={ this.state.loading }>
            { this.props.t('DELETE_ALL') } ({count})
          </Button>
        </Flex>
      </Flex>
    )
  }
}

export default withTranslation(['common'], { withRef: true })(NotificationList)
