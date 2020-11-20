/*
* Copyright (C) 2005-2020 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* temporal.js
* Temporal Object Library V 1.0
*
* This file contains Objects for dealing with temporals. Temporals are a robust representation
* of date - time information allowing for uncertainty and imprecision.
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

var g_version = "1";


function Temporal (strInitTemporal) {
    //private members
    var _className = "Temporal";
    var _ver = g_version;				//version number for data representation
    var _type = null;					// type of temporal with the default to be a simple date
    var _strTemporal = strInitTemporal;
    var _dates = {};
    var _durations = {};
    var _fields = {};

    function _init (){	//initailization code
        if (!_strTemporal) { // empty string return basic temporal
            return;
        }
        Temporal.parse(that, _strTemporal);
    }

    //public members
    var that = {
        clear: function (str) {  // clears the temporal to a basic format
            _ver = g_version;
            _type = null;
            _strTemporal = ( str && typeof str === "string" ? str : "");
            _dates = {};
            _durations = {};
            _fields = {};
        },

        // function toString  returns an encode serialized string representation of the temporal
        toString: function () {
            var temp = "|VER=" + _ver + "|TYP=" + _type;
            var i;
            for ( i in _dates) {
                temp += "|" + i + "=" + _dates[i].toString();
            }
            for ( i in _durations) {
                temp += "|" + i + "=" + _durations[i].toString();
            }
            for ( i in _fields) {
                temp += "|" + i + "=" + _fields[i];
            }
            return temp;
        },

        getField: function (code) {
            if ( _fields[code] ) {
                return _fields[code];
            } else {
                return null;
            }
        },

        getTDuration: function (code) {
            if ( _durations[code] ) {
                return _durations[code];
            } else {
                return null;
            }
        },

        getTDate: function (code) {
            var a = _dates;
            if ( _dates[code] ) {
                return _dates[code];
            } else {
                return null;
            }
        },

        getOrigString: function () {
            return _strTemporal;
        },

        getVersion: function () {
            return _ver;
        },

        getType: function () {
            return _type;
        },

        setType: function (type) {
            type = type.toLowerCase();
            if ( type !== "u" && type !== "s" && type !== "c" && type !== "f" && type !== "p" && type !== "d") {
                return null;
            }

            return (_type = type);
        },

        setField: function (code,value) {
            if ( Temporal.fieldsDict[code] && value && typeof value.toString === "function") {
                _fields[code] = value;
                return true;
            }
            return false;
        },

        removeField: function (code) {
            if ( Temporal.fieldsDict[code] && typeof _fields[code] === "object" ) {
                var temp = _fields[code];
                delete _fields[code];
                return temp;
            }
            return false;
        },

        setTDuration: function (code, tDur) {
            if ( Temporal.tDurationDict[code] ) {
                if (typeof tDur === "object" && typeof tDur.getClass === "function" && tDur.getClass() === "TemporalDuration") {
                    var temp = null;
                    if ( _durations[code] ) {
                        temp = _durations[code];
                    }
                    _durations[code] = tDur;
                    return temp;
                } else {
                    throw "Temporal Exception - setTDuration called with invalid TDuration";
                }
            }
            return false;
        },

        removeTDuration: function (code) {
            if ( Temporal.tDurationDict[code] && typeof _durations[code] === "object" ) {
                var temp = _durations[code];
                delete _durations[code];
                return temp;
            }
            return false;
        },

        setTDate: function (code,tDate) {
            if ( Temporal.tDateDict[code] ) {
                if (typeof tDate === "object" && typeof tDate.getClass === "function" && tDate.getClass() === "TemporalDate") {
                    var temp = null;
                    if ( _dates[code] ) {
                        temp = _dates[code];
                    }
                    _dates[code] = tDate;
                    return temp;
                } else {
                    throw "Temporal Exception - setTDate called with invalid TDate";
                }
            }
            return false;
        },

        removeTDate: function (code) {
            if ( Temporal.tDateDict[code] && typeof _dates[code] === "object" ) {
                var temp = _dates[code];
                delete _dates[code];
                return temp;
            }
            return false;
        },

        getStringForCode: function (code) {
            if ( typeof code === "string") {
                if ( typeof Temporal.tDateDict[code] === "string" && _dates[code]) {
                    return _dates[code].toString();
                }
                if ( typeof Temporal.tDurationDict[code] === "string" && _durations[code]) {
                    return _durations[code].toString();
                }
                if ( typeof Temporal.fieldsDict[code] === "string" && _fields[code]) {
                    return _fields[code];
                }
            }
            return "";
        },

        addObjForString: function (code, value) {
            if ( typeof code === "string") {
                if (typeof value !== "string") {
                    throw "Temporal Exception - non string value passed to addObjForString - type " + typeof value;
                }
                if ( typeof Temporal.tDateDict[code] === "string" ) {
                    _dates[code] = TDate(value);
                    return _dates[code];
                }
                if ( typeof Temporal.tDurationDict[code] === "string" ) {
                    _durations[code] = TDuration(value);
                    return _durations[code];
                }
                if ( typeof Temporal.fieldsDict[code] === "string" ) {
                    _fields[code] = value;
                    return _fields[code];
                }
                throw "Temporal Exception - invalid code passed to addObjForString - code " + code;
            }
        },

        removeObjForCode: function (code) {
            if ( typeof code === "string") {
                if ( typeof Temporal.tDateDict[code] === "string" ) {
                    delete _dates[code];
                }
                if ( typeof Temporal.tDurationDict[code] === "string" ) {
                    delete _durations[code];
                }
                if ( typeof Temporal.fieldsDict[code] === "string" ) {
                    delete _fields[code];
                }
            }
        },

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            if (strClass === _className) {
                return true;
            }
            return false;
        }

    };  // end that

    _init();		// initialize before returning
    return that;	// returning "that"  keeps all variables due to closure
}

// helper function that takes the current strTemporal representation and sets the members to the contained values
Temporal.parse = function () {
    // if there are no arguments nothing to do
    if (!arguments.length) {
        return null;
    }
    //setup  input arguments
    var temporal, str;
    if (arguments.length>1) {
        temporal = arguments[0];
        if ( typeof temporal.getClass !== "function" || temporal.getClass() !== "Temporal") {
            throw "Temporal parse exception - invalid arguements passed - more than one arg requires a Temporal object first";
        }
        str = arguments[1];
    } else {
        temporal = new Temporal();
        str = arguments[0];
    }

    // if a null or empty string passed in then return an empty Temporal or the passed in Temporal object.
    if (!str || str === "" ) {
        return temporal;
    }

    // if not a valid temporal string try to see if this is a recognisable date string
    var fieldType;
    if ( !Temporal.isValidFormat(str)) {
        if(!(str.indexOf("VER=")>=0 || str.indexOf("TYP=")>=0)){
            try {
                var tDate = TDate.parse(str);
                if(tDate) { fieldType = "DAT"; }	// save string as a TDate object
            }
            catch(e) {	// save string in COM field and keep an empty simple date temporal
            }
        }
        if(fieldType !== "DAT"){
            if(str.replace(/\|VER=(\d)*\|TYP=./,'')==="") {
                return; //empty temporal string
            }
            fieldType = "COM";
        }
        temporal.clear(str);  // clear out all the oold stuff
        temporal.setType("s");
        temporal.addObjForString(fieldType,str);
        return temporal;
    }

    temporal.clear(str);  // reset temporal to empty with ver set to current version and store original string
    str = str.replace(/^\|/,"");
    str = str.replace(/VER=[^\|]+\|?/,"");

    var type = str.match(/TYP=(.)\|?/); // match for TYP followed by a single letter defining the temporal type followed optionally by a | vertical bar
    if (type && type.length === 2) {
        if ( temporal.setType(type[1])) {
            str = str.replace(/TYP=[^\|]+\|?/,"");
            type = type[1];
        } else {
            throw "Temporal parse exception - invalid Temporal Type code - " + type[0];
        }
    } else {
        throw "Temporal parse exception - no Temporal Type code ";
    }

    var fields = Temporal.getFieldsForString(type,str);  // get the list of field headers for this type
    var val,i;
    for (i=0; i < fields.length; i++) {
        val = str.match(new RegExp("\\|?" + fields[i] + "=([^\\|]+)","i"));
        if (val && val.length === 2) {
            temporal.addObjForString(fields[i],val[1]);
        }
    }
    return temporal;
};


Temporal.typeDict = {"s" :	"Simple Date",
    "c"	:	"C14 Date",
    "f"	:	"Approximate Date",
    "p"	:	"Probability Date Range",
    "d"	:	"Duration"
};

Temporal.fieldsDict = {	"VER"	:	"Version Number",
    "TYP"	:	"Temporal Type Code",
    "PRF"	:	"Probability Profile",					// FIXME: add definitions
    "SPF"	:	"Start Profile",					// FIXME: add definitions
    "EPF"	:	"End Profile",					// FIXME: add definitions
    "CAL"	:	"Calibrated",
    "COD"	:	"Laboratory Code",
    "DET"	:	"Determination Type",
    "COM"	:	"Comment",
    "EGP"	:	"Egyptian Date",
    "CLD"   :   "Calendar",
    "CL2"   :   "Non-gregorian value",  //value in calendar value
};

Temporal.determination = {	0	:	"Unknown",
    1	:	"Attested",
    2	:	"Conjecture",
    3	:	"Measurement"
};

Temporal.profiles = {	0	:	"Flat",
    1	:	"Central",
    2	:	"Slow Start",
    3	:	"Slow Finish"
};

Temporal.tDateDict = {	"DAT"	:	"ISO DateTime",
    "BPD"	:	"Before Present (1950) Date",
    "BCE"	:	"Before Current Era",
    "TPQ"	:	"Terminus Post Quem",
    "TAQ"	:	"Terminus Ante Quem",
    "PDB"	:	"Probable begin",
    "PDE"	:	"Probable end",
    "SRT"	:	"Sortby Date"
};

Temporal.tDurationDict = {	"DUR"	:	"Simple Duration",
    "DEV"	:	"Standard Deviation",
    "DVP"	:	"Deviation Positive",
    "DVN"	:	"Deviation Negative",
    "RNG"	:	"Range",
    "ERR"	:	"Error Margin"
};

Temporal._typeFieldMap = {	s : {
        req : [["DAT"]],
        //											[]],		// empty date allows to capture ill-formed date strings
        opt : ["COM","DET","CLD","CL2"],
        hdr : ["DAT"]
    },
    c :	{
        req : [["DVP","DVN","BPD","COD"],
            ["DEV","BPD","COD"],
            ["DVP","DVN","BCE","COD"],
            ["DEV","BCE","COD"]],
        opt : ["CAL","DET","COM","SRT"],
        hdr : ["DVP","DVN","BCE","BPD","COD","DEV","DAT"]
    },
    p :	{
        req : [["PDB","PDE","TPQ","TAQ"],["TPQ","TAQ"]],
        opt : ["DET","SPF","EPF","COM","SRT","CLD","CL2"],
        hdr : ["PDB","PDE","TPQ","TAQ"]
    },
    f :	{
        req : [["DAT","RNG"]],
        opt : ["DET","PRF","COM","SRT","CLD","CL2"],
        hdr : ["DAT","RNG"]
    },
    d :	{
        req : [["DUR"]],
        opt : ["DET","ERR","COM","CLD","CL2"],
        hdr : ["DUR"]
    }
};

Temporal.cloneObj = function(obj) {

    function isArray(a)
    {
        return Object.prototype.toString.apply(a) === '[object Array]';
    }

    //return eval($.toJSON(o));
    if(typeof(obj) !== "object") return obj;

    if(obj === null) return obj;

    if(isArray(obj)){
        var newA = [];
        for(var i in obj) newA.push(Temporal.cloneObj(obj[i]));
        return newA;
    }else{
        var newO = {};
        for(var i in obj) newO[i] = Temporal.cloneObj(obj[i]);
        return newO;
    }
};

Temporal.typeFieldMap = function (type) {
    return Temporal.cloneObj(Temporal._typeFieldMap[type]);
};

/**
 *  isValidFormat is a static member function of the Temporal Class which
 *  checks a string to see if it is a validly formatted temporal string.
 *  Specifically it must have a recognised version number, a type specified,
 *  all require fields for the type and no non-required or non optional fields
 *  for the type.
 *
 *  @param   string str  the Temporal string to checked for validity
 *  @return  boolean true or false.
 */
