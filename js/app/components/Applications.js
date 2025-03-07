import axios from 'axios'
import { isArray } from 'lodash'
import React, { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'

function LinkToApplication ({pricingRuleSetApplication}) {
  const { t } = useTranslation()

  switch (pricingRuleSetApplication.entity) {
    case "AppBundle\\Entity\\Store" : {
      const url = window.Routing.generate("admin_store", {id: pricingRuleSetApplication.id})
      return (<li><a href={url}>{t('STORE')} {pricingRuleSetApplication.name}</a></li>)
    }
    case "AppBundle\\Entity\\LocalBusiness": {
      const url = window.Routing.generate("admin_restaurant", {id: pricingRuleSetApplication.id})
      return (<li><a href={url}>{t('RESTAURANT')} {pricingRuleSetApplication.name}</a></li>)
    }
    case "AppBundle\\Entity\\LocalBusinessGroup": {
      const url = window.Routing.generate("admin_business_restaurant_group", {id: pricingRuleSetApplication.id})
      return (<li><a href={url}>{t('RESTAURANTS_GROUP')} {pricingRuleSetApplication.name}</a></li>)
    }
    case "AppBundle\\Entity\\DeliveryForm": {
      const url = window.Routing.generate("admin_form", {id: pricingRuleSetApplication.id})
      return (<li><a href={url}>{t('DELIVERY_FORM')} {pricingRuleSetApplication.name}</a></li>)
    }
    default:
      console.error('Unable to display linked entity')
      return <li>Unhandled entity type</li>
  }
}

export default function ShowApplications(props) {
  const { t } = useTranslation(),
    { data, objectId, fetchUrl } = props,
    [applications, setApplications] = useState([]),
    [loading, setLoading] = useState(true),
    [expanded, setExpanded] = useState(false)

    useEffect(() => {

      if (isArray(data)) {
        setLoading(false)
        setApplications(data)
      } else {
        const jwt = document.head.querySelector('meta[name="application-auth-jwt"]').content
        const headers = {
          'Authorization': `Bearer ${jwt}`,
          'Accept': 'application/ld+json',
          'Content-Type': 'application/ld+json'
        }
        const url = window.Routing.generate(fetchUrl, {id: objectId})

        axios.get(url, { headers: headers}).then((resp) => {
          setApplications(resp.data['hydra:member'])
          setLoading(false)
        })
      }
    },[])

  return (
    <>
      { loading ?
        <span className="loader loader--dark"></span> :
        <>
          { applications.length > 0 ?
            <span>
              {t('ADMIN_APPLIED_TO', { count: applications.length })} - <a onClick={() => setExpanded(!expanded)}>{ t('SHOW_DETAILS') }</a>
            </span> :
            <span>{t('ADMIN_NO_APPLICATIONS')}</span>
          }
          { expanded ?
            <ul className="nomargin">
              { applications.map((pricingRuleSetApplication, index) => {return <LinkToApplication key={index} pricingRuleSetApplication={pricingRuleSetApplication} />}) }
            </ul> :
            null
          }
        </>
      }
    </>
  )
}
