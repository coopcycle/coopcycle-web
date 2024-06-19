import React from "react";
import { Image, Upload, notification } from "antd";
import "./Style.scss";

import store from "./incidentStore";

async function _handleUpload(id, file) {
  if (file.size > 5 * 1024 * 1024) {
    return { message: "Image is too big" };
  }
  const httpClient = new window._auth.httpClient();
  const formData = new FormData();
  formData.append("file", file);
  return await httpClient.post(
    window.Routing.generate("api_incident_images_post_collection"),
    formData,
    {
      "Content-Type": "multipart/form-data",
      "X-Attach-To": `/api/incidents/${id}`,
    },
  );
}

export default function () {
  const { incident, images } = store.getState();
  return (
    <>
      <Image.PreviewGroup>
        {images.map((image) => (
          <span key={image.id} className="thumbnail">
            <Image
              width="128px"
              src={image.thumbnail}
              preview={{ src: image.full }}
            />
          </span>
        ))}
      </Image.PreviewGroup>
      <Upload
        name="image"
        accept="image/*"
        customRequest={async ({ file }) => {
          const { error, message } = await _handleUpload(incident.id, file);
          if (!error) {
            location.reload();
          } else {
            if (message) {
              return notification.error({ message });
            }
            return notification.error({ message: "Something went wrong" });
          }
        }}
        className="thumbnail"
      >
        <div className="incident-image-uploader">
          <div>
            <i className="fa fa-upload mr-2"></i>Upload
          </div>
        </div>
      </Upload>
    </>
  );
}
