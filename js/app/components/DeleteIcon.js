import React, { useState } from 'react'
import { useTranslation } from 'react-i18next'


export default function DeleteIcon({ deleteUrl, objectId, objectName, errorMessage }) {

  errorMessage = errorMessage || "ADMIN_DEFAULT_DELETION_ERROR_MESSAGE"

  const { t } = useTranslation(),
    [loading, setLoading] = useState(false),
    onDeleteClick = async () => {
      if (!window.confirm(t('CONFIRM_DELETE_WITH_PLACEHOLDER', { object_name: objectName }))) {
          return
      }

      setLoading(true)
      const url = window.Routing.generate(deleteUrl, {
        id: objectId,
      })

      const httpClient = new window._auth.httpClient()
      const { error } = await httpClient.delete(url);

      if (error)
      {
        setLoading(false)
        alert(t(errorMessage))
        return;
      } else {
        window.location.reload()
      }
    }

  return (
    <>
      { loading ?
        <span className="loader loader--dark"></span> :
        (<a className="text-danger" onClick={() => onDeleteClick()}>
          <i className="fa fa-trash delete-package"></i>
        </a>)
      }
    </>
  )
}