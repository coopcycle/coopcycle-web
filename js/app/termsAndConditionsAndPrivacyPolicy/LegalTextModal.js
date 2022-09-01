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
  const [loadingText, setLoadingText] = useState(true)

  let agreeButtonObserver = null
  let agreeButtonRef = null

  const { t } = useTranslation()

  useEffect(() => {
    termsAndConditionsCheck.addEventListener('change', (e) => {
      _handleLegalText(
        e.target.checked,
        setTermsAndConditionsOpen,
        setTermsAndConditionsText,
        termsAndConditionsText,
        'terms'
      )
    })

    privacyPolicyCheck.addEventListener('change', (e) => {
      _handleLegalText(
        e.target.checked,
        setPrivacyPolicyOpen,
        setPrivacyPolicyText,
        privacyPolicyText,
        'privacy'
      )
    })

    return () => agreeButtonRef?.current ? agreeButtonObserver.unobserve(agreeButtonRef.current) : null;
  }, [])

  const _handleLegalText = async (checked, setModalOpen, setTypeText, typeText, type) => {
    if (checked) {
      setLoadingText(true)
      setLegalText(null)
      setModalOpen(true)

      let text = typeText
      if (!text) {
        try {
          const result = await $.getJSON(`/${document.documentElement.lang}/${type}-text`)
          if (result?.text) {
            text = result.text
            setTypeText(text)
          } else {
            setModalOpen(false)
            return
          }
        } catch (err) {
          console.error(err)
          setModalOpen(false)
          return
        }
      }

      setLegalText(text)
      setLoadingText(false)
    } else {
      setModalOpen(false)
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

  const _renderLoading = () => {
    return (
      <div className="row" >
          <div className="col-xs-5"></div>
          <div className="col-xs-2">
            <i className="fa fa-spinner fa-spin"></i>
          </div>
          <div className="col-xs-5"></div>
        </div>
    )
  }

  return (
    <Modal
        isOpen={termsAndConditionsOpen || privacyPolicyOpen}
        shouldCloseOnOverlayClick={false}
        className="ReactModal__Content--termsAndPolicy"
        overlayClassName="ReactModal__Overlay--termsAndPolicy">
        {
          loadingText ? _renderLoading() :
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
        }
      </Modal>
  )
}
