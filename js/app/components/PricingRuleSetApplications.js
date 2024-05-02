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
      return "Unhandled entity type"
  }
}

export default function PricingRuleSetApplications(props) {
  const { t } = useTranslation(),
    { pricingRuleSetId } = props,
    [applications, setApplications] = useState([]),
    [loading, setLoading] = useState(true),
    [expanded, setExpanded] = useState(false),
    displayedApplications = expanded ? applications : applications.slice(0, 1)

  useEffect(() => {
    const url = window.Routing.generate('api_pricing_rule_sets_applications_item', {id:pricingRuleSetId})
    // const jwtToken = document.querySelector("#pricings-list").dataset.jwt

    const headers = {
      'Authorization': `Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE3MTQ2MzM1OTcsImV4cCI6MTcxNDYzNzE5Nywicm9sZXMiOlsiUk9MRV9BRE1JTiIsIlJPTEVfRElTUEFUQ0hFUiIsIlJPTEVfU1RPUkUiLCJST0xFX1VTRVIiXSwidXNlcm5hbWUiOiJhZG1pbiJ9.A6KQsqks5_czE8TChzwfxfTE6h_8Tb5aRTvxzqwtn8d3e1F1XacIT-rnoU8iw4guaYNVrBSUa5DvLZOo_kGl3-rMTBSdhKw2bQcPK2BlJxutU36EwvND_ZR1BVX6VwqE0oniSaseFqqXYucFhPnx3DwTbuuS7wprCArJnbfQ8RPhieKU-zZKXMZPP8NN8oJqjL-xHnH8u-qUNlxl9Nec74J13PbqmUNqJT8rhWqU_95qPM3HZGHYhVeDUlHptyIyHLIZzBn3Wrm6PoEJ3nimzNRxaosc6d7WGGwplB8tDuuiL0d1hyEeULGFdTbvWoPjIMlb9y5V1ADDy_AoDBeeDxVouxEz6_kF6tE5QhtQQmkYqnSBRnhQuD25qeUhW_lelw8f23-qLIFsDMo-RmPlYdrSM4WPMyGopOI-xyZBB-8t3UmRSiuU5inyadZIqjBP68t9Z7JmJ9LvakE8XCGHRL75FfMCwoTnVZr3DfLQ9qHRMj-fxa8oxMv4owOmfYJUIx9wc1PaRErnX8jtIIOOXwHow7sVnUjqxLq8bb4APh_PagtvxG2AO6YSqsI_MWn8dzNGHvi49qWRAy3j_vofoEQM0-qV92-UDtdAwLCmgbI5yipx7fCT5knZtlas0mjMGw1bq_lZVwe02-Kms-obfovJMnh242dbhAm5Bo4fPZ0`,
      'Accept': 'application/ld+json',
      'Content-Type': 'application/ld+json'
    }

    axios.get(url, { headers: headers}).then((resp) => {
      setApplications(resp.data['hydra:member'])
      setLoading(false)
    })

    },[])

  return (
    <ul>
      { loading ?
        <div className="text-center"><span className="loader loader--dark"></span></div> :
        <>
          {displayedApplications.length > 0 ?
            displayedApplications.map((pricingRuleSetApplication, index) => {
              return <LinkToApplication key={index} pricingRuleSetApplication={pricingRuleSetApplication} />
            }) :
            t('ADMIN_NO_APPLICATIONS')
          }
          { expanded ? (<a onClick={() => setExpanded(false)}>...show more</a>) : (<a onClick={() => setExpanded(true)}>hide</a>) }
        </>
      }
    </ul>
  )
}
