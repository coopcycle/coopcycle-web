import React, { useEffect, useState } from 'react'
import Modal from 'react-modal'

import { useTranslation } from 'react-i18next'
import WarehouseForm from './WarehouseForm'
import { Table } from 'antd'

export default () => {

  const { t } = useTranslation()


  const [isModalOpen, setModalOpen] = useState(false)
  const [warehouses, setWarehouses] = useState([])
  const [isLoading, setIsLoading] = useState(true)

  const httpClient = new window._auth.httpClient()

  useEffect(() => {
    httpClient.get(window.Routing.generate("api_warehouses_get_collection")).then(({ response }) => {
      setWarehouses(response["hydra:member"])
      setIsLoading(false)
    })
  }, [])

  const columns = [
    {
      title: t("NAME"),
      dataIndex: "name",
      key: "name",
    },
    {
      title: t("ADDRESS"),
      dataIndex: ["address", "streetAddress"],
      key: "address.streetAddress",
    },
  ]

  const onSubmit = async (values) => {
    const url = window.Routing.generate("api_warehouses_post_collection")

    const { error } = await httpClient.post(url, values);

    if (error)
    {
      alert(t('ERROR'))
      return;
    } else {
      window.location.reload()
    }
  }
  const initialValues = {
    name: '',
    address: {}
  }

  return (
    <div>
      <div className="row pull-right mb-2">
        <div className="col-md-12">
          <a onClick={() => setModalOpen(true)} className="btn btn-success">
              <i className="fa fa-plus"></i> { t('ADD') }
          </a>
        </div>
      </div>
      <div className="row">
        <div className="col-md-12">
          <Table
            columns={columns}
            loading={isLoading}
            dataSource={warehouses}
            rowKey="@id"
          />
        </div>
      </div>
      <Modal
        isOpen={isModalOpen}
        appElement={document.getElementById('warehouse')}
        className="ReactModal__Content--no-default" // disable additional inline style from react-modal
      >
        <WarehouseForm initialValues={initialValues} onSubmit={onSubmit}/>
      </Modal>
    </div>

  )
}