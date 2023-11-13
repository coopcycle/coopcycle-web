import React from 'react'
import ReactDOM from 'react-dom'
import { QueryBuilder } from '@cubejs-client/playground';
// import the antd styles from the `@cubejs-client/playground` package as it overrides some variables
import '@cubejs-client/playground/lib/antd.min.css';
// alternatively you can use the default antd styles
// import 'antd/dist/antd.min.css';

const el = document.getElementById('cubejs-playground');

if (el) {

  const query = {
    measures: ['Order.count'],
    dimensions:  ['Order.state']
  };

  ReactDOM.render(
    <QueryBuilder
      apiUrl={ el.dataset.apiUrl }
      token={ el.dataset.token }
      initialVizState={{
        query
      }}
    />, el)

}
