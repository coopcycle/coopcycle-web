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

const Color = ({color}) => {
  return (<span style={{width: '20px', height: '20px', borderRadius: '40px', backgroundColor: color, display: 'inline-block'}}></span>)
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
      title: t("ADMIN_VEHICLE_NAME_LABEL"),
      dataIndex: "name",
    },
    {
      title: t("ADMIN_VEHICLE_COLOR_LABEL"),
      dataIndex: "color",
      align: "center",
      render: (color) => <Color color={color} />
    },
    {
      title: t("ADMIN_VEHICLE_MAX_WEIGHT_LABEL"),
      dataIndex: "maxWeight",
      align: "center"
    },
    {
      title: t("ADMIN_VEHICLE_MAX_VOLUME_UNITS_LABEL"),
      dataIndex: "volumeUnits",
      align: "center"
    },
    {
      title: t("ADMIN_VEHICLE_IS_ELECTRIC_LABEL"),
      dataIndex: "isElectric",
      align: "center",
      render: (isElectric) => <IsElectric isElectric={isElectric} />
    },
    {
      title: t("ADMIN_VEHICLE_ELECTRIC_RANGE_LABEL"),
      dataIndex: "electricRange",
      align: "center"
    },
    {
      title: t("ADMIN_VEHICLE_WAREHOUSE_LABEL"),
      dataIndex: ["warehouse", "name"],
      align: "center"
    },
  ]

  const trailerColumns = [
    {
      title: t("ADMIN_VEHICLE_NAME_LABEL"),
      dataIndex: "name",
    },
    {
      title: t("ADMIN_VEHICLE_COLOR_LABEL"),
      dataIndex: "color",
      render: (color) => <Color color={color} />,
      align: "center"
    },
    {
      title: t("ADMIN_VEHICLE_MAX_WEIGHT_LABEL"),
      dataIndex: "maxWeight",
      align: "center"
    },
    {
      title: t("ADMIN_VEHICLE_MAX_VOLUME_UNITS_LABEL"),
      dataIndex: "maxVolumeUnits",
      align: "center"
    },
    {
      title: t("ADMIN_VEHICLE_IS_ELECTRIC_LABEL"),
      dataIndex: "isElectric",
      render: (isElectric) => <IsElectric isElectric={isElectric} />,
      align: "center"
    },
    {
      title: t("ADMIN_VEHICLE_ELECTRIC_RANGE_LABEL"),
      dataIndex: "electricRange",
      align: "center"
    },
    {
      title: t("ADMIN_VEHICLE_COMPATIBLE_VEHICLES_LABEL"),
      dataIndex: "compatibleVehicles",
      render: (compatibleVehicles) => <CompatibleVehicles compatibleVehicles={compatibleVehicles} />,
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
          { isLoading ?
            <span className="loader loader--dark"></span> :
            null
          }
          { !isLoading && warehouses.length === 0 ?
            <span>
              { t('ADMIN_VEHICLE_NO_WAREHOUSE') }
              <a
                className="btn btn-primary"
                href={ window.Routing.generate('admin_warehouses') }>
                  { t('ADMIN_VEHICLE_TO_WAREHOUSE_PAGE') }
              </a>
            </span> :
            <>
              <div className="pull-right mb-2">
                  <a onClick={() => setIsVehicleModalOpen(true)} className="btn btn-success">
                    <i className="fa fa-plus"></i> { t('ADD_BUTTON') }
                  </a>
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
            </>
          }
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
                <i className="fa fa-plus"></i> { t('ADD_BUTTON') }
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