import React, { useEffect, useState } from 'react'

import { Table } from 'antd'
import Modal from 'react-modal'
import { useTranslation } from 'react-i18next'
import VehicleForm from './VehicleForm'
import TrailerForm from './TrailerForm'
import DeleteIcon from '../DeleteIcon'

const Loader = () => {
  return (
    <div className="text-center my-4">
      <span className="loader loader--lg loader--dark"></span>
    </div>
  )
}

const CompatibleVehicles = ({compatibleVehicles, vehicles}) => {
  return (
    <ul>
      {
        compatibleVehicles.map((compatibleVehicleId) => {
          const vehicle = vehicles.find(v => v['@id'] === compatibleVehicleId)
          return <li key={vehicle['@id']}>{ vehicle.name }</li>
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
  const [initialValues, setInitialValues] = useState({})

  const httpClient = new window._auth.httpClient()

  const fetchAll = () => {
    setIsLoading(true)
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
  }

  const openVehicleCreateModal = () => {
    setInitialValues({})
    setIsVehicleModalOpen(true)
  }

  const openTrailerCreateModal = () => {
    setInitialValues({})
    setIsTrailerModalOpen(true)
  }

  useEffect(() => {
    fetchAll()
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
      align: "center",
      render: (maxWeight) => maxWeight / 1000
    },
    {
      title: t("ADMIN_VEHICLE_MAX_VOLUME_UNITS_LABEL"),
      dataIndex: "maxVolumeUnits",
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
    {
      key: "edit",
      align: "right",
      render: (record) => <a className="text-reset" href="#"><span className="fa fa-pencil" onClick={() => {setInitialValues(record); setIsVehicleModalOpen(true)}}></span></a>,
    },
    {
      key: "action",
      align: "right",
      render: (record) => <DeleteIcon deleteUrl={"api_vehicles_delete_item"}  objectId={record.id} objectName={record.name} afterDeleteFetch={fetchAll} />,
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
      align: "center",
      render: (maxWeight) => maxWeight / 1000
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
      render: (compatibleVehicles) => <CompatibleVehicles compatibleVehicles={compatibleVehicles} vehicles={vehicles} />,
    },

    {
      key: "edit",
      align: "right",
      render: (record) => <a className="text-reset" href="#"><span className="fa fa-pencil" onClick={() => {setInitialValues(record); setIsTrailerModalOpen(true)}}></span></a>,
    },
    {
      key: "action",
      align: "right",
      render: (record) => <DeleteIcon deleteUrl={"api_trailers_delete_item"}  objectId={record.id} objectName={record.name} afterDeleteFetch={fetchAll} />,
    },
  ]

  const onSubmitVehicle = async (values) => {
    let url, request

    if (values['@id']) {
      url = window.Routing.generate("api_vehicles_patch_item", {id: values.id})
      request = await httpClient.patch(url, values)
    } else {
      url = window.Routing.generate("api_vehicles_post_collection")
      request = await httpClient.post(url, values)
    }

    const {error} = await request

    if (error)
    {
      alert(t('ERROR'))
      return;
    } else {
      fetchAll()
      setIsVehicleModalOpen(false)
    }
  }

  const onSubmitTrailer = async (values) => {
    let url, request

    if (values['@id']) {
      url = window.Routing.generate("api_trailers_patch_item", {id: values.id})
      request = await httpClient.patch(url, values)
    } else {
      url = window.Routing.generate("api_trailers_post_collection")
      request = await httpClient.post(url, values)
    }

    const res = await request

    if (res.error)
    {
      alert(t('ERROR'))
      return
    }

    if (values['compatibleVehicles']) {
      url = window.Routing.generate("api_trailers_set_vehicles_item", {id: res.response.id})
      request = await httpClient.put(url, {compatibleVehicles: values.compatibleVehicles})
      await request
    }

    fetchAll()
    setIsTrailerModalOpen(false)
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
          className="tab-pane active p-4"
          id="vehicles"
        >
          { isLoading ?
            <Loader /> :
              warehouses.length === 0 ?
                <div className='text-center'>
                  <p className="mb-2">{ t('ADMIN_VEHICLE_NO_WAREHOUSE') }</p>
                  <a
                    className="btn btn-default"
                    href={ window.Routing.generate('admin_warehouses') }>
                      { t('ADMIN_VEHICLE_TO_WAREHOUSE_PAGE') }
                  </a>
                </div> :
                <>
                  <div className="pull-right mb-2">
                      <a onClick={() => openVehicleCreateModal()} className="btn btn-success">
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
                      initialValues={initialValues}
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
          className="tab-pane p-4"
          id="trailers"
        >
          { isLoading ?
            <Loader /> :
              vehicles.length === 0 ?
                <div className='text-center'>
                  <p className="my-2">{ t('ADMIN_VEHICLE_NO_VEHICLE') }</p>
                </div> :
                <>
                  <div className="pull-right mb-2">
                      <a onClick={() => openTrailerCreateModal()} className="btn btn-success">
                        <i className="fa fa-plus"></i> { t('ADD_BUTTON') }
                      </a>
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
                      initialValues={initialValues}
                      onSubmit={onSubmitTrailer}
                      closeModal={() => setIsTrailerModalOpen(false)}
                      vehicles={vehicles}
                    />
                  </Modal>
                </>
          }
        </div>
      </div>
    </div>
  )
}