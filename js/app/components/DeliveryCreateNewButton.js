import React, { useState } from 'react'
import { Button, Modal, Select } from 'antd'
import { PlusOutlined } from '@ant-design/icons'
import { useTranslation } from 'react-i18next'

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
          color="green"
          variant="solid"
          icon={<PlusOutlined />}
          onClick={showModal}>
          {t('CREATE_NEW_ORDER')}
        </Button>
      )}

      <Modal
        title={t('CREATE_NEW_ORDER')}
        open={isModalVisible}
        onCancel={handleCancel}
        footer={null}>
        <Select
          style={{ width: '100%' }}
          placeholder={t('ADMIN_DASHBOARD_CHOOSE_STORE_LABEL')}
          onChange={handleStoreChange}
          value={selectedStore}
          showSearch
          filterOption={(input, option) =>
            (option?.label ?? '').toLowerCase().startsWith(input.toLowerCase())
          }
          options={stores.map(store => ({
            key: store.id,
            value: window.Routing.generate(routes.store_new, {
              id: store.id,
            }),
            label: store.name,
          }))}
        />
      </Modal>
    </>
  )
}
