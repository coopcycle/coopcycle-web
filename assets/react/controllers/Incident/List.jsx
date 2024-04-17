import React, { useEffect, useState, useMemo } from "react";
import { Table, Tag, Tooltip, Avatar, Button, Row, Col, Badge } from "antd";
import TaskContext from "./TaskContext";
import _ from "lodash";
import "antd/dist/antd.css";

async function _fetchIncidents() {
  const httpClient = new window._auth.httpClient();
  return await httpClient.get(
    window.Routing.generate("api_incidents_get_collection"),
  );
}

function _showPriority(priority) {
  switch (priority) {
    case 1:
      return { text: "High", status: "error" };
    case 2:
      return { text: "Medium", status: "warning" };
    case 3:
      return { text: "Low", status: "success" };
  }
}

export default function () {
  const [incidents, setIncidents] = useState(null);

  const users = useMemo(() => {
    return _(incidents)
      .uniqBy("createdBy.id")
      .map((u) => ({ value: u.createdBy.username, text: u.createdBy.username }))
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

  console.log(users);
  const columns = [
    {
      title: "Title",
      dataIndex: "title",
      key: "title",
    },
    {
      title: "Priority",
      dataIndex: "priority",
      key: "priority",
      filters: [
        { text: "Low", value: 3 },
        { text: "Medium", value: 2 },
        { text: "High", value: 1 },
      ],
      onFilter: (value, record) => record.priority === value,
      sorter: (a, b) => a.priority - b.priority,
      render: (priority) => {
        const { text, status } = _showPriority(priority);
        return <Badge status={status} text={text} />;
      },
    },
    {
      title: "Status",
      dataIndex: "status",
      key: "status",
      filters: [
        { text: "Open", value: "OPEN" },
        { text: "Closed", value: "CLOSED" },
      ],
      onFilter: (value, record) => record.status === value,
      render: (text) => (
        <Tag color={text === "OPEN" ? "green" : "red"}>{text}</Tag>
      ),
    },
    {
      title: "Task",
      dataIndex: "task",
      key: "task",
      render: (task) => (
        <Tooltip placement="leftTop" title={<TaskContext task={task} />}>
          <Button type="link">Task #{task.id}</Button>
        </Tooltip>
      ),
    },
    {
      title: "Author",
      dataIndex: ["createdBy", "username"],
      key: "createdBy",
      filters: users,
      filterSearch: true,
      onFilter: (value, record) => record.createdBy.username === value,
      render: (username) => (
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
      title: "Action",
      dataIndex: "id",
      key: "action",
      render: (id) => (
        <a href={window.Routing.generate("admin_incident", { id })}>Edit</a>
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
            <TaskContext task={record.task} />
          </Col>
        </Row>
      )}
      rowKey="id"
    />
  );
}
