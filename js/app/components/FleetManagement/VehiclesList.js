import React, { useEffect, useState } from 'react'

import { Table } from 'antd'
import Modal from 'react-modal'
import { useTranslation } from 'react-i18next'
import VehicleForm from './VehicleForm'
import TrailerForm from './TrailerForm'

const CompatibleVehicles = ({compatibleVehicles}) => {
  return (
    <ul>
      {
        compatibleVehicles.map((vehicleCompat) => {
          return <li key={vehicleCompat['@id']}>{ vehicleCompat.vehicle.name }</li>
        })
      }
    </ul>
  )
}

const IsElectric = ({isElectric}) => {
  return <>
    { isElectric ? <i className='fa fa-check'></i> : <i className='fa fa-close'></i> }
  </>
}

export default () => {

  const { t } = useTranslation()

  const [isLoading, setIsLoading] = useState(true)
  const [isVehicleModalOpen, setIsVehicleModalOpen] = useState(false)
  const [isTrailerModalOpen, setIsTrailerModalOpen] = useState(false)
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
      render: (isElectric) => <IsElectric isElectric={isElectric} />
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
      render: (isElectric) => <IsElectric isElectric={isElectric} />
    },
    {
      title: t("ELETRIC_RANGE"),
      dataIndex: "electricRange",
    },
    {
      title: t("COMPATIBLE_VEHICLES"),
      dataIndex: "compatibleVehicles",
      render: (compatibleVehicles) => <CompatibleVehicles compatibleVehicles={compatibleVehicles} />
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

  const onSubmitTrailer = async (values) => {
    const url = window.Routing.generate("api_trailers_post_collection")

    const { error } = await httpClient.post(url, values);

    if (error)
    {
      alert(t('ERROR'))
      return;
    } else {
      setIsLoading(true)
      const { response } = await httpClient.get(window.Routing.generate("api_trailers_get_collection"))
      setTrailers(response["hydra:member"])
      setIsLoading(false)
    }
  }

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
            appElement={document.getElementById('vehicles-admin-app')}
            shouldCloseOnOverlayClick={true}
            shouldCloseOnEsc={true}
            className="ReactModal__Content--no-default" // disable additional inline style from react-modal
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
          <div className="row pull-right mb-2">
            { isLoading ?
              (<span className="loader loader--dark"></span>) :
              <a onClick={() => setIsTrailerModalOpen(true)} className="btn btn-success">
                <i className="fa fa-plus"></i> { t('ADD') }
              </a>
            }
          </div>
          <Table
            columns={trailerColumns}
            loading={isLoading}
            dataSource={trailers}
            rowKey="@id"
          />
          <Modal
            isOpen={isTrailerModalOpen}
            appElement={document.getElementById('vehicles-admin-app')}
            shouldCloseOnOverlayClick={true}
            shouldCloseOnEsc={true}
            className="ReactModal__Content--no-default" // disable additional inline style from react-modal
          >
            <TrailerForm
              initialValues={{}}
              onSubmit={onSubmitTrailer}
              closeModal={() => setIsTrailerModalOpen(false)}
              vehicles={vehicles}
            />
          </Modal>
        </div>
      </div>
    </div>
  )
}