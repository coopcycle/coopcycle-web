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
  const [legalHelp, setLegalHelp] = useState(null)
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
      setLegalHelp(null)
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
          setModalOpen(false)
          return
        }
      }

      setLegalText(text)
      setLegalHelp(t(`MUST READ_BEFORE_AGREE_${type}`))
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
          <form className="d-flex flex-column">
            <div className="legal-text">
              <ReactMarkdown >
                { legalText }
              </ReactMarkdown>
              <span className="bottom" ref={el => _handleRefAfterRender(el)}>bottom</span>
            </div>
            <div className="d-flex justify-content-end align-items-center p-1 mt-4">
              <span className="help flex-1 mr-4">
                <i className="fa fa-info-circle mr-2"></i>
                { legalHelp }
              </span>
              <div className="mr-4">
                <button type="button" className="btn btn-block btn-default"
                  onClick={() => _handleCancel()}>
                  { t('CANCEL') }
                </button>
              </div>
              <div className="">
                <button disabled={agreeButtonDisabled}
                  type="button" className="btn btn-block btn-agree" onClick={() => _handleAgree()}>
                  { t('AGREE') }
                </button>
              </div>
            </div>
          </form>
        }
      </Modal>
  )
}
