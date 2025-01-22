import React, { useEffect, useState } from 'react'
import { Modal, Card, Descriptions, Col, Row, Tooltip, Badge } from 'antd'
import _ from 'lodash'
import { useTranslation } from 'react-i18next'

const baseURL = location.protocol + '//' + location.host

function _generateLabelURL(barcode, token) {
  return window.Routing.generate('task_label_pdf') + '?code=' + barcode + '&token=' + token
}

function _shouldOpenModal(items) {
  if (!items || !Array.isArray(items)) {
    return false
  }

  const totalItems = items.reduce((acc, item) => {
    if (!item?.barcodes?.packages || !Array.isArray(item.barcodes.packages)) {
      return acc
    }
    return acc + 1 + item.barcodes.packages.length
  }, 0)

  return totalItems !== 1
}

function GenericBarcode({ barcode: [barcode, token] }) {
  const { t } = useTranslation()
  return (
    <Card
      title="Task"
      size="small"
      headStyle={{ backgroundColor: '#f0f2f5' }}
      extra={
        <Tooltip title={t('GENERIC_BARCODE_HELP')}>
          <i className="fa fa-question-circle" aria-hidden="true"></i>
        </Tooltip>
      }
      actions={[
        <a
          key="generic_barcode_view"
          href={_generateLabelURL(barcode, token)}
          target="_blank"
          rel="noreferrer">
          View
        </a>,
      ]}>
      <p>Tracking: {barcode}</p>
    </Card>
  )
}

function PackageBarcode({ barcode: [barcode, token], extra, index }) {
  return (
    <Card
      title={`Package #${index}`}
      size="small"
      extra={extra}
      actions={[
        <a
          key={`package_barcode_${index}_view`}
          href={_generateLabelURL(barcode, token)}
          target="_blank"
          rel="noreferrer">
          View
        </a>,
      ]}>
      <p>Tracking: {barcode}</p>
    </Card>
  )
}

function TaskBarcode({ index, task }) {
  const packages = _(task.barcodes.packages).reduce((acc, p) => {
    acc = acc.concat(p.barcodes.map(b => [p, b]))
    return acc
  }, [])
  return (
    <>
      {index > 1 && <hr />}
      <Descriptions
        bordered={true}
        className="mb-3"
        title={`Drop-off #${index}`}
        layout="vertical">
        <Descriptions.Item label="Recipient">
          {task.address.contactName}
        </Descriptions.Item>
        <Descriptions.Item label="Address">
          {task.address.streetAddress}
        </Descriptions.Item>
      </Descriptions>
      <Row gutter={[16, 16]}>
        <Col span={8}>
          <GenericBarcode barcode={task.barcodes.task} />
        </Col>
        {packages.map(([{ name, color, short_code }, barcode], index) => (
          <Col span={8} key={`package-${index}`}>
            <PackageBarcode
              extra={<Badge color={color} text={`[${short_code}] ${name}`} />}
              barcode={barcode}
              index={index + 1}
            />
          </Col>
        ))}
      </Row>
    </>
  )
}

export default function (props) {

  const [_items, setItems] = useState([]);

  const { showLabel, deliveryId } = {
    showLabel: 'Show barcodes',
    deliveryId: null, 
    ...props,
  }

  const httpClient = new window._auth.httpClient()

  const getDeliveryTasks = async () => {
    const { response, error } = await httpClient.get(`${baseURL}/api/deliveries/${deliveryId}?groups=barcode,address,delivery`)

    if (error) {
      return
    }

    if (response) {
      return response.tasks
    } 
  }
  
  useEffect(() => {
    getDeliveryTasks().then(items => {
      const _items = items.filter(i => i.type !== 'PICKUP')
      setItems(_items)
    })
  },[])

  const shouldOpenModal = _shouldOpenModal(_items)
  const [isOpen, setIsOpen] = useState(false)

  const a5Width = Math.round((148 * 96) / 25.4)
  const a5Height = Math.round((210 * 96) / 25.4)

  const features = [
    `width=${a5Width}`,
    `height=${a5Height}`,
    'resizable=yes',
    'scrollbars=yes',
    'status=yes',
  ].join(',')

  return (
    <>
      <a
        onClick={() => {
          if (shouldOpenModal) {
            setIsOpen(true)
          } else {
            window.open(
              _generateLabelURL(_items[0].barcodes.task[0], _items[0].barcodes.task[1]),
              '_blank',
              features,
            )
          }
        }}>
        {showLabel}
      </a>
      <Modal
        title="Barcodes"
        open={isOpen}
        onCancel={() => setIsOpen(false)}
        footer={null}
        width="980px">
        {_items.map((item, index) => (
          <TaskBarcode key={index} index={index + 1} task={item} />
        ))}
      </Modal>
    </>
  )
}
