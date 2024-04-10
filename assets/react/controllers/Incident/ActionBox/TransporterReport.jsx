import React, { useState, useEffect } from "react";
import classNames from "classnames";
import {
  Select,
  Form,
  DatePicker,
  Image,
  Checkbox,
  Row,
  Col,
  Divider,
  Empty,
} from "antd";
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
      <div>
        {images.length > 1 && (
          <Checkbox
            className="my-3 mx-1"
            indeterminate={indeterminate}
            onChange={onCheckAllChange}
            checked={checkAll}
          >
            Check all
          </Checkbox>
        )}
      </div>
      <Checkbox.Group onChange={handleOnChange} value={selectedImages}>
        <Row gutter={[12, 12]} align="middle">
          {images.map((image, i) => {
            const selected = selectedImages.includes(image);
            return (
              <Col key={i} align="middle">
                <Image
                  width="128px"
                  height="128px"
                  src={image.thumbnail}
                  preview={{src: image.full}}
                  className={classNames("p-2, border", {
                    "border-primary": selected,
                    "border-default": !selected,
                  })}
                />
                <div className="mt-1">
                  <Checkbox value={image}>Select</Checkbox>
                </div>
              </Col>
            );
          })}
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

  const imageComponent =
    images.length > 0 ? (
      <ImagesSelector images={images} />
    ) : (
      <Empty description="No images" />
    );

  return (
    <Form layout="vertical" autoComplete="off">
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
      <Divider orientation="left" plain>
        Images to include in the report
      </Divider>
      {imageComponent}
    </Form>
  );
}
