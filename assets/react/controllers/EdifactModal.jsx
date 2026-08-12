import React, { useState } from 'react'
import { Modal, Descriptions, Table, Typography, Tag, Divider, Empty, Spin, Tabs } from 'antd'
import moment from 'moment'

const baseURL = location.protocol + '//' + location.host

const { Title, Text } = Typography

// Human-readable labels for the party types found in an EDIFACT message.
const NAD_TYPE_LABELS = {
  RECIPIENT: 'Recipient',
  SENDER: 'Sender',
  ORIGIN_SHIPPER: 'Origin shipper',
  PARTICIPANT: 'Participant',
  PARTICIPANT2: 'Participant',
  DELIVERY_AGENT: 'Delivery agent',
  PROVIDER: 'Provider',
  FORWARDER: 'Forwarder',
  CONTRACTOR: 'Contractor',
  COLLEGUE: 'Colleague',
  COLLECT: 'Collect',
}

const DATE_EVENT_LABELS = {
  DEPARTURE_DATE: 'Departure date',
  ESTIMATED_ARRIVAL_DATE: 'Estimated arrival date',
  REQUESTED_DELIVERY_DATE: 'Requested delivery date',
  START_OF_DELIVERY_PERIOD: 'Start of delivery period',
  END_OF_DELIVERY_PERIOD: 'End of delivery period',
  SHIPMENT_DATE: 'Shipment date',
}

function humanize(value, labels) {
  return labels[value] || value
}

function NamesAndAddresses({ namesAndAddresses }) {
  if (!namesAndAddresses || namesAndAddresses.length === 0) {
    return null
  }

  return (
    <>
      <Title level={5} className="mt-3">
        Parties
      </Title>
      {namesAndAddresses.map((nad, index) => (
        <Descriptions
          key={index}
          bordered
          size="small"
          column={1}
          className="mb-3">
          <Descriptions.Item label="Type">
            <Tag>{humanize(nad.type, NAD_TYPE_LABELS)}</Tag>
          </Descriptions.Item>
          {nad.addressLabel && (
            <Descriptions.Item label="Name">
              {nad.addressLabel}
            </Descriptions.Item>
          )}
          {nad.contactName && (
            <Descriptions.Item label="Contact">
              {nad.contactName}
            </Descriptions.Item>
          )}
          {nad.contactSiret && (
            <Descriptions.Item label="SIRET">
              {nad.contactSiret}
            </Descriptions.Item>
          )}
          {nad.address && (
            <Descriptions.Item label="Address">
              {nad.address}
            </Descriptions.Item>
          )}
          {(nad.latitude || nad.longitude) && (
            <Descriptions.Item label="Coordinates">
              {nad.latitude}, {nad.longitude}
            </Descriptions.Item>
          )}
          {nad.communicationMeans && nad.communicationMeans.length > 0 && (
            <Descriptions.Item label="Contact details">
              {nad.communicationMeans.map((c, i) => (
                <div key={i}>
                  <Text type="secondary">{c.type}: </Text>
                  {c.value}
                </div>
              ))}
            </Descriptions.Item>
          )}
        </Descriptions>
      ))}
    </>
  )
}

function DatesTable({ dates }) {
  if (!dates || dates.length === 0) {
    return null
  }

  const columns = [
    {
      title: 'Event',
      dataIndex: 'event',
      key: 'event',
      render: value => humanize(value, DATE_EVENT_LABELS),
    },
    {
      title: 'Date',
      dataIndex: 'date',
      key: 'date',
      render: value => moment(value).format('L LT'),
    },
  ]

  return (
    <>
      <Title level={5} className="mt-3">
        Dates
      </Title>
      <Table
        size="small"
        pagination={false}
        rowKey={(_, index) => index}
        columns={columns}
        dataSource={dates}
        className="mb-3"
      />
    </>
  )
}