Temporal.isValidFormat = function ( str ) {
    if (str.search(/\|/) === -1) {
        return false;
    }
    var type = str.match(/TYP=(.)/)[1],
    map = Temporal.typeFieldMap(type),
    headers = str.match(/\D\D\D=/g).join(""),
    i,j;
    if (headers.search("VER=") !== -1) { // must have a version number
        headers = headers.replace("VER=","");
        if (headers.search("TYP=") !== -1) { // must have a type
            headers = headers.replace("TYP=","");
            for (i=0; i < map.req.length; i++) { // for each required fields pattern
                var temp = headers,
                valid = true,
                r1 = map.req[i];
                for (j=0; j < r1.length; j++) { // must have all require fields
                    if (temp.search(r1[j] + "=") !== -1) {
                        temp = temp.replace(r1[j] + "=","");
                    } else {
                        valid = false;
                        break;
                    }
                }
                if (valid) {  // any fields left should be optional
                    for (j=0; j < map.opt.length; j++) {
                        temp = temp.replace(map.opt[j] + "=","");
                    }
                    if ( temp.search(/\S/) === -1) { //  should be nothing left, anything left is unspecified and therefore illegal
                        return true;
                    }
                }
            }
        }
    }
    return false;
};

// returns array
//  0 - valid
//  1 - missed required fields
//  2 - optional fields
//  3 - error message
Temporal.checkValidity = function ( temporal ) {
    if (!temporal || !temporal.isA || !temporal.isA("Temporal") || temporal.getVersion()>g_version) {
        return false;
    }
    var type = temporal.getType();
    var map = Temporal.typeFieldMap(type);
    var ret = [false,[],[],""];
    var headers = temporal.toString().match(/\D\D\D=/g).join("");
    var i,j;
    headers = headers.replace("VER=","");
    headers = headers.replace("TYP=","");
    for (i=0; i < map.req.length; i++) { // for each required fields pattern
        var temp = headers;
        var valid = true;

        for (j=0; j < map.req[i].length; j++) { // must have all require fields
            if (temp.search(map.req[i][j] + "=") !== -1) {
                temp = temp.replace(map.req[i][j] + "=","");//remove from search
            } else {
                //req field not found
                valid = false;
                if (i < map.req.length-1){
                    break;  //ignore if there are several sets
                } else {
                    //add missed fields for last set
                    ret[1].push(map.req[i][j]); // on min req set save any codes required to complete the min set
                }
            }
        }

        if (valid) {  // remove any optional fields
            for (j=0; j < map.opt.length; j++) {
                temp = temp.replace(map.opt[j] + "=","");
            }
            if ( temp.length > 1) { //  caller wants the list of extra codes in the valid case.
                temp = temp.replace(/=$/,"");
                ret[2] = temp.split("=");
            }
            ret[1] =[];
            break;
        }
    }

    if (valid) { //do type specific checking here
        switch ( type ) {
            case 'p':
            if (temporal.getTDate("PDB") && temporal.getTDate("TPQ").compare(temporal.getTDate("PDB")) > 0) {
                ret[3] = "Probability type temporals require that TPQ is before or the same as PB.";
            }else if (temporal.getTDate("PDE") && temporal.getTDate("TAQ").compare(temporal.getTDate("PDE")) < 0) {
                ret[3] = "Probability type temporals require that TAQ is after or the same as PE.";
            }else{
                ret[0] = true;
            }
            break;
            case 'f':
            case 'c':
            case 'd':
            case 's':
            default:
                ret[0] = true;
        }
    }
    return ret;
}

