import React from "react";
import { Image } from "antd";

export default function ({ images }) {
  images = JSON.parse(images);
  return (
    <Image.PreviewGroup>
      {images.map((image) => (
        <span key={image} className="thumbnail">
          <Image width="128px" src={image} />
        </span>
      ))}
    </Image.PreviewGroup>
  );
}
