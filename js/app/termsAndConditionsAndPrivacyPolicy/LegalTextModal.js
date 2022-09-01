import React, { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import ReactMarkdown from 'react-markdown'
import Modal from 'react-modal'

import './index.scss'

export default ({termsAndConditionsCheck, privacyPolicyCheck}) => {
  const [termsAndConditionsOpen, setTermsAndConditionsOpen] = useState(false)
  const [privacyPolicyOpen, setPrivacyPolicyOpen] = useState(false)
  const [termsAndConditionsText, setTermsAndConditionsText]= useState(null)
  const [privacyPolicyText, setPrivacyPolicyText] = useState(null)
  const [legalText, setLegalText] = useState(null)
  const [agreeButtonDisabled, setAgreeButtonDisabled] = useState(true)

  let agreeButtonObserver = null
  let agreeButtonRef = null

  const { t } = useTranslation()

  useEffect(() => {
    termsAndConditionsCheck.addEventListener('change', (e) => {
      _handleTermsAndConditions(e.target.checked);
    })

    privacyPolicyCheck.addEventListener('change', (e) => {
      _handlePrivacyPolicy(e.target.checked);
    })

    return () => agreeButtonRef?.current ? agreeButtonObserver.unobserve(agreeButtonRef.current) : null;
  }, [])

  const _handleTermsAndConditions = async (checked) => {
    if (checked) {
      let text = termsAndConditionsText
      if (!text) {
        text = await Promise.resolve('# Condiciones de uso de CoopCycle para el sitio web y la aplicación\n\nEsta página (y cualquier documento al que se haga referencia en ella) establece las condiciones de uso de nuestro sitio web [coopcycle.org](https://coopcycle.org)')
        setTermsAndConditionsText(text)
      }

      setLegalText(text)
      setTermsAndConditionsOpen(true)
    } else {
      setTermsAndConditionsOpen(false)
    }
  }

  const _handlePrivacyPolicy = async (checked) => {
    if (checked) {
      let text = privacyPolicyText
      if (!text) {
        text = await Promise.resolve('## Data Collector : CoopCycle\n\n### Información de contacto\n\n**Oficina central** : 23, avenue Claude Vellefaux, 75010 Paris, France\n\n**Número SIRET** : 83361956200014\n\n**Número RNA** : W751241474\n\n**Email** : contact [AT] coopcycle.org\n\n**Presidente** : Lison Noël\n\n### Tipo de datos recopilados\n\nRecogemos datos en los siguientes eventos:\n\n- Direcciones IP - Recogemos estos datos cuando se conecta a nuestro sitio web a través de Internet.\n- Análisis del tráfico - Recogemos estos datos cuando las páginas se cargan en un navegador u otro programa compatible con javascript / http.\n- Información de la cuenta de usuario - Recopilamos estos datos al crear una cuenta de usuario en el sitio.\n\n### Duración del almacenamiento de datos\n\nAlmacenamos diferentes tipos de datos de forma diferente:\n\n- Direcciones IP - Esta información se almacena en línea durante 30 días antes de ser eliminada. Parte de esta información se utiliza con software de red y de diagnóstico, y esta información puede almacenarse durante períodos de tiempo más largos.\n- Análisis del tráfico - Almacenamos estos datos indefinidamente.\n- Información de la cuenta del usuario - Almacenamos estos datos indefinidamente o hasta que el usuario solicite su eliminación.\n\n### Alojamiento de datos\n\nLos datos personales están alojados en la Unión Europea.\n\nCoopCycle se esfuerza por tomar precauciones razonables para mantener la confidencialidad y seguridad de los datos personales procesados y evitar que sean distorsionados, dañados, destruidos o accedidos por terceros no autorizados.\n\nA los efectos del almacenamiento técnico de sus datos personales, éstos pueden ser almacenados de forma centralizada en los siguientes proveedores de servicios:\n\n- OVH\n- Scaleway\n- Digital Ocean\n\n### Uso de los datos\n\nUtilizamos principalmente la información de la cuenta de usuario para proporcionarle los servicios que ha solicitado.\n\nNecesitamos la dirección y el número de teléfono para hacer entregas a domicilio, el número de teléfono nos es útil cuando no podemos encontrar la dirección proporcionada. La dirección de correo electrónico se utiliza para enviar notificaciones de pedidos.\n\nTambién necesitamos su información de contacto para resolver cualquier problema con sus pedidos.\n\nPodemos analizar su actividad en nuestros sitios ')
        setPrivacyPolicyText(text)
      }

      setLegalText(text)
      setPrivacyPolicyOpen(true)
    } else {
      setPrivacyPolicyOpen(false)
    }
  }

  const _handleCancel = () => {
    if (termsAndConditionsOpen) {
      termsAndConditionsCheck.checked = false
      setTermsAndConditionsOpen(false)
    } else if (privacyPolicyOpen) {
      privacyPolicyCheck.checked = false
      setPrivacyPolicyOpen(false)
    }
  }

  const _handleAgree = () => {
    setTermsAndConditionsOpen(false)
    setPrivacyPolicyOpen(false)
  }

  const _handleRefAfterRender = (el) => {
    if (el) {
      agreeButtonRef = el

      agreeButtonObserver = new IntersectionObserver(
        ([entry]) => {
          // button has to be disabled when it is not in viewport (intersecting)
          setAgreeButtonDisabled(!entry.isIntersecting)
        }, { rootMargin: '10px' }
      );

      agreeButtonObserver.observe(agreeButtonRef);
      }
  }

  return (
    <Modal
        isOpen={termsAndConditionsOpen || privacyPolicyOpen}
        shouldCloseOnOverlayClick={false}
        className="ReactModal__Content--termsAndPolicy"
        overlayClassName="ReactModal__Overlay--termsAndPolicy">
        <form>
          <ReactMarkdown>
            { legalText }
          </ReactMarkdown>
          <div className="row" >
            <div className="col-sm-4 col-xs-6">
              <button type="button" className="btn btn-block btn-default"
                onClick={() => _handleCancel()}>
                { t('CANCEL') }
              </button>
            </div>
            <div className="col-sm-8 col-xs-6">
              <button ref={el => _handleRefAfterRender(el)} disabled={agreeButtonDisabled}
                type="button" className="btn btn-block btn-success" onClick={() => _handleAgree()}>
                { t('AGREE') }
              </button>
            </div>
          </div>
        </form>
      </Modal>
  )
}