Temporal.getTypeReq = function (type) {
    if ( type && typeof type === "string" && typeof Temporal.typeFieldMap(type) === "object") {
        return Temporal.typeFieldMap(type).req;
    } else {
        throw "Temporal Exception - invalid temporal type passed to getTypeReq - " + type;
    }
}

Temporal.getTypeOpt = function (type) {
    if ( type && typeof type === "string" && typeof Temporal.typeFieldMap(type) === "object") {
        return Temporal.typeFieldMap(type).opt;
    } else {
        throw "Temporal Exception - invalid temporal type passed to getTypeOpt - " + type;
    }
}

Temporal.getFieldsForType = function (type) {
    if ( type && typeof type === "string" && typeof Temporal.typeFieldMap(type) === "object") {
        return 	Temporal.typeFieldMap(type).hdr.concat(Temporal.typeFieldMap(type).opt);
    } else {
        throw "Temporal Exception - invalid temporal type passed to getFieldsForType - " + type;
    }
}

Temporal.getStringForCode = function (code) {
    if (Temporal.fieldsDict[code]) {
        return Temporal.fieldsDict[code];
    }
    if (Temporal.tDateDict[code]) {
        return Temporal.tDateDict[code];
    }
    if (Temporal.tDurationDict[code]) {
        return Temporal.tDurationDict[code];
    }
    return "UNKNOWN FIELD CODE";
}

Temporal.getFieldsForString = function (type,str) {
    if (!str) {
        return "";
    }
    if ( type && typeof type === "string" && typeof Temporal.typeFieldMap(type) === "object") {
        var fields;
        var map = Temporal.typeFieldMap(type);
        var headers = str.match(/[A-Za-z][A-Za-z][A-Za-z]=/g).join("");
        if (!headers) {  // no valid headers - requires 3 letter
            return "";
        }
        var temp;
        for (var i=0; i < map.req.length; i++) {  //for each required fields combination/pattern
            var temp = headers,
            valid = true;
            for (var j=0; j < map.req[i].length; j++) { //check that all required fields for this pattern are present
                if (temp.search(new RegExp(map.req[i][j] + "=","i")) !== -1) {
                    temp = temp.replace(map.req[i][j] + "=","");
                } else {
                    valid = false;
                    break;
                }
            }
            if (valid) {
                fields = map.req[i];
                break;
            }
        }
        if (!valid) {
            throw "Temporal Exception - invalid temporal string passed to getFieldsForString - " + str;
        }
        // a pattern of required fields was found - now add all remaining headers that are in the optional for this type
        for (var j=0; j < map.opt.length; j++) {
            if (temp.search(map.opt[j] + "=") !== -1) {
                fields.push(map.opt[j]);
            }
        }
        return fields;
    } else {
        throw "Temporal Exception - invalid temporal type passed to getFieldsForString - " + type;
    }
}


