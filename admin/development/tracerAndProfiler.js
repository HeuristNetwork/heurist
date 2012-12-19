/*
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 */

var JSTraceProfiler = function(){
    _level = 0;
    _metrics = [{ type: 'C',level: 0, name: "Construct Profile Object",text: null,time:(new Date()).getTime()}];
    _profiling = true;
    _outputTextArea = null;
    _maxMetrics = 2000;
    _overhead = 0;
    _displayEl = null;

    var that = {
        calcOverhead : function(){
            if (_metrics.length< _maxMetrics) _metrics.push({ type: 'O',level: _level, name: "Start Overhead Calc",text: null,time: (new Date()).getTime() });
            _overhead = 0;
            //function(strArg){ temp = strArg;}("test");  //dummy function to approximate the call and return time with parameterpassing
            if (_metrics.length< _maxMetrics) _metrics.push({ type: 'O',level: _level, name: "End Overhead Calc",text: null,time: (new Date()).getTime() });
            stop = _metrics.pop();
            start =  _metrics.pop();
            _overhead = stop.time - start.time;
        },

        start : function(strMsg){
            if (_metrics.length< _maxMetrics) _metrics.push({ type: 'B',level: _level, name: "Start Profile Run",text: strMsg,time: (new Date()).getTime() });
            _profiling = true;
        },

        stop : function(strMsg){
            if (_metrics.length< _maxMetrics) _metrics.push({ type: 'S',level: _level, name: "Stop Profile Run",text: strMsg,time: (new Date()).getTime() });
            _profiling = false;
        },

        mark : function(strName,strMsg){
            if(!_profiling) return;
            if (_metrics.length< _maxMetrics) _metrics.push({ type: 'M',level: _level, name: strName,text: strMsg,time: (new Date()).getTime() });
        },

        entry : function(strFuncName, strMsg) {
            if(!_profiling) return;
            _level++;
            if (_metrics.length< _maxMetrics) _metrics.push({ type: 'E',level: _level, name: strFuncName, text: strMsg ,time: (new Date()).getTime() });
        },

        exit : function(strFuncName, strMsg){
            if(!_profiling) return;
            if (_metrics.length< _maxMetrics) _metrics.push({type:'X',level: _level, name: strFuncName, text: strMsg, time: (new Date()).getTime()});
            _level--;
        },

        setOutput : function(element){ _outputTextArea = element; },

        createOutputPanel : function(ctrlBarEl,dispEl){
            //create control bar  - show Profile output, clearMetrics with text entry,  startProfile with text entry, stopProfile with text entry,
            //                      addMark - with 2 text entry fields, incrementProfileLevel - with 2 text entry fields, decrementProfileLevel - with 2 text entry fields,
            this.showButton = document.createElement("input");
            this.showButton.type = "button";
            this.showButton.value = "Show";
            this.showButton.style.fontWeight = "bold";
            this.showButton.style.marginBottom = "1em";
            this.showButton.style.display = "block";
            var thisRef = this;
            this.showButton.onclick = function() { thisRef.showOutput(); };
            ctrlBarEl.appendChild(this.showButton);
            this.hideButton = document.createElement("input");
            this.hideButton.type = "button";
            this.hideButton.value = "Hide";
            this.hideButton.style.fontWeight = "bold";
            this.hideButton.style.marginBottom = "1em";
            this.hideButton.style.display = "block";
            this.hideButton.onclick = function() { thisRef.hideOutput(); };
            ctrlBarEl.appendChild(this.hideButton);
            this.clearButton = document.createElement("input");
            this.clearButton.type = "button";
            this.clearButton.value = "Clear";
            this.clearButton.style.fontWeight = "bold";
            this.clearButton.style.marginBottom = "1em";
            this.clearButton.style.display = "inline";
            this.clearButton.onclick = function() { thisRef.clearMetrics("clear button pressed"); };
            ctrlBarEl.appendChild(this.clearButton);
            //create text area for output
            _outputTextArea = window.document.createElement("textarea");
            _outputTextArea.style.height ="100%";
            _outputTextArea.style.width ="100%";
            _outputTextArea.style.display = "block";
            dispEl.appendChild(_outputTextArea);
            _displayEl = dispEl;
        },

        clearMetrics : function(strMsg){
            _metrics=[{type:'R',level: _level=0, name: "Restart Profiler", text: strMsg, time: (new Date()).getTime()}];
        },

        showOutput : function(){
            var info = "", i=0, len = _metrics.length, curLevel=0, indent = "", outputText="Profile overhead per logged entry = " + _overhead + "\n",
                message = "", metric = null, entryStack = [],  lastBSTime = _metrics[0].time, enrtyMet = null, lastMetricTime = _metrics[0].time;
            for (;i<len;i++){
                metric = _metrics[i];
                mType = metric.type;
                switch (mType){
                    case 'B':
                    case 'S':
                        message = "T=" + (mType == 'B'? "Start ":"Stop ") + "L=" + metric.level + "  N= " + metric.name  + " elapsed time=" + (metric.time - lastBSTime) + "  M= " + (metric.text ? metric.text:"");
                        lastBSTime = lastMetricTime = metric.time;
                        break;
                    case 'E':
                        message = "T=Entry " +"L=" + metric.level + "  N= " + metric.name + " LM elapsed time=" + (metric.time - lastMetricTime) + "  M= " + (metric.text ? metric.text:"") ;
                        entryStack.push(metric);
                        lastMetricTime = metric.time;
                        break;
                    case 'X':
                        entryMet = entryStack.pop();
                        message = "T=Exit ";
                        if (!entryMet) {
                            message += "Error misalligned exit for " + metric.name + "\n" ;  //name mismatch error
                        }else{
                            if (metric.name != entryMet.name) message += "Error Name Mismatch= " + entryMet.name + " and " + metric.name ;  //name mismatch error
                            if (metric.level != entryMet.level) message += "Error Level Mismatch= " + entryMet.level + " and " + metric.level ; // level mismatch error
                            message += " L=" + metric.level + "  N= " + metric.name  + " FUNC elapsed time=" + (metric.time - entryMet.time) + "  M= " + (metric.text ? metric.text:"");
                        }
                        lastMetricTime = metric.time;
                        break;
                    case 'M':
                        message = "T=Mark " +"L=" + metric.level + "  N= " + metric.name + " LM elapsed time=" + (metric.time - lastMetricTime) + "  M= " +(metric.text ? metric.text:"") ;
                        lastMetricTime = metric.time;
                        break;
                    case 'C':
                    case 'R':
                    default:
                        message = "T=" + (mType == 'C'? "Construct ": mType == 'R' ? "Restart" :"Unknown ") +"L=" + metric.level + "  N= " + metric.name  + " time=" + metric.time + "  M= " +(metric.text ? metric.text:"");
                        lastMetricTime = metric.time;
                }
                outputText += message + "\n";

            }

            if (window.navigator.appName.match(/Microsoft/))_outputTextArea.innerText =  outputText;
            else _outputTextArea.innerHTML = outputText;
            _displayEl.style.display = "block";
        },

        hideOutput : function(){
            _displayEl.style.display = "none";
        }
    }
    return that;
}();

JSTraceProfiler.calcOverhead();