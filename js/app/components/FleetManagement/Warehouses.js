import React, { useEffect, useState } from 'react'
import Modal from 'react-modal'

import { useTranslation } from 'react-i18next'
import WarehouseForm from './WarehouseForm'
import { Table } from 'antd'
import DeleteIcon from '../DeleteIcon'

export default () => {

  const { t } = useTranslation()

  const [isModalOpen, setModalOpen] = useState(false)
  const [warehouses, setWarehouses] = useState([])
  const [isLoading, setIsLoading] = useState(true)

  const httpClient = new window._auth.httpClient()

  const fetchWarehouses = () => {
    setIsLoading(true)
    httpClient.get(window.Routing.generate("api_warehouses_get_collection")).then(({ response }) => {
      setWarehouses(response["hydra:member"])
      setIsLoading(false)
    })
  }

  useEffect(() => {
    fetchWarehouses()
  }, [])

  const columns = [
    {
      title: t("ADMIN_WAREHOUSE_NAME_LABEL"),
      dataIndex: "name",
      key: "name",
    },
    {
      title: t("ADMIN_WAREHOUSE_ADDRESS_LABEL"),
      dataIndex: ["address", "streetAddress"],
      key: "address.streetAddress",
    },
    {
      key: "action",
      align: "right",
      render: (record) => <DeleteIcon deleteUrl={"api_warehouses_delete_item"}  objectId={record.id} objectName={record.name} afterDeleteFetch={fetchWarehouses} />,
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
      setModalOpen(false)
      fetchWarehouses()
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
              <i className="fa fa-plus"></i> { t('ADD_BUTTON') }
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
        shouldCloseOnOverlayClick={true}
        shouldCloseOnEsc={true}
        style={{content: {overflow: "unset"}}}
      >
        <div className="modal-header">
          <h4 className="modal-title">
            { t('ADMIN_WAREHOUSE_FORM_TITLE') }
            <a className="pull-right" onClick={ () => setModalOpen(false) }><i className="fa fa-close"></i></a>
          </h4>
        </div>
        <WarehouseForm initialValues={initialValues} onSubmit={onSubmit}/>
      </Modal>
    </div>

  )
}