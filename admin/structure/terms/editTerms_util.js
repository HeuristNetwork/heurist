//OLD     

    /**
    * create/fill SELECT for terms or returns JSON array 
    *
    * datatype enum|relation
    * termIDTree - json string or object (tree) OR number - in this case this vocabulary ID, if not defined all terms are taken from window.hWin.HEURIST4.terms.treesByDomain
    * headerTermIDsList - json string or array (disabled terms)
    * defaultTermID - term to be selected
    * topOptions - text or array for top most item(s)
    * needArray  return array tree if terms (instead of select element)
    *
    */
    function createTermSelectExt(selObj, datatype, termIDTree, headerTermIDsList, defaultTermID, topOptions, needArray) {
        return createTermSelectExt2(selObj,
            {datatype:datatype, termIDTree:termIDTree, headerTermIDsList:headerTermIDsList,
             defaultTermID:defaultTermID, topOptions:topOptions, needArray:needArray, useHtmlSelect:false});
    }

    function createTermSelectExt2(selObj, options) {

        var datatype =  options.datatype,
            termIDTree =  options.termIDTree,  //all terms
            headerTermIDsList =  options.headerTermIDsList,  //non selectable
            defaultTermID =  options.defaultTermID,
            topOptions =  options.topOptions,
            needArray  =  (options.needArray===true),
            supressTermCode = options.supressTermCode,
            useHtmlSelect  = (options.useHtmlSelect===true),
            useIds  = (options.useIds===true),
            vocabsOnly  = (options.vocabsOnly===true);


        if(needArray){

        }else{
            selObj = window.hWin.HEURIST4.ui.createSelector(selObj, topOptions);
        }

        if(datatype=="relation" || datatype === "relmarker" || datatype === "relationtype"){
            datatype = "relation";
        }else{
            datatype = "enum";
        }

        var terms = window.hWin.HEURIST4.terms;
        if(!(datatype=="enum" || datatype=="relation") || !terms ){
            return needArray ?[] :selObj;
        }

        var termLookup = terms.termsByDomainLookup[datatype];

        //prepare header
        //
        var temp;
        try{
           temp = ( window.hWin.HEURIST4.util.isArray(headerTermIDsList)   //instanceof(Array)
            ? headerTermIDsList
            : (( typeof(headerTermIDsList) === "string" && !window.hWin.HEURIST4.util.isempty(headerTermIDsList) )
                ? $.parseJSON(headerTermIDsList)  //headerTermIDsList.split(",")
                : [] ));
        }catch(ex2){
           temp = [];
        }
                

        var headerTerms = {}; //non selectable
        for (var id in temp) {
            headerTerms[temp[id]] = temp[id];
        }

        //
        //
        var isNotFirefox = (navigator.userAgent.indexOf('Firefox')<0);

        function createSubTreeOptions(optgroup, parents, termSubTree, termLookupInner, defaultTermID) 
        {
            var termID;
            var localLookup = termLookupInner;
            var termName,
            termCode, hasImage,
            arrterm = [],
            reslist2 = [];

            for(termID in termSubTree) { // For every term in 'term'
                termName = "";
                termCode = "";

                if(localLookup[termID]){
                    termName = localLookup[termID][terms.fieldNamesToIndex['trm_Label']];
                    termCode = localLookup[termID][terms.fieldNamesToIndex['trm_Code']];
                    hasImage = localLookup[termID][terms.fieldNamesToIndex['trm_HasImage']];
                    if(supressTermCode || window.hWin.HEURIST4.util.isempty(termCode)){
                        termCode = '';
                    }else{
                        termCode = " [code "+termCode+"]";
                    }
                }

                if(window.hWin.HEURIST4.util.isempty(termName)) continue;

                arrterm.push([termID, termName, termCode, hasImage]);
            }

            //sort by name
            arrterm.sort(function (a,b){
                return a[1].toUpperCase()<b[1].toUpperCase()?-1:1;
            });



            var i=0, cnt= arrterm.length;
            for(;i<cnt;i++) { // For every term in 'term'

                termID = arrterm[i][0];
                termName = arrterm[i][1];
                termCode = arrterm[i][2];
                hasImage = arrterm[i][3];
                var termParents = '';
                var origName = arrterm[i][1];
                
                var depth = parents.length;

                /* not used anymore - replaced with jquery selecmenu
                if(isNotFirefox && (depth>1 || (optgroup==null && depth>0) )){
                    //for non mozilla add manual indent
                    var a = new Array( ((depth<7)?depth:7)*2 );
                    termName = a.join('. ') + termName;       
                }*/
                if(depth>0){
                    termParents = parents.join('.');
                }
                

                var isDisabled = (headerTerms[termID]? true:false);
                var hasChildren = ( typeof termSubTree[termID] == "object" && Object.keys(termSubTree[termID]).length>0 );
                var isHeader   = ((headerTerms[termID]? true:false) && hasChildren);
                var new_optgroup;

                //in FF optgroup is allowed on first level only - otherwise it is invisible

                if(isHeader && depth==0) { // header term behaves like an option group
                    //opt.className +=  ' termHeader';

                    if(selObj){

                        new_optgroup = document.createElement("optgroup");
                        new_optgroup.label = termName;
                        new_optgroup.depth = 0;

                        if(optgroup==null){
                            selObj.appendChild(new_optgroup);
                        }else{
                            optgroup.appendChild(new_optgroup);
                        }
                    }

                }else{

                    if(selObj){

                        var opt = new Option(termName+termCode, termID);
                        opt.className = "depth" + (depth<7)?depth:7;
                        //opt.depth = depth;
                        opt.disabled = isDisabled;
                        $(opt).attr('depth', depth)
                              .attr('term-img', hasImage?1:0);
                        if(useIds && termID>0){
                            $(opt).attr('entity-id', termID);
                        }
                        
                        
                        if(termParents!=''){
                            $(opt).attr('parents', termParents);
                            $(opt).attr('term-orig', origName);  
                            $(opt).attr('term-view', termName+termCode);
                        } 

                        if (termID == defaultTermID ||
                            termName == defaultTermID) {
                            opt.selected = true;
                        }

                        if(optgroup==null){
                            selObj.appendChild(opt);
                        }else{
                            optgroup.appendChild(opt);
                        }
                        new_optgroup = optgroup;
                    }
                }

                if(!vocabsOnly){
                
                    var children = [];
                    if(hasChildren){
                        var parents2 = parents.slice();
                        parents2.push(termName);      //depth+1
                        children = createSubTreeOptions( new_optgroup, parents2, termSubTree[termID], localLookup, defaultTermID);
                    }
                    var k=0, cnt2 = children.length, termssearch=[];
                    for(;k<cnt2;k++){
                        /*if(!children[k].disabled || children[k].children.length>0){
                        termssearch.push(children[k].id);
                        }*/
                        termssearch = termssearch.concat( children[k].termssearch );
                    }
                    if(!isDisabled){ //} || children.length>0){
                        termssearch.push(termID); //add itself
                    }

                    reslist2.push({id:termID, text:termName, depth:depth, disabled:isDisabled, children:children, termssearch:termssearch });
                    var parent = reslist2[reslist2.length-1];
                    for(k=0;k<cnt2;k++){
                        parent.children[k].parent = parent;
                    }
                    
                }
            } //for
            
            return reslist2;
        }//end internal function

        //
        //
        //
        var toparray = [];
        if(vocabsOnly){
            toparray = [0]; //Object.keys(terms.treesByDomain[datatype]);
        }else if(window.hWin.HEURIST4.util.isArray(termIDTree)){
            toparray = termIDTree;
        }else{
            toparray = [ termIDTree ]; //vocabulary
        }

        var m, lenn = toparray.length;
        var reslist_final = [];

        for(m=0;m<lenn;m++){

            var termTree = toparray[m];
            
            if(!window.hWin.HEURIST4.util.isempty(termTree)){

                //
                //prepare tree
                //
                if(window.hWin.HEURIST4.util.isNumber(termTree)){
                    //this is vocabulary id - show list of all terms for this vocab
                    var tree = terms.treesByDomain[datatype];
                    termTree = (termTree>0)?tree[termTree]:tree;
                }else{
                    try{
                        termTree = (typeof termTree == "string") ? $.parseJSON(termTree) : null;
                        if(termTree==null){
                            termTree = terms.treesByDomain[datatype];
                        }
                    }catch(ex2){
                    }
                }

                var reslist = createSubTreeOptions(null, [], termTree, termLookup, defaultTermID);
                if(!selObj){
                    reslist_final = reslist_final.concat( reslist);
                }
            
            }
        }

        if(selObj){
            if (!defaultTermID) selObj.selectedIndex = 0;
            
            //apply select menu
            selObj = window.hWin.HEURIST4.ui.initHSelect(selObj, useHtmlSelect);
            
            return selObj;
        }else{
            return reslist_final;
        }
    }
