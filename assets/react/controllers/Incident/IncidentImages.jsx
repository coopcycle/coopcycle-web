import React from "react";
import { Image, Upload } from "antd";
import "./Style.scss";

async function _handleUpload(id, file) {
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

export default function ({ images, incident_id }) {
  images = JSON.parse(images);
  return (
    <>
      <Image.PreviewGroup>
        {images.map((image) => (
          <span key={image} className="thumbnail">
            <Image width="128px" src={image} />
          </span>
        ))}
      </Image.PreviewGroup>
      <Upload
        name="image"
        customRequest={({ file }) => _handleUpload(incident_id, file)}
        className="thumbnail"
      >
        <div className="incident-image-uploader ">
          <div>
            <i className="fa fa-upload mr-2"></i>Upload
          </div>
        </div>
      </Upload>
    </>
  );
}
