import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client'
import {
  DndContext,
  useDroppable,
  useDraggable,
  closestCenter,
  closestCorners,
  KeyboardSensor,
  PointerSensor,
  MouseSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  useSortable,
  sortableKeyboardCoordinates,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities';
import _ from 'lodash';
import { Provider, useDispatch, useSelector } from 'react-redux'

import { createStoreFromPreloadedState } from './menu-editor/store'
import { fetchProducts, removeProductFromSection } from './menu-editor/actions'
import { selectProducts, selectMenuSections } from './menu-editor/selectors'

import './menu-editor.scss'

// https://github.com/clauderic/dnd-kit/blob/master/stories/2%20-%20Presets/Sortable/MultipleContainers.tsx

const httpClient = new window._auth.httpClient()

const Section = ({ section, index }) => {

  const { setNodeRef } = useDroppable({
    id: section['@id'],
    data: {
      type: 'section',
    }
  });

  return (
    <div className="menuEditor__panel mb-4">
      <h4 className="menuEditor__panel__title">
        <i className="fa fa-arrows mr-2" aria-hidden="true"></i>
        <a href="#">
          <span className="mr-2">{ section.name }</span>
          <i className="fa fa-pencil" aria-hidden="true"></i>
        </a>
        <a className="pull-right" href="#">
          <i className="fa fa-close"></i>
        </a>
      </h4>
      <SortableContext
        // The SortableContext component also optionally accepts an id prop.
        // If an id is not provided, one will be auto-generated for you.
        // The id prop is for advanced use cases.
        // If you're building custom sensors, you'll have access to each sortable element's data prop,
        // which will contain the containerId associated to that sortable context.
        id={ section['@id'] }
        // https://docs.dndkit.com/presets/sortable/sortable-context
        // It requires that you pass it a sorted array of the unique identifiers
        // associated with the elements that use the useSortable hook within it.
        items={ section.hasMenuItem.map(product => product['@id']) }
        strategy={verticalListSortingStrategy}>
        <div ref={ setNodeRef } className="menuEditor__panel__body">
          { section.hasMenuItem.map((product) => (
            <Product key={ product['@id'] } product={ product } />
          )) }
        </div>
      </SortableContext>
    </div>
  )
}

const LeftPanel = () => {

  const sections = useSelector(selectMenuSections)

  return (
    <div className="menuEditor__left">
      { sections.map((section, index) => (
        <Section key={`section-${index}`} section={section} index={ index } />
      ))}
      <div className="d-flex flex-row align-items-center justify-content-between border p-4">
        <strong>Add child</strong>
        <button type="button" className="btn btn-success" data-toggle="modal" data-target="#newChildTaxonModal">
          <i className="fa fa-plus mr-2"></i><span>Add</span>
        </button>
      </div>
    </div>
  )
}

const RightPanel = () => {

  const { isOver, setNodeRef } = useDroppable({
    id: 'products',
  });
  const products = useSelector(selectProducts)

  console.log('isOver', isOver)

  return (
    <div className="menuEditor__right">
      <div className="menuEditor__panel menuEditor__productList">
        <h4 className="menuEditor__panel__title">
          Products {/*{{ 'form.menu_editor.products_panel.title'|trans }}*/}
        </h4>
        <SortableContext id="products" items={ products.map(p => p['@id']) } strategy={ verticalListSortingStrategy }>
          <div ref={ setNodeRef } className="menuEditor__panel__body" style={{ backgroundColor: isOver ? 'blue' : 'lightblue' }}>
            { products.map((product, index) => (
              <Product key={ `product-${index}` } product={ product } />
            )) }
          </div>
        </SortableContext>
      </div>
    </div>
  )
}

const Product = ({ product }) => {

  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
  } = useSortable({
    id: product['@id'],
    data: {
      type: 'product'
    }
  });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  };

  return (
    <div className="menuEditor__product" ref={setNodeRef} style={style} {...attributes} {...listeners}>
      { product.name }
    </div>
  )
}

