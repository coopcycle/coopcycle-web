import React from 'react'
import ReactDOM from 'react-dom';

class CartPortal extends React.Component {
  constructor(props) {
    super(props);
    this.el = document.querySelector('[id="bs-example-navbar-collapse-1"]');

  }

  render() {
    return ReactDOM.createPortal(
      this.props.children,
      this.el,
    );
  }
}

export default CartPortal
