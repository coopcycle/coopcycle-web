import { Table } from 'antd'
import React, { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'


export default () => {

  const { t } = useTranslation()

  const [isLoading, setIsLoading] = useState(true)
  const setWarehouses = useState([])[1]
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
      key: "name",
    },
    {
      title: t("MAX_VOLUME_UNITS"),
      dataIndex: "volumeUnits",
      key: "volumeUnits",
    },
    {
      title: t("MAX_WEIGHT"),
      dataIndex: "maxWeight",
      key: "maxWeight",
    },
    {
      title: t("COLOR"),
      dataIndex: "color",
      key: "color",
    },
    {
      title: t("IS_ELECTRIC"),
      dataIndex: "isElectric",
      key: "isElectric",
    },
    {
      title: t("ELETRIC_RANGE"),
      dataIndex: "electricRange",
      key: "electricRange",
    },
    {
      title: t("WAREHOUSE"),
      dataIndex: "warehouse",
      key: "warehouse",
    },
  ]

  const trailerColumns = [
    {
      title: t("NAME"),
      dataIndex: "name",
      key: "name",
    },
    {
      title: t("MAX_VOLUME_UNITS"),
      dataIndex: "maxVolumeUnits",
      key: "maxVolumeUnits",
    },
    {
      title: t("MAX_WEIGHT"),
      dataIndex: "maxWeight",
      key: "maxWeight",
    },
    {
      title: t("COLOR"),
      dataIndex: "color",
      key: "color",
    },
    {
      title: t("IS_ELECTRIC"),
      dataIndex: "isElectric",
      key: "isElectric",
    },
    {
      title: t("ELETRIC_RANGE"),
      dataIndex: "electricRange",
      key: "electricRange",
    },
    {
      title: t("COMPATIBLE_VEHICLES"),
      dataIndex: "compatibleVehicles",
      key: "compatibleVehicles",
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