import React, { useState, useEffect } from "react";
import classNames from "classnames";
import { Select, Form, DatePicker, Image, Checkbox, Row, Col } from "antd";
import "../Style.scss";

async function _fetchFailureReason(id) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.get(
    window.Routing.generate("api_tasks_task_failure_reasons_item", { id }),
  );
}

function ImagesSelector({ images, onChange = () => {} }) {
  const [selectedImages, setSelectedImages] = useState([]);
  const [indeterminate, setIndeterminate] = useState(false);
  const [checkAll, setCheckAll] = useState(false);

  const handleOnChange = (checkedList) => {
    setCheckAll(checkedList.length === images.length);
    setIndeterminate(
      !!checkedList.length && checkedList.length < images.length,
    );
    setSelectedImages(checkedList);
    onChange(checkedList);
  };

  const onCheckAllChange = (e) => {
    handleOnChange(e.target.checked ? images : []);
    setCheckAll(e.target.checked);
    setIndeterminate(false);
  };

  return (
    <>
      <Checkbox
        className="m-3"
        indeterminate={indeterminate}
        onChange={onCheckAllChange}
        checked={checkAll}
      >
        Check all
      </Checkbox>
      <Checkbox.Group onChange={handleOnChange} value={selectedImages}>
        <Row gutter={[12, 12]} align="middle">
          {images.map((image, i) => (
            <Col key={i} span={4} align="middle">
              <Image
                width="128px"
                src={image}
                className={classNames("p-2", {
                  "border border-primary": selectedImages.includes(image),
                })}
              />
              <div className="mt-1">
                <Checkbox value={image}>Select</Checkbox>
              </div>
            </Col>
          ))}
        </Row>
      </Checkbox.Group>
    </>
  );
}

export default function ({ incident, task, images }) {
  console.log(incident);

  const [failureReasons, setFailureReasons] = useState(null);

  useEffect(() => {
    async function _fetch() {
      const { response, error } = await _fetchFailureReason(task.id);
      if (!error) {
        const value = response["hydra:member"].map((v) => ({
          value: v.code,
          label: v.description,
        }));
        setFailureReasons(value);
      }
    }
    _fetch();
  }, []);

  return (
    <Form labelCol={{ span: 6 }} wrapperCol={{ span: 16 }} autoComplete="off">
      <Form.Item
        label="Failure reason"
        name="failureReason"
        rules={[{ required: true, message: "Please enter a reason" }]}
      >
        <Select
          loading={!failureReasons}
          disabled={!failureReasons}
          options={failureReasons}
        />
      </Form.Item>
      <Form.Item
        label="Failure date"
        name="failureDate"
        rules={[{ required: true, message: "Please enter a date" }]}
      >
        <DatePicker
          style={{ width: "100%" }}
          format="LLL"
          showTime={{ format: "HH:mm", minuteStep: 15 }}
        />
      </Form.Item>
      <h4>Images</h4>
      <ImagesSelector images={images} />
    </Form>
  );
}