// Temporal Date object extend by date.js
var TDate = function (strDate) {
    //private members
    var _className = "TemporalDate";
    var _origString = strDate && strDate.toString ? strDate.toString() : "";
    var _dateFormat = "ymd";
    var _year = null;
    var _month = null;
    var _day = null;
    var _hours = null;
    var _minutes = null;
    var _seconds = null;
    var _milliseconds = null;
    var _milliSep = null;
    var _tzOffset = null;
    var _tz = null;


    //public members
    var that = {

        clear: function () {
            _origString = null;
            _dateFormat = "ymd";
            _year = null;
            _month = null;
            _day = null;
            _hours = null;
            _minutes = null;
            _seconds = null;
            _milliseconds = null;
            _tzOffset = null;
            _tz = null;
        },

        getOrigString: function () {
            return _origString;
        },

        getYear: function () {
            return _year;
        },

        getMonth: function () {
            return _month;
        },

        getDay: function () {
            return _day;
        },

        getHours: function () {
            return _hours;
        },

        getMinutes: function () {
            return _minutes;
        },

        getSeconds: function () {
            return _seconds;
        },

        getMilliseconds: function () {
            return _milliseconds;
        },

        getTimezoneOffset: function () {
            return "" + _tzOffset ;
        },

        toString : function (format) {
            var frmPart = function (s,fillLength) {
                if (!s) {
                    return "";
                }
                var neg = s<0;
                if (neg) {
                    s = s.replace(/\-/,"");
                    if(fillLength==4) fillLength=6; //special case - required 6 digits for visjs timeline
                }
                while (s.toString().length < fillLength) {
                    s = "0" + s;
                }
                if (neg) {
                    s = "-" + s;
                }
                return s;
            };

            if (!format) {
                format = "yyyy-MM-dd HH:mm:ssz";  // set default format to universally searchable format
            }

            return  format.replace(/[\s\-\/]?dd?d?d?|[\s\-\/]?MM?M?M?|yy?y?y?y?y?|[\sT]?hh?|[\sT]?HH?|[\s\.,:]?mm?|[\s\.,:]?ss?s?|\s?tt?|\s?zz?z?/g, function (format) {
                    switch (format) {
                        case " hh":
                        case "Thh":
                        case "hh":
                            return _hours ? (format.length === 3? format[0] : "") + frmPart((_hours < 13 ? _hours : (_hours - 12)),2) : "";
                        case " h":
                        case "Th":
                        case "h":
                            return _hours ? (format.length === 2? format[0] : "") + (_hours < 13 ? _hours : (_hours - 12)) : "";
                        case " HH":
                        case "THH":
                        case "HH":
                            return _hours ? (format.length === 3? format[0] : "") + frmPart(_hours,2) : "";
                        case " H":
                        case "TH":
                        case "H":
                            return _hours ? (format.length === 2? format[0] : "") + _hours : "";
                        case " mm":
                        case ":mm":
                        case ".mm":
                        case ",mm":
                        case "mm":
                            return _minutes ? (format.length === 3? format[0] : "") + frmPart(_minutes,2) : "";
                        case " m":
                        case ":m":
                        case ".m":
                        case ",m":
                        case "m":
                            return _minutes ? (format.length === 2? format[0] : "") + _minutes: "";
                        case " ss":
                        case ":ss":
                        case ".ss":
                        case ",ss":
                        case "ss":
                            return _seconds ? (format.length === 3? format[0] : "") + frmPart(_seconds,2) : "";
                        case " s":
                        case ":s":
                        case ".s":
                        case ",s":
                        case "s":
                            return _seconds ? (format.length === 2? format[0] : "") + _seconds : "";
                        case " sss":
                        case ":sss":
                        case ".sss":
                        case ",sss":
                            return _milliseconds ? format[0] + _milliseconds : "";
                        case "sss":
                            return _milliseconds ? (_milliSep ? _milliSep : ".") + _milliseconds : "";
                        case "yyyyyy":
                        case "yyyy":
                        case "yyy":
                        case "yy":
                        case "y":
                            return _year ? frmPart(_year, format.length) : "";
                        case "dddd":
                            return _year && _month && _day ? TDate.getDayName(_year, _month, _day) : "";
                        case "ddd":
                            return _year && _month && _day ? TDate.getDayName(_year, _month, _day,true) : "";
                        case " dd":
                        case "-dd":
                        case "/dd":
                        case "dd":
                            return _day ? (format.length === 3? format[0] : "") + frmPart(_day,2) : "";
                        case " d":
                        case "-d":
                        case "/d":
                        case "d":
                            return _day ? (format.length === 2? format[0] : "") + _day : "";
                        case " MMMM":
                        case "MMMM":
                            return _month ? TDate.getMonthName(_month) : "";
                        case " MMM":
                        case "MMM":
                            return _month ? ((format.length === 4? format[0] : "") + TDate.getMonthName(_month,true)) : "";
                        case " MM":
                        case "-MM":
                        case "/MM":
                        case "MM":
                            return _month ? (format.length === 3 && _year ? format[0] : "") + frmPart(_month,2) : "";
                        case " M":
                        case "-M":
                        case "/M":
                        case "M":
                            return _month ? (format.length === 2? format[0] : "") + _month : "";
                        case " t":
                        case "t":
                            return  (format[0] === " " ? " " : "") + (_hours < 12 ? "A" : "P");
                        case " tt":
                        case "tt":
                            return  (format[0] === " " ? " " : "") + (_hours < 12 ? "AM" : "PM");
                        case " zzz":
                        case "zzz":	//output Zone
                            return (format[0] === " " ? " " : "") + (_tz ? _tz : "Z");
                        case " zz":
                        case "zz":  //output offset
                            return (format[0] === " " ? " " : "") + (_tzOffset ? "GMT" + _tzOffset : '');
                        case " z":
                        case "z":
                            return (format[0] === " " ? " " : "") 
                                +(_tzOffset ? ( (_tzOffset.indexOf('-')==0?'':'+') + _tzOffset) : "");
                        default:
                            return "";
                    }
            });
        },

        setTDate: function (date) {
            if (typeof date === "string") {  // should be and encode string
                TDate.parse(date);
            } else if (typeof date === "object" && typeof date.getClass === "function" && date.getClass() === "TemporalDate") {  //
                _origString = date.getOrigString();
                _year = date.getYear();
                _month = date.getYear();
                _day = date.getYear();
                _hour = date.getYear();
                _minute = date.getYear();
                _second = date.getYear();
                _millisecond = date.getYear();
                that.setTimezone(date.getTimezone());
            } else {
                return null;
            }
            return that;
        },

        setTDateFormat: function (str) {
            if ( str && str.length ===3 ) {
                _dateFormat = str;
            }
        },

        setOrigString: function (str) {
            _origString = str;
        },

        setYear: function (str) {
            if ( str !== null && str !== "" ) {
                if ( isNaN(str)|| !TDate.validateYear(Number(str))) {
                    throw " TDate exception - invalid string supplied to setYear() - " + str;
                }
            }
            _year = str;
        },

        setMonth: function (str) {
            if ( str !== null && str !== "" ) {
                if ( isNaN(str) || !TDate.validateMonth(Number(str))) {
                    throw " TDate exception - invalid string supplied to setMonth() - " + str;
                }
            }
            _month = str;
        },

        setDay: function (str) {
            if ( str !== null && str !== "" ) {
                if ( isNaN(str) || !TDate.validateDay(Number(str),_year ,_month)) {
                    throw " TDate exception - invalid string supplied to setDay() - " + str;
                }
            }
            _day = str;
        },

        setHours: function (str) {
            if ( str !== null && str !== "" ) {
				if(Number(str)==24) { str = '0'; }
                if ( isNaN(str) || !TDate.validateHour(Number(str))) {
                    throw " TDate exception - invalid string supplied to setHours() - " + str;
                }
            }
            _hours = str;
        },

        setMinutes: function (str) {
            if ( str !== null && str !== "" ) {
                if ( isNaN(str) || !TDate.validateMinute(Number(str))) {
                    throw " TDate exception - invalid string supplied to setMinutes() - " + str;
                }
            }
            _minutes = str;
        },

        setSeconds: function (str) {
            if ( str !== null && str !== "" ) {
                if ( isNaN(str) || !TDate.validateSecond(Number(str))) {
                    throw " TDate exception - invalid string supplied to setSeconds() - " + str;
                }
            }
            _seconds = str;
        },

        setMilliseconds: function (str, sep) {
            if ( str !== null && str !== "" ) {
                if ( isNaN(str) || !TDate.validateMillisecond(Number(str))) {
                    throw " TDate exception - invalid string supplied to setMilliseconds() - " + str;
                }
            }
            _milliseconds = str;
            if (sep && sep.match(/^[\s\.,:]$/)) {
                _milliSep = sep;
            }
        },

        setTimezoneOffset: function (str) {
            var h = str.match(/^\s*(?:UTC|GMT)?([\+|\-])(\d\d):?(\d\d)?/);
            if (!h || !h[0] || !h[2] || h[2] > 23 || h[3] > 59) {
                _tzOffset = '00:00';
                //throw " TDate exception - invalid string supplied to setTimezone() - " + str;
            } else {
                _tzOffset = ( ( h[1] === "-" ? "-" : "+") + h[2] + (h[3] ? h[3] : "") );
                if(_tzOffset && _tzOffset.length>0 && _tzOffset.indexOf(":")<0){
                    _tzOffset = _tzOffset+":00";
                }
            }
        },

        compare: function (tDate2) {
            var ret;
            function comp (a,b) {
                a = parseInt(isNaN(a)?0:a);
                b = parseInt(isNaN(b)?0:b);
                if (a>b) return 1;
                if (a<b) return -1;
                return 0;
            }
            ret = comp(this.getYear(), tDate2.getYear());
            if (ret === 0) {
                ret = comp(this.getMonth(), tDate2.getMonth());
                if (ret === 0) {
                    ret = comp(this.getDay(), tDate2.getDay());
                    if (ret === 0) {
                        ret = comp(this.getHours(), tDate2.getHours());
                        if (ret === 0) {
                            ret = comp(this.getMinutes(), tDate2.getMinutes());
                            if (ret === 0) {
                                ret = comp(this.getSeconds(), tDate2.getSeconds());
                                if (ret === 0) {
                                    ret = comp(this.getMilliseconds(), tDate2.getMilliseconds());
                                }
                            }
                        }
                    }
                }
            }
            return ret;
        },

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            if (strClass === _className) {
                return true;
            }
            return false;
        }

    };  // end that

    //init and binding code

    if (typeof _origString === "string" && _origString.match(/\S/)) {
        TDate.parse(that,_origString);
    }
    return that;
}