function PackagesTable({ packages, measurements }) {
  const hasPackages = packages && packages.length > 0
  const hasMeasurements = measurements && measurements.length > 0

  if (!hasPackages && !hasMeasurements) {
    return null
  }

  return (
    <>
      <Title level={5} className="mt-3">
        Packages &amp; measurements
      </Title>
      {hasPackages && (
        <Table
          size="small"
          pagination={false}
          rowKey={(_, index) => `pkg-${index}`}
          columns={[
            { title: 'Type', dataIndex: 'type', key: 'type' },
            { title: 'Quantity', dataIndex: 'quantity', key: 'quantity' },
          ]}
          dataSource={packages}
          className="mb-3"
        />
      )}
      {hasMeasurements && (
        <Table
          size="small"
          pagination={false}
          rowKey={(_, index) => `mes-${index}`}
          columns={[
            { title: 'Measurement', dataIndex: 'type', key: 'type' },
            { title: 'Quantity', dataIndex: 'quantity', key: 'quantity' },
            { title: 'Unit', dataIndex: 'unit', key: 'unit' },
          ]}
          dataSource={measurements}
          className="mb-3"
        />
      )}
    </>
  )
}

function ParsedMessage({ message }) {
  const { point } = message

  return (
    <div>
      <Descriptions bordered size="small" column={2} className="mb-3">
        <Descriptions.Item label="Reference">
          {message.reference}
        </Descriptions.Item>
        <Descriptions.Item label="Transporter">
          {message.transporter}
        </Descriptions.Item>
        <Descriptions.Item label="Message type">
          {message.messageType}
        </Descriptions.Item>
        <Descriptions.Item label="Imported at">
          {message.createdAt ? moment(message.createdAt).format('L LT') : '—'}
        </Descriptions.Item>
        {point.comments && (
          <Descriptions.Item label="Comments" span={2}>
            {point.comments}
          </Descriptions.Item>
        )}
      </Descriptions>

      <NamesAndAddresses namesAndAddresses={point.namesAndAddresses} />
      <DatesTable dates={point.dates} />
      <PackagesTable
        packages={point.packages}
        measurements={point.measurements}
      />
    </div>
  )
}

function RawMessage({ message }) {
  return (
    <pre
      style={{
        fontFamily: 'monospace',
        whiteSpace: 'pre-wrap',
        wordBreak: 'break-all',
        maxHeight: '60vh',
        overflow: 'auto',
        margin: 0,
      }}>
      {message.raw}
    </pre>
  )
}

function EdifactMessage({ message, index, total }) {
  return (
    <div>
      {index > 0 && <Divider />}
      {total > 1 && (
        <Title level={4}>
          {message.taskType} — {message.reference}
        </Title>
      )}
      <Tabs
        defaultActiveKey="parsed"
        items={[
          {
            key: 'parsed',
            label: 'Parsed',
            children: <ParsedMessage message={message} />,
          },
          {
            key: 'raw',
            label: 'Raw',
            children: <RawMessage message={message} />,
          },
        ]}
      />
    </div>
  )
}

export default function ({ deliveryId, label }) {
  const [isOpen, setIsOpen] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [messages, setMessages] = useState(null)

  const showLabel = label || 'View EDIFACT data'

  const httpClient = new window._auth.httpClient()

  const open = async () => {
    setIsOpen(true)

    if (messages !== null) {
      return
    }

    setIsLoading(true)
    const { response, error } = await httpClient.get(
      `${baseURL}/api/deliveries/${deliveryId}/edifact`,
    )
    setIsLoading(false)

    if (error) {
      setMessages([])
      return
    }

    setMessages(response?.messages || [])
  }

  return (
    <>
      <a
        onClick={open}
        title={showLabel}
        style={{ cursor: 'pointer' }}>
        <i className="fa fa-file-text-o fa-lg" aria-hidden="true"></i>
        <span className="ml-2">{showLabel}</span>
      </a>
      <Modal
        title="EDIFACT data"
        open={isOpen}
        onCancel={() => setIsOpen(false)}
        footer={null}
        width="980px">
        {isLoading ? (
          <div className="text-center p-4">
            <Spin />
          </div>
        ) : messages && messages.length > 0 ? (
          messages.map((message, index) => (
            <EdifactMessage
              key={index}
              message={message}
              index={index}
              total={messages.length}
            />
          ))
        ) : (
          <Empty description="No EDIFACT data available" />
        )}
      </Modal>
    </>
  )
}
