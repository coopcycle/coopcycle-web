import React from 'react'
import { createRoot } from 'react-dom/client'
import { Switch } from 'antd'
import { useTranslation } from 'react-i18next';

function addFormToCollection(e) {

  e.preventDefault();

  const container = e.target.closest('[data-form-type="collection"]');
  const listContainer = container.querySelector('[data-form-collection="list"]');

  const index = listContainer.childElementCount;

  const tmp = document.createElement('div');

  tmp.innerHTML = container
    .dataset
    .prototype
    .replace(
      /__name__/g,
      index
    );

  const item = tmp.querySelector('[data-form-collection="item"]');

  const formPrototypeName = item.querySelector('[data-form-collection="update"]').value;
  const formPrototype = container.querySelector(`[data-form-prototype="${formPrototypeName}"]`);

  const newItem = createFormItem(formPrototype, index);

  listContainer.appendChild(newItem);
  addEventListeners(newItem);
};

function createFormItem(formPrototype, index) {

  const tmp = document.createElement('div');
  tmp.innerHTML = formPrototype.value.replace(
    /__name__/g,
    index
  );

  return tmp.querySelector('[data-form-collection="item"]');
}

function updateFormItem(e) {

  const formPrototypeName = e.target.value;
  const collectionContainer = e.target.closest('[data-form-type="collection"]');

  const listContainer = collectionContainer.querySelector('[data-form-collection="list"]');
  const formPrototype = collectionContainer.querySelector(`[data-form-prototype="${formPrototypeName}"]`);

  const item = e.target.closest('[data-form-collection="item"]');
  const index = Array.prototype.indexOf.call(listContainer.children, item);

  const newItem = createFormItem(formPrototype, index);

  item.replaceWith(newItem);
  addEventListeners(newItem);
}

function removeFormFromCollection(e) {
  e.preventDefault();
  e.target.closest('[data-form-collection="item"]').remove();
}

function addEventListeners(el) {
  el.querySelector('[data-form-collection="delete"]').addEventListener('click', removeFormFromCollection);
  el.querySelector('[data-form-collection="update"]').addEventListener('change', updateFormItem)
}

document
  .querySelectorAll('[data-form-collection="add"]')
  .forEach(btn => {
    btn.addEventListener("click", addFormToCollection)
  });

document
  .querySelectorAll(['[data-form-collection="delete"]'])
  .forEach(btn => {
    btn.addEventListener("click", removeFormFromCollection)
  });

document
  .querySelectorAll('[data-form-collection="item"]')
  .forEach(item => addEventListeners(item));

const coupon = document.getElementById('restaurant_promotion_coupon');
const couponCode = document.getElementById('restaurant_promotion_coupon_code');
const rules = document.getElementById('restaurant_promotion_rules');

function handleCouponBased(checked) {
  if (!checked) {
    coupon.closest('.form-group').classList.add('d-none');
    couponCode.required = false;
    rules.closest('.form-group').classList.remove('d-none');
  } else {
    coupon.closest('.form-group').classList.remove('d-none');
    couponCode.required = true;
    rules.querySelector('[data-form-collection="list"]').innerHTML = '';
    rules.closest('.form-group').classList.add('d-none');
  }
}

const couponBased = document.getElementById('restaurant_promotion_couponBased');

couponBased.addEventListener('change', (e) => {
  handleCouponBased(e.target.checked);
});

handleCouponBased(couponBased.checked);

const switchContainer = document.createElement('div');

const CouponBasedSwitch = () => {

  const { t } = useTranslation();

  return (
    <div className="d-flex align-items-center">
      <Switch
        defaultChecked={ couponBased.checked }
        disabled={ couponBased.disabled }
        onChange={ (checked) => {
          couponBased.checked = checked;
          couponBased.dispatchEvent(new Event('change'));
        } } />
      <span className="ml-2">{ t('PROMOTION_COUPON_BASED') }</span>
    </div>
  )
}

couponBased.closest('.checkbox').classList.add('d-none');

createRoot(switchContainer).render(<CouponBasedSwitch />)
couponBased.closest('.form-group').appendChild(switchContainer);
