import React, { useEffect, useState } from 'react'

import { Table } from 'antd'
import Modal from 'react-modal'
import { useTranslation } from 'react-i18next'
import VehicleForm from './VehicleForm'


export default () => {

  const { t } = useTranslation()

  const [isLoading, setIsLoading] = useState(true)
  const [isVehicleModalOpen, setIsVehicleModalOpen] = useState(false)
  const [warehouses, setWarehouses] = useState([])
  const [trailers, setTrailers] = useState([])
  const [vehicles, setVehicles] = useState([])

  const httpClient = new window._auth.httpClient()


  useEffect(() => {
    Promise.all([
      httpClient.get(window.Routing.generate("api_warehouses_get_collection")),
      httpClient.get(window.Routing.generate("api_trailers_get_collection")),
      httpClient.get(window.Routing.generate("api_vehicles_get_collection")),
    ]).then(values => {
      const [warehouseRes, trailerRes, vehicleRes] = values
      setWarehouses(warehouseRes.response["hydra:member"])
      setTrailers(trailerRes.response["hydra:member"])
      setVehicles(vehicleRes.response["hydra:member"])
      setIsLoading(false)
    })
  }, [])

  const vehicleColumns = [
    {
      title: t("NAME"),
      dataIndex: "name",
    },
    {
      title: t("MAX_VOLUME_UNITS"),
      dataIndex: "volumeUnits",
    },
    {
      title: t("MAX_WEIGHT"),
      dataIndex: "maxWeight",
    },
    {
      title: t("COLOR"),
      dataIndex: "color",
    },
    {
      title: t("IS_ELECTRIC"),
      dataIndex: "isElectric",
      render: (isElectric) => {
        return isElectric ? <i className='fa fa-check'></i> : <i className='fa fa-close'></i>
      }
    },
    {
      title: t("ELECTRIC_RANGE"),
      dataIndex: "electricRange",
    },
    {
      title: t("WAREHOUSE"),
      dataIndex: "warehouse",
    },
  ]

  const onSubmitVehicle = async (values) => {
    const url = window.Routing.generate("api_vehicles_post_collection")

    const { error } = await httpClient.post(url, values);

    if (error)
    {
      alert(t('ERROR'))
      return;
    } else {
      setIsLoading(true)
      const { response } = await httpClient.get(window.Routing.generate("api_vehicles_get_collection"))
      setVehicles(response["hydra:member"])
      setIsLoading(false)
    }
  }

  const trailerColumns = [
    {
      title: t("NAME"),
      dataIndex: "name",
    },
    {
      title: t("MAX_VOLUME_UNITS"),
      dataIndex: "maxVolumeUnits",
    },
    {
      title: t("MAX_WEIGHT"),
      dataIndex: "maxWeight",
    },
    {
      title: t("COLOR"),
      dataIndex: "color",
    },
    {
      title: t("IS_ELECTRIC"),
      dataIndex: "isElectric",
    },
    {
      title: t("ELETRIC_RANGE"),
      dataIndex: "electricRange",
    },
    {
      title: t("COMPATIBLE_VEHICLES"),
      dataIndex: "compatibleVehicles",
    },
  ]

  return (

    <div>
      <ul className="nav nav-tabs" role="tablist">
        <li role="presentation" className="active">
          <a href="#vehicles" aria-controls="vehicles" role="tab" data-toggle="tab">
            { t('VEHICLES') }
          </a>
        </li>
        <li role="presentation">
          <a href="#trailers" aria-controls="trailers" role="tab" data-toggle="tab">
            { t('TRAILERS') }
          </a>
        </li>
      </ul>
      <div className="tab-content">
        <div
          role="tabpanel"
          className="tab-pane active p-3"
          id="vehicles"
        >
          <div className="row pull-right mb-2">
            { isLoading ?
              (<span className="loader loader--dark"></span>) :
              <a onClick={() => setIsVehicleModalOpen(true)} className="btn btn-success">
                <i className="fa fa-plus"></i> { t('ADD') }
              </a>
            }
          </div>
          <Modal
            isOpen={isVehicleModalOpen}
            appElement={document.getElementById('vehicles-admin-app')} className="ReactModal__Content--warehouse-form"
            shouldCloseOnOverlayClick={true}
            shouldCloseOnEsc={true}
          >
            <VehicleForm
              initialValues={{}}
              onSubmit={onSubmitVehicle}
              closeModal={() => setIsVehicleModalOpen(false)}
              warehouses={warehouses}
            />
          </Modal>
          <Table
            columns={vehicleColumns}
            loading={isLoading}
            dataSource={vehicles}
            rowKey="@id"
          />
        </div>
        <div
          role="tabpanel"
          className="tab-pane p-3"
          id="trailers"
        >
          <Table
            columns={trailerColumns}
            loading={isLoading}
            dataSource={trailers}
            rowKey="@id"
          />
        </div>
      </div>
    </div>
  )
}