import React, { useEffect, useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'
import moment from 'moment'
import {
  Layout,
  DatePicker,
  Button,
  Dropdown,
  Space,
  Drawer,
  Tag,
  Tooltip,
  Grid,
} from 'antd'
import {
  MenuOutlined,
  LeftOutlined,
  RightOutlined,
  SettingOutlined,
  PlusOutlined,
  EllipsisOutlined,
  ClockCircleOutlined,
  ExclamationCircleOutlined,
  SyncOutlined,
} from '@ant-design/icons'
import _ from 'lodash'

import {
  openFiltersModal,
  resetFilters,
  openSettings,
  openImportModal,
  openExportModal,
  openNewTaskModal,
  openNewRecurrenceRuleModal,
} from '../redux/actions'
import { selectSelectedDate } from '../../coopcycle-frontend-js/logistics/redux'
import DeliveryCreateNewButton from '../../components/DeliveryCreateNewButton'
import { selectStores } from '../redux/selectors'
import SearchInput from './SearchInput'

const { Header } = Layout
const { useBreakpoint } = Grid

const DateNavigation = () => {
  const selectedDate = useSelector(selectSelectedDate)
  const nav = useSelector(state => state.config.nav)

  const prevUrl = window.Routing.generate('admin_dashboard_fullscreen', {
    date: moment(selectedDate).subtract(1, 'days').format('YYYY-MM-DD'),
    nav: nav,
  })
  const nextUrl = window.Routing.generate('admin_dashboard_fullscreen', {
    date: moment(selectedDate).add(1, 'days').format('YYYY-MM-DD'),
    nav: nav,
  })

  const handleDateChange = date => {
    if (date) {
      queueMicrotask(() => {
        window.location.href = window.Routing.generate(
          'admin_dashboard_fullscreen',
          {
            date: date.format('YYYY-MM-DD'),
            nav: nav,
          },
        )
      })
    }
  }

  return (
    <Space.Compact>
      <Button
        icon={<LeftOutlined />}
        onClick={() => (window.location.href = prevUrl)}
      />
      <DatePicker
        format="ll"
        value={moment(selectedDate)}
        onChange={handleDateChange}
        style={{ width: 140 }}
      />
      <Button
        icon={<RightOutlined />}
        onClick={() => (window.location.href = nextUrl)}
      />
    </Space.Compact>
  )
}

const ExportButton = ({ onClick }) => {
  const { t } = useTranslation()
  const dispatch = useDispatch()

  const exportEnabled = useSelector(
    state => state.config.exportEnabled === 'on',
  )

  if (!exportEnabled) return null

  return (
    <Button
      type="text"
      icon={<i className="fa fa-download mr-2" aria-hidden="true" />}
      onClick={() => {
        dispatch(openExportModal())
        if (onClick) {
          onClick()
        }
      }}>
      {t('ADMIN_DASHBOARD_NAV_EXPORT')}
    </Button>
  )
}

const ImportButton = ({ onClick }) => {
  const { t } = useTranslation()
  const dispatch = useDispatch()

  return (
    <Button
      type="text"
      icon={<i className="fa fa-upload mr-2" aria-hidden="true" />}
      onClick={() => {
        dispatch(openImportModal())
        if (onClick) {
          onClick()
        }
      }}>
      {t('ADMIN_DASHBOARD_NAV_IMPORT')}
    </Button>
  )
}

const ImportItem = ({ token, message }) => {
  return (
    <Tooltip title={message}>
      {message ? (
        <Tag icon={<ExclamationCircleOutlined />} color="warning">
          {token}
        </Tag>
      ) : (
        <Tag icon={<SyncOutlined spin />} color="processing">
          {token}
        </Tag>
      )}
    </Tooltip>
  )
}

const ImportsStatus = ({ imports }) => {
  if (_.size(imports) === 0) return null

  return (
    <Space>
      {_.map(imports, (message, token) => (
        <ImportItem key={token} token={token} message={message} />
      ))}
    </Space>
  )
}

const Filters = ({ withOpenLabel = false, withClearLabel = false }) => {
  const { t } = useTranslation()
  const dispatch = useDispatch()

  const isDefaultFilters = useSelector(state => state.settings.isDefaultFilters)
  const isActive = !isDefaultFilters

  return (
    <Space>
      <Tooltip title={t('ADMIN_DASHBOARD_NAV_FILTERS')}>
        <Button
          type={isActive ? 'primary' : 'text'}
          icon={<i className="fa fa-sliders mr-2" aria-hidden="true" />}
          onClick={() => dispatch(openFiltersModal())}>
          {withOpenLabel ? t('ADMIN_DASHBOARD_NAV_FILTERS') : null}
        </Button>
      </Tooltip>
      {!isDefaultFilters && (
        <Tooltip title={t('ADMIN_DASHBOARD_NAV_FILTERS_CLEAR')}>
          <Button
            type="text"
            icon={<i className="fa fa-times-circle mr-2" aria-hidden="true" />}
            onClick={() => dispatch(resetFilters())}>
            {withClearLabel ? t('ADMIN_DASHBOARD_NAV_FILTERS_CLEAR') : null}
          </Button>
        </Tooltip>
      )}
    </Space>
  )
}

const CreateNewOrderButton = () => {
  const { t } = useTranslation()
  const stores = useSelector(selectStores)

  return (
    <DeliveryCreateNewButton
      stores={stores}
      routes={{
        store_new: 'admin_store_delivery_new',
      }}
      buttonComponent={
        <Button type="text" icon={<PlusOutlined />}>
          {t('CREATE_NEW_ORDER')}
        </Button>
      }
    />
  )
}

const LegacyCreateNewButtons = () => {
  const { t } = useTranslation()
  const dispatch = useDispatch()

  const menuItems = [
    {
      key: 'create-task',
      label: t('ADMIN_DASHBOARD_CREATE_TASK'),
      icon: <PlusOutlined />,
      onClick: () => dispatch(openNewTaskModal()),
    },
    {
      key: 'create-recurrence-rule',
      label: t('ADMIN_DASHBOARD_CREATE_RECURRENCE_RULE'),
      icon: <ClockCircleOutlined />,
      onClick: () => dispatch(openNewRecurrenceRuleModal()),
    },
  ]

  return (
    <Tooltip title={t('MORE')}>
      <Dropdown
        menu={{ items: menuItems }}
        trigger={['click']}
        placement="bottomRight">
        <Button
          data-testid="more-button"
          type="text"
          icon={<EllipsisOutlined />}
        />
      </Dropdown>
    </Tooltip>
  )
}

const SettingsButton = ({ withLabel = false, onClick }) => {
  const { t } = useTranslation()
  const dispatch = useDispatch()

  return (
    <Tooltip title={t('ADMIN_DASHBOARD_NAV_SETTINGS')}>
      <Button
        type="text"
        icon={<SettingOutlined />}
        onClick={() => {
          dispatch(openSettings())
          if (onClick) {
            onClick()
          }
        }}>
        {withLabel ? t('ADMIN_DASHBOARD_NAV_SETTINGS') : null}
      </Button>
    </Tooltip>
  )
}

const LiveUpdatesStatus = () => {
  return (
    <div>
      <span className="pulse" id="pulse"></span>
    </div>
  )
}

const OverflowButton = ({ setIsOverflowMenuVisible }) => {
  const { t } = useTranslation()

  return (
    <Tooltip title={t('MORE')}>
      <Button
        type="text"
        icon={<MenuOutlined />}
        onClick={() => setIsOverflowMenuVisible(true)}
      />
    </Tooltip>
  )
}

const OverflowMenu = ({
  isOverflowMenuVisible,
  setIsOverflowMenuVisible,
  children,
}) => {
  const { t } = useTranslation()
  return (
    <Drawer
      title={t('MORE')}
      placement="right"
      onClose={() => setIsOverflowMenuVisible(false)}
      open={isOverflowMenuVisible}
      width={300}>
      <div
        style={{
          display: 'flex',
          flexDirection: 'column',
          gap: '8px',
        }}>
        {children}
      </div>
    </Drawer>
  )
}

const Bar = ({ children }) => {
  return (
    <div
      style={{
        height: '50px',
        padding: '0 16px',
        background: '#f8f8f8',
        borderBottom: '1px solid #e7e7e7',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
      }}>
      {children}
    </div>
  )
}

const NavbarAntd = () => {
  const { t } = useTranslation()
  const dispatch = useDispatch()
  const screens = useBreakpoint()
  const [isOverflowMenuVisible, setIsOverflowMenuVisible] = useState(false)

  const imports = useSelector(state => state.imports)

  // Effect for handling imports popover (equivalent to componentDidUpdate)
  useEffect(() => {
    _.map(imports, (message, token) => {
      const $target = $(`#task-import-${token}`)
      if (message && !$target.data('bs.popover')) {
        $target.popover({
          html: true,
          container: 'body',
          placement: 'bottom',
          content: message,
        })
      }
    })
  }, [imports])

  if (screens.xl) {
    return (
      <Bar>
        <Space>
          <DateNavigation />

          <Space>
            <ExportButton />
            <ImportButton />
            <ImportsStatus imports={imports} />
          </Space>

          <SearchInput />

          <Filters withOpenLabel withClearLabel={screens.xxl} />
        </Space>

        <Space>
          <CreateNewOrderButton />
          <LegacyCreateNewButtons />
        </Space>

        <Space>
          <SettingsButton withLabel={screens.xxl} />
          <LiveUpdatesStatus />
        </Space>
      </Bar>
    )
  } else if (screens.md) {
    return (
      <>
        <Bar>
          <Space>
            <DateNavigation />

            <ImportsStatus imports={imports} />

            <SearchInput />

            <Filters />
          </Space>

          <Space>
            <CreateNewOrderButton />
            <LegacyCreateNewButtons />
          </Space>

          <Space>
            <LiveUpdatesStatus />
            <OverflowButton
              setIsOverflowMenuVisible={setIsOverflowMenuVisible}
            />
          </Space>
        </Bar>
        <OverflowMenu
          isOverflowMenuVisible={isOverflowMenuVisible}
          setIsOverflowMenuVisible={setIsOverflowMenuVisible}>
          <>
            <ExportButton onClick={() => setIsOverflowMenuVisible(false)} />
            <ImportButton onClick={() => setIsOverflowMenuVisible(false)} />
            <SettingsButton
              withLabel
              onClick={() => setIsOverflowMenuVisible(false)}
            />
          </>
        </OverflowMenu>
      </>
    )
  }

  return (
    <>
      <Bar>
        <Space>
          <DateNavigation />

          <ImportsStatus imports={imports} />

          <SearchInput />

          <Filters />
        </Space>

        <Space>
          <LiveUpdatesStatus />
          <OverflowButton setIsOverflowMenuVisible={setIsOverflowMenuVisible} />
        </Space>
      </Bar>
      <OverflowMenu
        isOverflowMenuVisible={isOverflowMenuVisible}
        setIsOverflowMenuVisible={setIsOverflowMenuVisible}>
        <>
          <ExportButton onClick={() => setIsOverflowMenuVisible(false)} />
          <ImportButton onClick={() => setIsOverflowMenuVisible(false)} />
          <CreateNewOrderButton />
          <Button
            type="text"
            block
            icon={<PlusOutlined />}
            onClick={() => {
              setIsOverflowMenuVisible(false)
              dispatch(openNewTaskModal())
            }}>
            {t('ADMIN_DASHBOARD_CREATE_TASK')}
          </Button>
          <Button
            type="text"
            block
            icon={<ClockCircleOutlined />}
            onClick={() => {
              setIsOverflowMenuVisible(false)
              dispatch(openNewRecurrenceRuleModal())
            }}>
            {t('ADMIN_DASHBOARD_CREATE_RECURRENCE_RULE')}
          </Button>
          <SettingsButton
            withLabel
            onClick={() => setIsOverflowMenuVisible(false)}
          />
        </>
      </OverflowMenu>
    </>
  )
}

export default NavbarAntd
