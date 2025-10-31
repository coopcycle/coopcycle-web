function addFormToCollection(e) {

  e.preventDefault();

  const container = e.target.closest('[data-form-type="collection"]');
  const listContainer = container.querySelector('[data-form-collection="list"]');

  const index = listContainer.childElementCount;

  const item = document.createElement('div');

  item.innerHTML = container
    .dataset
    .prototype
    .replace(
      /__name__/g,
      index
    );

  listContainer.appendChild(item);

  // collectionHolder.dataset.index++;
};


document
  .querySelectorAll('[data-form-collection="add"]')
  .forEach(btn => {
      btn.addEventListener("click", addFormToCollection)
  });
