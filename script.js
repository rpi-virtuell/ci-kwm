
function tagesordnungspunkteNummerieren(){

    let n2 = 0,n3 = 0,n4 = 0;
    let hs = document.querySelectorAll('h2, h3, h4');
    for(const h of hs){

        if(h.tagName == 'H2'){
            n3=0;
            n2++;
            h.setAttribute('data-before',n2+' ');
        }
        if(h.tagName == 'H3'){
            n4=0;
            n3++;
            h.setAttribute('data-before',n2+'.'+n3+' ');
        }
        if(h.tagName == 'H4'){
            n4++;
            h.setAttribute('data-before',n2+'.'+n3+'.'+n4+' ');
        }


    }
}
tagesordnungspunkteNummerieren();
if(typeof wp != 'undefined'){
    wp.hooks.addFilter(
        'blocks.getSaveContent.extraProps',
        'kwm/heading-numbers',
        function (props){

            if(props.className && props.className.indexOf(' ')>0){
                let classes = props.className.split(' ');
                let check_classes = ['wp-block-column','kwmtop'];
                for (const cls of classes) {
                    if(check_classes.indexOf(cls) !== -1){
                        tagesordnungspunkteNummerieren();
                    }
                }
            }
            return props;
        }
    );

    wp.hooks.addFilter(
        'blocks.getSaveElement',
        'kwm/heading-numbers',
        function (props){

            let tops = ['h2','h3','h4','div'];
            if(props && props.type && tops.indexOf(props.type) !== -1){
                tagesordnungspunkteNummerieren();
            }
            return props;
        }
    );

}

