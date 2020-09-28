/**
 * https://codepen.io/zesht/pen/WNNeVvV
 * https://stackoverflow.com/questions/659508/how-can-i-shift-select-multiple-checkboxes-like-gmail
 * https://gist.github.com/AndrewRayCode/3784055
 */

/**
 1) Track where each click is
 2) Track if the user presses (and holds down) SHIFT
 3) Track where the next click is
**/
/**
    FUTURE FEATURE: De-select multiple boxes
        Update: If the checkbox is de-selected and then shift key is held down, the code
                        will uncheck all the boxes encompassed to the next checkbox clicked.
**/


const checkboxes = Array.from(document.querySelectorAll('[data-multi-select]'))

// http://stackoverflow.com/questions/11761881/javascript-dom-find-element-index-in-container

const multiSelect = {
    box : [
        undefined,  // first checked box
        undefined       // second checked box
    ],
    shift : false,
    addMore : true,
    selectMany: function(){
        let i = this.box[0], x = this.box[1];
        if( i > x ){
            for( ; x<i; x++ ){
                checkboxes[x].checked = this.addMore;
            }
        }
        if( i < x ){
            for( ; i<x; i++ ){
                // console.log( i );
                checkboxes[i].checked = this.addMore;
            }
        }
        this.box[0] = undefined;
        this.box[1] = undefined;
    }
};

for( let i=0, x=checkboxes.length; i<x; i++ ){
    checkboxes[i].addEventListener('click', function(){

        multiSelect.addMore = (this.checked)? true : false;

        if( multiSelect.shift ){
            multiSelect.box[1] = checkboxes.indexOf(this) // nodeList.indexOf( this.parentNode );
            multiSelect.selectMany();
        }else{
            multiSelect.box[0] = checkboxes.indexOf(this) // this //nodeList.indexOf( this.parentNode );
        }

    });
}

document.body.addEventListener('keydown', function(e){
    let key = window.event? event : e;
    if( !!key.shiftKey ){
        // http://stackoverflow.com/questions/7479307/how-can-i-detect-shift-key-down-in-javascript
        multiSelect.shift = true;
    }
});
document.body.addEventListener('keyup', function(e){
    let key = window.event? event : e;
    if( !key.shiftKey ){
        multiSelect.shift = false;
    }
});
