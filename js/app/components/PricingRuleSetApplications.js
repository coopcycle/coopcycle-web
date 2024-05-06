import axios from 'axios'
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
      return "Unhandled entity type"
  }
}

export default function PricingRuleSetApplications(props) {
  const { t } = useTranslation(),
    { url } = props,
    [applications, setApplications] = useState([]),
    [loading, setLoading] = useState(true),
    [expanded, setExpanded] = useState(false),
    NUM_SHOWN_APPLICATIONS = 1,
    displayedApplications = expanded ? applications : applications.slice(0, NUM_SHOWN_APPLICATIONS)

    useEffect(() => {

    const jwt = document.head.querySelector('meta[name="application-auth-jwt"]').content
    const headers = {
      'Authorization': `Bearer ${jwt}`,
      'Accept': 'application/ld+json',
      'Content-Type': 'application/ld+json'
    }

    axios.get(url, { headers: headers}).then((resp) => {
      setApplications(resp.data['hydra:member'])
      setLoading(false)
    })

    },[])

  return (
    <>
    { loading ?
      <span className="loader loader--dark"></span> :
      <>
        <ul>
            { displayedApplications.length > 0 ?
              displayedApplications.map((pricingRuleSetApplication, index) => {
                return <LinkToApplication key={index} pricingRuleSetApplication={pricingRuleSetApplication} />
              }) :
              <li>{t('ADMIN_NO_APPLICATIONS')}</li>
            }
        </ul>
        { displayedApplications.length > NUM_SHOWN_APPLICATIONS ?
          expanded ?
            (<a onClick={() => setExpanded(false)}>...show more</a>) :
            (<a onClick={() => setExpanded(true)}>hide</a>)
          : null
        }
      </>
    }
    </>
  )
}
