import React from 'react';
import { Image, Upload, notification } from 'antd';
import './Style.scss';
import { useTranslation } from 'react-i18next';

import store from './redux/incidentStore';
import { selectImages, selectIncident } from './redux/incidentSlice';

async function _handleUpload(id, file, t) {
  if (file.size > 5 * 1024 * 1024) {
    return { message: t('INCIDENTS_IMAGE_TOO_BIG') };
  }
  const httpClient = new window._auth.httpClient();
  const formData = new FormData();
  formData.append('file', file);
  return await httpClient.post(
    window.Routing.generate('_api_/incident_images{._format}_post'),
    formData,
    {
      'Content-Type': 'multipart/form-data',
      'X-Attach-To': `/api/incidents/${id}`,
    },
  );
}

export default function () {
  //FIXME: replace with useAppSelector after migrating away from ux-react-controllers
  const state = store.getState();
  const incident = selectIncident(state);
  const images = selectImages(state);
  const { t } = useTranslation();

  return (
    <>
      <Image.PreviewGroup>
        {images.map(image => (
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
          const { error, message } = await _handleUpload(incident.id, file, t);
          if (!error) {
            location.reload();
          } else {
            if (message) {
              return notification.error({ message });
            }
            return notification.error({ message: t('SOMETHING_WENT_WRONG') });
          }
        }}
        className="thumbnail">
        <div className="incident-image-uploader">
          <div>
            <i className="fa fa-upload mr-2"></i>
            {t('INCIDENTS_UPLOAD')}
          </div>
        </div>
      </Upload>
    </>
  );
}