const MenuEditor = ({ restaurant }) => {

  // const [ menu, setMenu ] = useState(defaultMenu)

  const dispatch = useDispatch()

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(MouseSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  useEffect(() => {
    dispatch(fetchProducts(restaurant));
  }, [dispatch])

  function handleDragEnd(event) {

    console.log('handleDragEnd', event)

    const { active, over } = event;

    /*
    if (active.id !== over.id) {

      // Items have been reordered
      const activeSection = active.data.current.sortable.containerId;
      const overSection = over.data.current.sortable.containerId;

      if (activeSection === overSection) {

        const sectionIndex = _.findIndex(menu.hasMenuSection, (s) => s['@id'] === activeSection);
        const items = menu.hasMenuSection[sectionIndex].hasMenuItem;

        const oldIndex = _.findIndex(items, (i) => i['@id'] === active.id);
        const newIndex = _.findIndex(items, (i) => i['@id'] === over.id);

        const newItems = arrayMove(items, oldIndex, newIndex);

        const newSections = menu.hasMenuSection.slice();

        newSections.splice(sectionIndex, 1, {
          ...menu.hasMenuSection[sectionIndex],
          hasMenuItem: newItems
        });

        const payload = {
          products: newItems.map((i) => i['@id'])
        }

        httpClient.put(activeSection, payload).then(({ error, response }) => {
          // TODO Do something in case of error
          console.log('error', error)
        })

        const newMenu = {
          ...menu,
          hasMenuSection: newSections,
        }

        setMenu(newMenu);
      }

    }
    */
  }

  function handleDragOver(event) {

    console.log('handleDragOver', event)

    const { active, over } = event;
    const { id } = active;
    const { id: overId } = over;

    const isOverProducts = overId === 'products' || over.data.current?.sortable?.containerId === 'products';
    const isOverProduct = over.data.current?.type === 'product';

    if (active.data.current.type === 'product') {
      if (isOverProducts) {
        // console.log(`drag over --> ${id} is over ${overId}`);
        dispatch(removeProductFromSection(id))
      } else if (isOverProduct) {
        console.log('Move to section')
      }

    }

    /*
    if (active.id !== over.id) {

      // Items have been reordered
      const activeSection = active.data.current.sortable.containerId;
      const overSection = over.data.current.sortable.containerId;

      if (activeSection === overSection) {

        const sectionIndex = _.findIndex(menu.hasMenuSection, (s) => s['@id'] === activeSection);
        const items = menu.hasMenuSection[sectionIndex].hasMenuItem;

        const oldIndex = _.findIndex(items, (i) => i['@id'] === active.id);
        const newIndex = _.findIndex(items, (i) => i['@id'] === over.id);

        const newItems = arrayMove(items, oldIndex, newIndex);

        const newSections = menu.hasMenuSection.slice();

        newSections.splice(sectionIndex, 1, {
          ...menu.hasMenuSection[sectionIndex],
          hasMenuItem: newItems
        });

        const payload = {
          products: newItems.map((i) => i['@id'])
        }

        httpClient.put(activeSection, payload).then(({ error, response }) => {
          // TODO Do something in case of error
          console.log('error', error)
        })

        const newMenu = {
          ...menu,
          hasMenuSection: newSections,
        }

        setMenu(newMenu);
      }

    }
    */
  }

  return (
    <DndContext
      onDragOver={handleDragOver}
      onDragEnd={handleDragEnd}
      sensors={sensors}
      collisionDetection={ /*closestCenter*/ closestCorners}>
      <div className="menuEditor mb-4">
        {/* TODO Add form input for menu name */}
        { menu ? <LeftPanel /> : null }
        { menu ? <RightPanel /> : null }
      </div>
    </DndContext>
  )
}

const container = document.getElementById('menu-editor');

const menu = JSON.parse(container.dataset.menu);

let preloadedState = {
  menu
};

const store = createStoreFromPreloadedState(preloadedState);

createRoot(container).render(
  <Provider store={ store }>
    <MenuEditor
      restaurant={ JSON.parse(container.dataset.restaurant) }
      defaultMenu={ JSON.parse(container.dataset.menu) } />
  </Provider>
)