// static function  parse() - parses a string assuming ISO format precision and set Date
//called like myTDate  = TDate.parse("1952/04/16 14:05"); or myTDate = new TDate(); .... TDate.parse(myTDate,"1952/04/16 14:05");
TDate.parse = function () {
    // if there are no arguments nothing to do
    if (!arguments.length) {
        return null;
    }
    //setup  input arguments
    var tDate, str;
    if (arguments.length>1) {
        tDate = arguments[0];
        if ( typeof tDate.getClass !== "function" || tDate.getClass() !== "TemporalDate") {
            throw "TDate parse exception - invalid arguements passed - more than one arg requires a tDate object first";
        }
        str = arguments[1];
    } else {
        tDate = new TDate();
        str = arguments[0];
    }

    // if a null string passed in then return an empty TDate or the passed in TDate object.
    if (!str || str === "") {
        return tDate;
    }

    //2 special cases
    if("today"===str.toLowerCase()){

        var date = new Date();
        var d = date.getDate();
        var m = date.getMonth()+1;
        var y = date.getFullYear();
        str = '' + y +'-'+ (m<=9?'0'+m:m) +'-'+ (d<=9?'0'+d:d);
        //str = (new Date()).toDateString();
    }else if("tomorrow"===str.toLowerCase()){
        var date = new Date();
        var d = date.getDate()+1;  // TODO: will give wrong date at end of month
        var m = date.getMonth()+1;
        var y = date.getFullYear();
        str = '' + y +'-'+ (m<=9?'0'+m:m) +'-'+ (d<=9?'0'+d:d);
        //str = (new Date()).toDateString();
    }else if("yesterday"===str.toLowerCase()){
        var date = new Date();
        var d = date.getDate()-1; // TODO: will give wrong date at beginning of month
        var m = date.getMonth()+1;
        var y = date.getFullYear();
        str = '' + y +'-'+ (m<=9?'0'+m:m) +'-'+ (d<=9?'0'+d:d);
        //str = (new Date()).toDateString();
    }else if("now"===str.toLowerCase()){
        str = (new Date()).toString();
    }


    var temp = str.replace(/(GMT|UTC)/,"");   //remove  GMT or UTC marker
    temp = temp.replace(/\s+/g," ");	//compress multiple spaces into a single space
    temp = temp.replace(/\s*\([^\)]+\)\s*$/,""); //remove any Timezone adorment like (AUS  Eastern Daylight Time)
    temp = temp.replace(/\s*(sun|mon|tues?|wed(nes)?|thur?s?|fri)(day)?\.?,?\s*/i,"");  //remove any day indicators
    temp = temp.replace(/([012]?\d)\s*(th|rd|nd|st)(\s*of)?/i,"$1d");
    temp = temp.replace(/\s+/g," ");	//compress multiple spaces into a single space
    if (temp.match(/\d\d?\s*(?:am|pm)/i)) {  //  12 hour clock need to convert
        var t12 = temp.match(/(?:\s+(\d\d?)([:\.,]\d\d?\d?){0,3})\s*(am|pm)/i);
        if ( t12[3].match(/pm/i) ) {
            t12[1] = (12 + (t12[1]-0)).toString() + (t12[2] ? t12[2] : "");
        }
        temp = temp.replace(/\d\d?([:\.,]\d\d?\d?){0,3}\s*(am|pm)/i, t12[1]);
    }

    var periodDesignator = "";
    var BCE = false;
    p = temp.match(/(?:\b|\d)(bce|ce|bc|ad)\b/i);
    if (p){
        temp = temp.replace(p[1], '');
        periodDesignator = p[1].toUpperCase();
        if (p[1][0].toLowerCase() == "b"){
            BCE = true;
        }
    }

    var t = temp.match(/\b[^\.\-\d:,\s\+\/]/);
    while (t && t[0] && t[0] !== "T") { // possible word for month
        var word = temp.match(/^\s*(?:\-?\d+(?:d|m)?[\.\-:,\s\+\/]\s*)*([^\.\-\d:,\s\+\/]{1,20}\.?)\s*(?:\-?\d+(?:d|m)?\s)*/);//(/^\s*(?:\d+\s)*\s*([^\.\-\d:\s\+\/]{1,20}\.?)\s*(?:\d+\s)*/);
        var valid = false;
        if (word) {
            word = word[1];
            var m = word.match(/(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)/i);
            if (m) {
                var month = {jan:'1',feb:'2',mar:'3',apr:'4',may:'5',jun:'6',jul:'7',aug:'8',sep:'9',oct:'10',nov:'11',dec:'12'};
                temp = temp.replace( word,''+month[m[1].toLowerCase()]+'m');
            } else{
                var roman = { i:'1',ii:'2',iii:'3',iv:'4',v:'5',vi:'6',vii:'7',viii:'8',ix:'9',x:'10',xi:'11',xii:'12', iiii:'4', viiii:'9'};
                m = roman[word.toLowerCase()];
                if (m) {
                    temp = temp.replace( word,'' + m +'m');
                }else{
                    throw "TDate parser exception -  unrecognized word found - " + word ;
                }
            }
        } else {
            throw "TDate parser exception -  unrecognized characters found in input string - " + str;
        }
        t = temp.match(/\b[^\.\-\d:,\s\+\/]/);
    }
    temp = temp.replace(/,\s*/g,' ');   // take care of any comma separation and turn them into a single space
    //		temp = temp.match(/^\s*((?:(?:\-?\d+(?:d|m)?|(?:jan|febr?)(?:uary)?|(?:(?:(?:sept?|nov|dec)(?:em)?)|octo?)(?:ber)?|marc?h?|apri?l?|may|june?|july?|aug(?:ust)?)[\/\-\s]?){0,3})?\s*[\s|T]?\s*([012]?\d(?:[:\.,]\d\d?\d?){0,3})?\s*(Z|(?:[\+\-\s]?\d\d:?(?:\d\d)?))?/i);
    temp = temp.match(/^\s*((?:(?:\-?\d+(?:d|m)?)[\/\-\s]?){0,3})?\s*[\s|T]?\s*([012]?\d(?:[:\.,]\d\d?\d?){0,3})?\s*(Z|(?:[\+\-\s]?\d\d:?(?:\d\d)?))?/i);
    if (periodDesignator){ // period format doesn't have time or timezone  TODO:check this is correct
        temp[2] = temp[3] = null;
    }
    if (!(temp[1] || temp[2] )) {
        throw "TDate parser exception -  unrecognized format should be date and/or time with or without timezone  - " + str;
    }

    var _year = null;
    var _month = null;
    var _day = null;
    var _dateFormat = "";
    var _hours = null;
    var _minutes = null;
    var _seconds = null;
    var _milliseconds = null;
    var _milliSep = ".";
    var _tzOffset = null;

    function _setDatePart(date,format) {
        if ( ! date || !date.length || !format || !format.length || format.length !== date.length) {
            throw "TDate parser exception - internal function call error setDatePart" ;
        }
        for (var i=0; i < date.length; i++) {
            switch ( format[i] ) {
                case "y" :
                    _year = date[i];
                    break;
                case "m" :
                    _month = date[i];
                    break;
                case "d" :
                    _day = date[i];
                    break;
                default:
                    throw "TDate parser exception - internal function call error setDatePart bad format indicator" + i + format[i];
            }
            _dateFormat += format[i];
        }
    }

    var date = temp[1] && BCE ? temp[0] : temp[1];
    if (date) {
        date = date.replace(/(^\s+|\s+$)/g,""); //trim beginning and ending spaces
        date = date.replace(/\s+/g," "); //reduce spaces to single space
        date = date.split(/[\/\-\s]/); //separate year, month and day using slash, dash or space as a delimiter
        for (var i = 0; i < date.length - 1; i++) {
            if (!date[i] && date[i+1]) {	// neg sign add it back in
                date[i+1] = "-" + date[i+1];
                date.splice(i,1);	// remove the empty element
                break;
            } else if (i === date.length - 2 && date[i+1] === "") { // last interation
                date.splice(i+1,1);
            }
        }
        if (date.length > 3) {
            throw "TDate parser exception -  too many values in date string - " + date;
        }
        if (date.length === 1) { // only one number assume it's the year
            _year = date[0];
            _dateFormat = "y";
        } else {
            var yearFound = false;
            var dayFound = false;
            var monthFound = false;
            var dateFormat = [];
            for (var i = 0; i < date.length; i++) {
                if (!date[i]) {
                    if ( i === date.length - 1 ) {
                        date.splice(i,1);
                        continue;
                    }
                    throw "TDate parser exception -  empty value in date string is not allowed  - " + (temp && temp[1] ? temp[1] : "" );
                }
                var codedDatePart = date[i].match(/(\d\d?)(m|d)/);
                if (codedDatePart) {  // found character which codes the meaning of this number
                    date[i] = codedDatePart[1];
                    var numFormat = codedDatePart[2],
                    num = Number(date[i]);
                    if (numFormat == "m") {
                        if (monthFound){
                            throw "TDate parser exception -  unrecognized date string duplicate month designated - " + (temp && temp[1] ? temp[1] : "" );
                        }
                        if (num < 1 || num > 12 ){
                            throw "TDate parser exception -  out of range month designator "+ date[i] + (temp && temp[1] ? "in "+temp[1] : "" );
                        }
                        monthFound = true;
                        for (var j=0; j < dateFormat.length; j++) {
                            dateFormat[j] = dateFormat[j].replace(/m/,""); // remove any existing month possibilities
                            if (dateFormat[j] === "d") {
                                dayFound = true;
                            }
                            if (dateFormat[j] === "y") {
                                yearFound = true;
                            }
                        }
                        dateFormat.push("m");
                        continue;
                    } else {
                        if (dayFound){
                            throw "TDate parser exception -  unrecognized date string duplicate day designated - " + (temp && temp[1] ? temp[1] : "" );
                        }
                        if (num < 1 || num > 31 ){
                            throw "TDate parser exception -  out of range day designator "+ date[i] + (temp && temp[1] ? "in "+temp[1] : "" );
                        }
                        dayFound = true;
                        for (var j=0; j < dateFormat.length; j++) {
                            dateFormat[j] = dateFormat[j].replace(/d/,""); // remove any existing month possibilities
                            if (dateFormat[j] === "m") {
                                monthFound = true;
                            }
                            if (dateFormat[j] === "y") {
                                yearFound = true;
                            }
                        }
                        dateFormat.push("d");
                        continue;
                    }
                }
                /*
                if ( !monthFound && date[i].search(/[^\-\d]/) !== -1) {  // found characters should be month name
                if ( date[i].substring(0,3).match(/jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec/i)) {
                date[i] = {	jan:"01",feb:"02",mar:"03",
                apr:"04",may:"05",jun:"06",
                jul:"07",aug:"08",sep:"09",
                oct:"10",nov:"11",dec:"12"}[date[i].substring(0,3).toLowerCase()];
                monthFound = true;
                for (var j=0; j < dateFormat.length; j++) {
                dateFormat[j] = dateFormat[j].replace(/m/,""); // remove any existing month possibilities
                if (dateFormat[j] === "d") {
                dayFound = true;
                }
                if (dateFormat[j] === "y") {
                yearFound = true;
                }
                }
                dateFormat.push("m");
                continue;
                } else {
                throw "TDate parser exception -  unrecognized date string with non digit characters - " + (temp && temp[1] ? temp[1] : "" );
                }
                }
                */
                var val = Number(date[i]);
                if ( !yearFound && (date[i].length > 2 || val < 1 || val > 31 || (dayFound && monthFound)) ) { // definitely a year
                    if ( date.length === 3 && i === 1) { // year is the middle and not allowed
                        throw "TDate parser exception -  unrecognized date string  unexpected year - " + (temp && temp[1] ? temp[1] : "" );
                    }
                    for (var j=0; j < dateFormat.length; j++) {
                        dateFormat[j] = dateFormat[j].replace(/y/,""); // remove any existing year possibilities
                        if (dateFormat[j] === "d") {
                            if (date.length === 2) {
                                throw "TDate parser exception -  date string error - detected day followed by year  - " + (temp && temp[1] ? temp[1] : "" );
                            }
                            if (BCE) {
                                throw "TDate parser exception -  date string error - detected day in a BC/BCE  - " + (temp && temp[1] ? temp[1] : "" );
                            }
                            dayFound = true;
                            if (dateFormat.length > 1 && dateFormat[1] === "md"){
                                dateFormat[1] = "m";
                            }
                        }
                        if (dateFormat[j] === "md" && date.length ===2) {
                            dateFormat[j] = "m";
                        }
                        if (dateFormat[j] === "m") {
                            monthFound = true;
                        }
                    }

                    dateFormat.push("y");
                    yearFound = true;
                    continue;
                }
                if ( val > 12 ) { // can't be month
                    if (date.length === 3) {  // 3 part date
                        if ( yearFound || i === 1) {  // must be a day
                            for (var j=0; j < dateFormat.length; j++) {
                                dateFormat[j] = dateFormat[j].replace(/d/,""); // remove any existing day possibilities
                                if (dateFormat[j] === "y") {
                                    yearFound = true;
                                }
                            }
                            dateFormat.push("d");
                            dayFound = true;
                        } else {  // not year found and first or last date part
                            dateFormat.push("yd");
                        }
                    } else {// 2 part date
                        if ( yearFound ) {
                            throw "TDate parser exception -  date string error - detected year followed by day  - " + (temp && temp[1] ? temp[1] : "" );
                        }
                        if (periodDesignator) {// found year
                            if (i===1){
                                dateFormat[0]="m";
                            }
                            dateFormat.push("y");
                            yearFound = true;
                        }else{
                            dateFormat.push("yd");
                        }
                    }
                } else {	// can be month, day or year
                    if (dayFound) {
                        dateFormat.push( (yearFound || ( date.length === 3 && i === 1 ) ? "m" : "ym"));
                    } else if (yearFound) {
                        dateFormat.push(  date.length === 2 ? "m" : "md");
                    } else if (monthFound) {
                        dateFormat.push(date.length === 3 && i === 1 ? "d" : "yd");
                    } else if (periodDesignator) {
                        dateFormat.push(BCE || date.length === 2 ? "ym" : "ymd");
                    } else {
                        dateFormat.push(date.length === 3 && i === 1 ? "md" : "ymd");
                    }
                }
            }
            var dFrm = dateFormat.join("");
            if ( dFrm.match(/ymdmdymd|ymdmdyd|ydmdymd|ymdym/)) {
                throw "TDate parser exception -  ambiguous date string supplied  - " + (temp && temp[1] ? temp[1] : "" );
            }
            if ( dFrm.length < 4 && dFrm.match(/mm|yy|dd/)) {  //?? guard not sure if this can happen
                throw "TDate parser exception -  ambiguous date string supplied  - " + (temp && temp[1] ? temp[1] : "" );
            }
            switch  (dFrm.length ) {
                case 1 :	// should only happen for a year
                case 2 :	// ym my md dm
                    _setDatePart( date,dateFormat);
                    break;
                default:
                if (date.length === 2)  { // assume md or dm with md taking precedence
                    dFrm = dFrm.replace(/y/g,"");  // remove year tags
                    if (dFrm.length > 2) {
                        dFrm = dFrm.replace(/md/g,"m");  // can be mdmd  dmd  or mdd
                    }
                    if (dFrm === "dmd" || dFrm === "dm") {  // dm case
                        _setDatePart( date,["d","m"]);
                    } else {
                        _setDatePart( date,["m","d"]);
                    }
                } else {  // 3 parts  possible combinations are ydm -
                    if ( dFrm.length === 3) {
                        _setDatePart( date,dateFormat);
                    } else if ( dFrm.length === 5) {
                        if ( dFrm[0] === "y") {  // y md md case
                            _setDatePart( date,["y","m","d"]);
                        } else { // md md y case
                            _setDatePart( date,["d","m","y"]);
                        }
                    } else {
                        throw "TDate parser exception -  unrecognized format: " + dateFormat.join("-") + " from input: " + (temp && temp[1] ? temp[1] : "" );
                    }
                }
            }
        }
    }

    if (_year && BCE && _year > 0){
        _year = "-" + _year;
    }

    var time = temp[2];
    if (time) {  //  Has Time
        if ( ((_year && _month) || _month) && !_day) {
            throw "TDate parser exception - time code with year/month or month without day information not allowed - " + str;
        }

        time = time.match(/\s*(\d+)(?:([\.,:\s])(\d+))?(?:([\.,:\s])(\d+))?(?:([\.,:\s])(\d+))?\s*/);

		if(time[1] !== null && Number(time[1])==24) { time[1] = '0'; }

        if (time[1] !== null  && TDate.validateHour(time[1])) { //valid hour input
            _hours = time[1];

            if (time[3] !== null  &&  TDate.validateMinute(time[3])) { //valid minute input
                _minutes = time[3];

                if (time[5] !== null  &&  TDate.validateSecond(time[5])) { //valid minute input
                    _seconds = time[5];

                    if (time[7] !== null  &&  TDate.validateMillisecond(time[7])) {
                        _milliseconds = time[7];
                        _milliSep = time[6];
                    }
                }
            }
        }
    }

    var zone = temp[3];
    if (zone) { //  Has Time Zone
        if (!_hours) {
            throw "TDate parser exception - time zone without Time hours information not allowed - " + str;
        } else {
            if (zone.match(/\s*Z\s*/)) {  // UTC
                _tzOffset = "0";
            } else { // time zone offset string
                zone = zone.match(/^\s*(?:UTC|GMT)?([\+\-])?(\d\d):?(\d\d)?/); //extract time zone information
                if (!zone) {  // illegal format found
                    throw " TDate parser exception - invalid timezone string  - " + (temp && temp[3] ? temp[3] : "" );
                } else {
                    if ( !zone[2] || zone[2] > 13 || zone[3] > 59) {
                        throw "TDate parser exception -  Time Zone unrecognized format should be hh or hhmm or hh:mm  - " + (temp && temp[3] ? temp[3] : "" );
                    } else {
                        _tzOffset = ( (zone[1]=== "-" ? "-" : "+") + zone[2] + (zone[3] ? zone[3] : "") );
                    }
                }
            }
        }
    }

    tDate.setOrigString(str);

    if (_year) {
        tDate.setYear(_year);
    }
    if (_month) {
        tDate.setMonth(_month);
    }
    if (_day) {
        tDate.setDay(_day);
    }
    if (_hours) {
        tDate.setHours(_hours);
    }
    if (_minutes) {
        tDate.setMinutes(_minutes);
    }
    if (_seconds) {
        tDate.setSeconds(_seconds);
    }
    if (_milliseconds) {
        tDate.setMilliseconds(_milliseconds, _milliSep);
    }
    if (_tzOffset) {
        tDate.setTimezoneOffset(_tzOffset);
    }

    return tDate;

}

