import React, { useState } from 'react'
import { Button, Modal, Select } from 'antd'
import { PlusOutlined } from '@ant-design/icons'
import { useTranslation } from 'react-i18next'

const { Option } = Select

export default function DeliveryCreateNewButton({
  stores,
  routes,
  buttonComponent,
}) {
  const { t } = useTranslation()
  const [isModalVisible, setIsModalVisible] = useState(false)
  const [selectedStore, setSelectedStore] = useState(null)

  const showModal = () => {
    setIsModalVisible(true)
  }

  const handleCancel = () => {
    setIsModalVisible(false)
    setSelectedStore(null)
  }

  const handleStoreChange = value => {
    setSelectedStore(value)
    window.location.href = value
  }

  if (!stores || !routes) {
    return null
  }

  return (
    <>
      {buttonComponent ? (
        React.cloneElement(buttonComponent, {
          onClick: showModal,
        })
      ) : (
        <Button
          className="btn-success-color"
          type="primary"
          icon={<PlusOutlined />}
          onClick={showModal}>
          {t('CREATE_NEW_DELIVERY')}
        </Button>
      )}

      <Modal
        title={t('CREATE_NEW_DELIVERY')}
        open={isModalVisible}
        onCancel={handleCancel}
        footer={null}>
        <Select
          style={{ width: '100%' }}
          placeholder={t('ADMIN_DASHBOARD_CHOOSE_STORE_LABEL')}
          onChange={handleStoreChange}
          value={selectedStore}>
          {stores.map(store => (
            <Option
              key={store.id}
              value={window.Routing.generate(routes.store_new, {
                id: store.id,
              })}>
              {store.name}
            </Option>
          ))}
        </Select>
      </Modal>
    </>
  )
}
