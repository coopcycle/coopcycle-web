import React, { useEffect, useState, useMemo } from "react";
import { Table, Tag, Avatar, Row, Col, Badge } from "antd";
import IncidentItem from "./IncidentItem";
import _ from "lodash";
import "antd/lib/pagination/style/index.css";
import "antd/lib/tree/style/index.css";
import "antd/lib/table/style/index.css";
import "antd/lib/tooltip/style/index.css";
import { useTranslation } from "react-i18next";

async function _fetchIncidents() {
  const httpClient = new window._auth.httpClient();
  return await httpClient.get(
    window.Routing.generate("api_incidents_get_collection"),
  );
}

function _showPriority(priority) {
  switch (priority) {
    case 1:
      return { text: "HIGH", status: "error" };
    case 2:
      return { text: "MEDIUM", status: "warning" };
    case 3:
      return { text: "LOW", status: "success" };
  }
}

function _statusCtx({ store, order: { restaurant } }) {
  if (store.id) {
    return (
      <div>
        <i className="fa fa-truck mr-2" aria-hidden="true"></i>
        {store.name}
      </div>
    );
  }

  if (restaurant.id) {
    return (
      <div>
        <i className="fa fa-cutlery mr-2" aria-hidden="true"></i>
        {restaurant.name}
      </div>
    );
  }
}

function _storeFilter(value, record) {
  if (value.startsWith("#s")) {
    return record.store.id === parseInt(value.substring(2));
  }

  if (value.startsWith("#r")) {
    return record.order.restaurant.id === parseInt(value.substring(2));
  }

  if (value === "store") {
    return record.store.id;
  }

  if (value === "restaurant") {
    return record.order.restaurant.id;
  }
}

export default function () {
  const { t } = useTranslation();
  const [incidents, setIncidents] = useState(null);

  const users = useMemo(() => {
    return _(incidents)
      .uniqBy("author.id")
      .map((u) => ({ value: u.author.username, text: u.author.username }))
      .value();
  }, [incidents]);

  const stores = useMemo(() => {
    return _(incidents)
      .filter((i) => i.store.id)
      .uniqBy("store.id")
      .map((i) => ({ value: "#s" + i.store.id, text: i.store.name }))
      .value();
  }, [incidents]);

  const restaurants = useMemo(() => {
    return _(incidents)
      .filter((i) => i.order.restaurant.id)
      .uniqBy("order.restaurant.id")
      .map((i) => ({
        value: "#r" + i.order.restaurant.id,
        text: i.order.restaurant.name,
      }))
      .value();
  }, [incidents]);

  const customers = useMemo(() => {
    return _(incidents)
      .filter((i) => i.order.customer.id)
      .uniqBy("order.customer.id")
      .map((i) => ({
        value: i.order.customer.id,
        text: i.order.customer.username,
      }))
      .value();
  }, [incidents]);

  useEffect(() => {
    async function _fetch() {
      const { response, error } = await _fetchIncidents();
      if (!error) {
        setIncidents(response["hydra:member"]);
      }
    }
    _fetch();
  }, []);

  const columns = [
    {
      title: t("TITLE"),
      dataIndex: "title",
      key: "title",
    },
    {
      title: t("PRIORITY"),
      dataIndex: "priority",
      key: "priority",
      filters: [
        { text: t("LOW"), value: 3 },
        { text: t("MEDIUM"), value: 2 },
        { text: t("HIGH"), value: 1 },
      ],
      onFilter: (value, record) => record.priority === value,
      sorter: (a, b) => a.priority - b.priority,
      render: (priority) => {
        const { text, status } = _showPriority(priority);
        return <Badge status={status} text={t(text)} />;
      },
    },
    {
      title: t("STATUS"),
      dataIndex: "status",
      key: "status",
      filters: [
        { text: t("OPEN"), value: "OPEN" },
        { text: t("CLOSED"), value: "CLOSED" },
      ],
      onFilter: (value, record) => record.status === value,
      render: (text) => (
        <Tag color={text === "OPEN" ? "green" : "red"}>{text}</Tag>
      ),
    },
    {
      title: t("STORE"),
      key: "context",
      filters: [
        { text: t("STORE"), value: "store", children: stores },
        { text: t("RESTAURANT"), value: "restaurant", children: restaurants },
      ],
      filterSearch: true,
      filterMode: "tree",
      onFilter: _storeFilter,
      render: _statusCtx,
    },
    {
      title: t("CUSTOMER"),
      dataIndex: ["order", "customer", "username"],
      filters: customers,
      filterSearch: true,
      onFilter: (value, record) => record.order.customer.id === value,
      key: "customer",
    },
    {
      title: t("REPORTED_BY"),
      dataIndex: "author",
      key: ["author", "username"],
      filters: users,
      filterSearch: true,
      onFilter: (value, record) => record.author.username === value,
      render: ({ username }) => (
        <>
          <Avatar
            size="small"
            className="mr-2"
            src={window.Routing.generate("user_avatar", { username })}
          />
          {username}
        </>
      ),
    },
    {
      title: t("ACTION"),
      dataIndex: "id",
      key: "action",
      render: (id) => (
        <a href={window.Routing.generate("admin_incident", { id })}>
          {t("EDIT")}
        </a>
      ),
    },
  ];
  return (
    <Table
      columns={columns}
      loading={!incidents}
      dataSource={incidents}
      expandedRowRender={(record) => (
        <Row gutter={[16, 16]}>
          <Col span={18}>
            <p>{record.description}</p>
          </Col>
          <Col span={6}>
            <IncidentItem task={record.task} />
          </Col>
        </Row>
      )}
      rowKey="id"
    />
  );
}