TDate.getMonthName = function (index,shortName) {
    if (index >0  && index <= 12) {
        var name = ["dummy","January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"][index - 0];
        return shortName ? name.substring(0,3) : name;
    }
    return null;
}

TDate.getDayName = function (y,m,d,shortName) {
    var index = (new Date(y,m,d)).getDay();  // FIXME : uses Javascript to figure out day of week, should create algorithm and use cultural info.
    if ( index >= 0 && index < 7 ) {
        var name = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"][index - 0];
        return shortName ? name.substring(0,3) : name;
    }
    return null;
}

TDate.validate = function (value, min, max, name) {
    if (value < min || value > max) {
        if (name === "year") {
            if (value < min) throw  "Hmmmm do you know something I don't, that's BBB (before big bang)";
            else throw  "Don't worry about it mate, neither of us will be around";
        }else {
            throw  value.toString() + " is not a valid value for " + name ;
        }
    }
    return true;
}

TDate.validateMillisecond = function (n) {
    return TDate.validate(n, 0, 999, "milliseconds");

}

TDate.validateSecond = function (n) {
    return TDate.validate(n, 0, 59, "seconds");
}

TDate.validateMinute = function (n) {
    return TDate.validate(n, 0, 59, "minutes");
}

TDate.validateHour = function (n) {
    return TDate.validate(n, 0, 23, "hours");
}

