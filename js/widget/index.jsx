import React from 'react';
import ReactDom from 'react-dom';

class Widget extends React.Component {
    render() {
        return (<h1>Hello World</h1>);
    };
}

var widget = {
        render: (domElement, args) => {
            ReactDom.render(<Widget />, domElement);
        }
    }

export { widget };