TDate.validateDay = function (n, year, month) {
    return TDate.validate(n, 1, TDate.getDaysInMonth(year, month), "day");
}

TDate.validateMonth = function (n) {
    return TDate.validate(n, 1, 12, "month");
}

TDate.validateYear = function (n) {
    return TDate.validate(n, -14000000000, 14000000000, "year");
}

TDate.getDaysInMonth = function (year, month) {
    return [null,31, (TDate.isLeapYear(year) ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][month];
}

TDate.isLeapYear = function (year) {
    return (((year % 4 === 0) && (year % 100 !== 0)) || (year % 400 === 0));
}


// wrapper for Date object to use it as a duration
var TDuration = function (strDuration) {
    //private members
    var _className = "TemporalDuration";
    var _origString = strDuration;
    var _year = null;
    var _month = null;
    var _day = null;
    var _hour = null;
    var _minute = null;
    var _second = null;

    // parse the string assuming ISO to get precision and set Duration
    function _parseStr (str) {
        that.clear();
        _origString = str;

        str.replace(/\s+/,"")  // remove all white space
        // handle the time period case (PT) and then catch any double non digital characters or any non ISO Dur standard characters
        if (str.replace(/[PYMD]T/,"T").match(/((\D\D)|([^PYMDTHS0-9]))/g)) {
            throw "TDuration exception - illegal characters or character squences - " + str;
        }
        // if a null string or formatted without possible data passed in then an empty TDuration is created.
        if (!str || str === "" || str.match(/^(?:[PT\s]*)$/)) {
            return true;
        }
        if (str.match(/T$/)) {
            throw "TDuration exception - Time specifier (T) without a time period - " + str;
        }
        var dur = str.match(/^P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?/); //separate date and time values

        if (!dur[0]) {
            throw "TDuration exception - invalid duration string passed to parseStr - " + str;
        }

        if (dur[1] ) {
            _year = Number(dur[1]);
        }

        if (dur[2]) {
            _month = Number(dur[2]);
        }

        if (dur[3]) {
            _day = Number(dur[3]);
        }

        if (dur[4]) {
            _hour = Number(dur[4]);
        }

        if (dur[5]) {
            _minute = Number(dur[5]);
        }

        if (dur[6]) {
            _second = Number(dur[6]);
        }

    };

    function _init () {
        _parseStr(_origString);
    };


    //public members
    var that = {
        clear: function () {
            _origString = null;
            _year = null;
            _month = null;
            _day = null;
            _hour = null;
            _minute = null;
            _second = null;
        },

        getYear: function () {
            return _year;
        },

        getMonth: function () {
            return _month;
        },

        getDay: function () {
            return _day;
        },

        getHour: function () {
            return _hour;
        },

        getMinute: function () {
            return _minute;
        },

        getSecond: function () {
            return _second;
        },

        toString: function () {
            var temp = "";
            if (_year || _month || _day || _hour || _minute || _second) {
                temp += "P";
                if ( _year ) {
                    temp += _year + "Y";
                }
                if ( _month ) {
                    temp += _month + "M";
                }

                if ( _day ) {
                    temp += _day + "D";
                }
                if ( _hour || _minute || _second ) {
                    temp += "T";
                    if ( _hour ) {
                        temp += _hour  + "H";
                    }
                    if ( _minute ) {
                        temp += _minute + "M";
                    }
                    if ( _second ) {
                        temp += _second + "S";
                    }
                }
            }
            return temp;
        },

        setTDuration: function (duration) {
            if (typeof duration === "string") {  // should be and encode string
                _parseStr(date);
            } else if (typeof duration === "object" && typeof duration.getClass === "function" && duration.getClass() === "TemporalDuration") {  //
                _parseStr(duration.toString());
            } else {
                return null;
            }
            return that;
        },

        setYear: function (str) {
            if ( str !== null && str !== "" ) {
                if ( isNaN(str)|| str < 0) {
                    throw " TDate exception - invalid string supplied to setYear() - " + str;
                }
            }
            _year = Number(str);
        },

        setMonth: function (str) {
            if ( str !== null && str !== "" ) {
                if ( isNaN(str) || str < 0 || str > 12) {
                    throw " TDate exception - invalid string supplied to setMonth() - " + str;
                }
            }
            _month = Number(str);
        },

        setDay: function (str) {
            if ( str !== null && str !== "" ) {
                if ( isNaN(str) || str < 0 || str > 31) {
                    throw " TDate exception - invalid string supplied to setDay() - " + str;
                }
            }
            _day = Number(str);
        },

        setHour: function (str) {
            if ( str !== null && str !== "" ) {
                if ( isNaN(str) || str < 0 || str > 24) {
                    throw " TDate exception - invalid string supplied to setHour() - " + str;
                }
            }
            _hour = Number(str);
        },

        setMinute: function (str) {
            if ( str !== null && str !== "" ) {
                if ( isNaN(str) || str < 0 || str> 60) {
                    throw " TDate exception - invalid string supplied to setMinute() - " + str;
                }
            }
            _minute = Number(str);
        },

        setSecond: function (str) {
            if ( str !== null && str !== "" ) {
                if ( isNaN(str) || str < 0 ||  str > 60) {
                    throw " TDate exception - invalid string supplied to setSecond() - " + str;
                }
            }
            _second = Number(str);
        },

        getClass: function () {
            return _className;
        },

        isA: function (strClass) {
            if (strClass === _className) {
                return true;
            }
            return false;
        }

    };  // end that

    //init and binding code
    _init();
    return that;
}   //end of TDuration

// is given string is in temporal format
function isTemporal(str) {

    var res = false;

    if (str && str.search(/\|VER/) != -1) {	//we have a temporal
        res = true;

        if (str.search(/SRT/) != -1 && str.match(/SRT=([^\|]+)/)) {

        }else if (str.search(/TYP=s/) != -1 ) {
            if (str.match(/DAT=([^\|]+)/)) {
                if (str.search(/COM=[^\|]+/) == -1) {
                    var dt = str.match(/DAT=([^\|]+)/)[1];
                    res = (dt.length>10);
                }
            }else if (str.search(/COM=[^\|]+/) != -1) {
                res = false;
            }
        }
    }
    return res;
}

function formatGregJulian(val, isneed){

        if(isneed && val){

            var tDate = TDate.parse(val);
            var isbce = (tDate.getYear()<0);
            var day = Number(tDate.getDay());
            
            var hrs = Number(tDate.getHours());
            var min = Number(tDate.getMinutes());
            var sec = Number(tDate.getSeconds());
            
            var res = (isNaN(day)||day<1||day>31?'':(day+' '))+tDate.toString('MMM')+' '+Math.abs(tDate.getYear())+(isbce?' BCE':'');
            
            if(hrs>0||min>0||sec>0){
               res = res + ' ' +tDate.toString('HH:mm:ss zz');
            }
            
            return  res.trim();
            //toString('d MMM yyyy') - misses space!
            //tDate.getDay()+' '+tDate.getMonth()+' '+tDate.getYear() + (isbce?' BCE':'');;

        }else{
            return val;
        }
}

/**
 *
 */
function temporalToHumanReadableString(inputStr) {
    var str = inputStr;
    var cld = '';
    if (str && str.search(/\|VER/) != -1) {	//we have a temporal

        var cldname = 'gregorian';
        if (str.match(/CLD=([^\|]+)/)){
              cldname = str.match(/CLD=([^\|]+)/)[1].toLowerCase();
        }

        if (str.match(/CL2=([^\|]+)/) && cldname!='gregorian') {
            cld = str.match(/CL2=([^\|]+)/)[1] + ' '+
                  str.match(/CLD=([^\|]+)/)[1];

            if(cld.indexOf('null')>=0) cld = cld.substr(4); //some dates were saved in wrong format - fix it
        }

        var isgj = (cldname=='gregorian' || cldname=='julian');

        if (str.search(/SRT/) != -1 && str.match(/SRT=([^\|]+)/)) {
            str = formatGregJulian(str.match(/SRT=([^\|]+)/)[1], isgj);
        }else if (str.search(/TYP=s/) != -1 ) {  //simple
            if (str.match(/DAT=([^\|]+)/)) {
                if (str.search(/COM=[^\|]+/) == -1) {
                }
                str = formatGregJulian(str.match(/DAT=([^\|]+)/)[1], isgj);
            }else if (str.search(/COM=[^\|]+/) != -1) {
                str = str.match(/COM=([^\|]+)/)[1];
            }
        }else if (str.search(/TYP=c/) != -1 ) { //c14 date
            var bce = str.match(/BCE=([^\|]+)/);
            bce = bce ? bce[1]: null;
            var c14 = str.match(/BPD=([^\|]+)/);
            c14 = c14 ? c14[1]: (bce ? bce:" c14 temporal");
            var suff = str.match(/CAL=([^\|]+)/) ? " Cal" : "";
            suff += bce ? " BCE" : " BP";
            var dvp = str.match(/DVP=P(\d+)Y/);
            dvp = dvp ? dvp[1]: null;
            var dev = str.match(/DEV=P(\d+)Y/);
            dev = dev ? " ±" + dev[1] + " yr" + (dev[1]>1?"s":""):(dvp ? " +" + dvp + " yr" + (dvp>1?"s":""):" + ??");
            var dvn = str.match(/DVN=P(\d+)Y/);
            dev += dvp ? (dvn[1] ? " -" + dvn[1] + " yr" + (dvn[1]>1?"s":""): " - ??") : "";
            str = c14 + dev + suff;
        }else if (str.search(/TYP=p/) != -1 ) {// probable date
            var tpq = str.match(/TPQ=([^\|]+)/);
            tpq = tpq ? tpq[1]: null;
            var taq = str.match(/TAQ=([^\|]+)/);
            taq = taq ? taq[1]: null;
            var pdb = str.match(/PDB=([^\|]+)/);
            pdb = pdb ? pdb[1]: (tpq ? tpq:"");
            var pde = str.match(/PDE=([^\|]+)/);
            pde = pde ? pde[1]: (taq ? taq:"");
            str = formatGregJulian(pdb, isgj) + " to " + formatGregJulian(pde, isgj);
        }else if (str.search(/TYP=f/) != -1 ) {//fuzzy date
            var dat = str.match(/DAT=([^\|]+)/);
            dat = dat ? formatGregJulian(dat[1], isgj): "";
            var rng = str.match(/RNG=P(\d*)(Y|M|D)/);
            var units = rng[2] ? (rng[2]=="Y" ? "year" : rng[2]=="M" ? "month" :rng[2]=="D" ? "day" :""): "";
            rng = rng && rng[1] ? " ± " + rng[1] + " " + units + (rng[1]>1 ? "s":""): "";
            str = dat + rng;
        }
        if(cld!=''){
            str = cld + ' (Gregorian '+str+')';
        }
    }
    return str;
}

function temporalSimplifyDate(sdate) {
    
    if(sdate){
        var s = sdate;
        if(s.indexOf("-00-00")>0){
            s = s.replace("-00-00","-01-01");
        }
        if(s.indexOf("00:00:00")>0){
            s = s.replace("-01-01 00:00:00"," ");
            s = s.replace("00:00:00"," ");
        }
        s = s.trim();
                                
        return s;
    }else{
        return sdate;
    }
}
