/*
* Copyright (C) 2005-2013 University of Sydney
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
* Heurist API  v0.3
* 0.2 (2007/12/12) - JSON data transfer both upstream and downstream
* 0.1 - initial release
* 
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


if (! window.console) { console = { log: function() { } } }
if (! window.firebug) { firebug = console; }
if (! HAPI_userData) { HAPI_userData = {}; }

/* Google API style, we have two naming systems available:
* HFooBar is also available as HAPI.FooBar
*/
var HAPI = {
	getVersion: function() { return "0.3"; },

	key: "",
	database: (top && top.HeuristInstance ? top.HeuristInstance : (window.HeuristInstance ? window.HeuristInstance:"")),
	setKey: function(key, database, url) {
		var error;
		var baseURL, path;

		if (! (key + "").match(/^[0-9a-f]{40}$/i)) {
			error = "is invalid";
		}
		else {
			baseURL = document.location.protocol + "//" + document.location.host;
			if (document.location.port) { baseURL += ":" + document.location.port; }
			path = document.location.pathname || "/";

			if (! path.match(/[\/]$/)) {
				// trim everything following the last /
				path = path.replace(/\/[^\/]*$/, "/");
			}

			do {
				// try the key against all path prefixes
				if (HAPI.SHA1(database + baseURL + path) === key) {	//CHECKTHIS: noticed that the port is fixed perhaps we need to try without portnumber also
					HAPI.key = key;
					HAPI.database = database;
					HAPI.HeuristBaseURL = url;
					if (HeuristSitePath) HAPI.HeuristSitePath = HeuristSitePath;
					return;
				}
				path = path.replace(/[^\/]*\/$/, "");
			} while (path);

			error = "was registered for a different web site or Heurist database";
		}

		alert("The Heurist API key used on this web site " + error);
	},

	isA: function(obj, className) {
		// Check if the object is of the named class or has a parent of that type

		if (! obj  ||  typeof(obj) !== "object"  ||  ! obj.constructor) { return false; }

		obj = obj.constructor;
	do {
			if (! obj.getClass) { return false; }
			if (obj.getClass().toLowerCase() === className.toLowerCase()) { return true; }
		} while ((obj = obj.superclass));

		return false;
	},

	inherit: function(subclass, superclass) {
		var Inheritance = function() {};
		Inheritance.prototype = superclass.prototype;
		subclass.prototype = new Inheritance();
		subclass.prototype.constructor = subclass;
		subclass.prototype.getClass = subclass.getClass;
		subclass.superclass = superclass;
	},

	getNonce: function() {
		var recordCache = {};
		var recordCacheCount = 1000000;
		return function(record) {
			/* Generate a unique string to reference the given record by */
			var nonce;
			do {
				nonce = "#" + Math.ceil(Math.random() * recordCacheCount);
			} while (recordCache[nonce]);
			recordCache[nonce] = record;

			return nonce;
		};
	}(),

	base64: function() {
		/* exposes HAPI.base64.encode and HAPI.base64.decode */

		var unmap = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";
		var map = {};
		for (var i=0; i < unmap.length; ++i) { map[unmap.charAt(i)] = i; }

		function decodeUTF8(input) {
			var output = "";

			var c0, c1, c2;
			var i = 0;
			var inputLength = input.length;
			while (i < inputLength) {
				c0 = input.charCodeAt(i);

				if (c0 < 128) {
					output += String.fromCharCode(c0);
					++i;
				}
				else if ((c0 > 191)  &&  (c0 < 224)) {
					c1 = input.charCodeAt(i+1);
					output += String.fromCharCode(((c0 & 0x1F) << 6) | (c1 & 0x3F));
					i += 2;
				}
				else {
					c1 = input.charCodeAt(i+1);
					c2 = input.charCodeAt(i+2);
					output += String.fromCharCode(((c0 & 0x0F) << 12) | ((c1 & 0x3F) << 6) | (c2 & 0x3F));
					i += 3;
				}
			}

			return output;
		}

		function encodeUTF8(input) {
			var output = "";

			var c;
			var i;
			var inputLength = input.length;
			for (i=0; i < inputLength; ++i) {
				c = input.charCodeAt(i);

				if (c < 128) {
					output += String.fromCharCode(c);
				}
				else if ((c >= 128) && (c < 2048)) {
					output += String.fromCharCode((c >> 6) | 0xC0) +
							  String.fromCharCode((c & 0x3F) | 0x80);
				}
				else {
					output += String.fromCharCode((c >> 12) | 0xE0) +
							  String.fromCharCode(((c >> 6) & 0x3F) | 0x80) +
							  String.fromCharCode((c & 0x3F) | 0x80);
				}
			}

			return output;
		}

		return {
			decode: function(input) {
				input = input.replace(/[^A-Za-z0-9+\/=]/g, "");

				var output = "";
				var i0, i1, i2, i3;
				var o0, o1, o2;
				var hasHighBytes = false;

				var i = 0;
				var inputLength = input.length;
				while (i < inputLength) {
					i0 = map[input.charAt(i++)];
					i1 = map[input.charAt(i++) || "="];
					i2 = map[input.charAt(i++) || "="];
					i3 = map[input.charAt(i++) || "="];

					o0 = (i0 << 2) | (i1 >> 4);
					o1 = ((i1 & 0x0F) << 4) | (i2 >> 2);
					o2 = ((i2 & 0x03) << 6) | i3;

					if (o0 > 127  ||  o1 > 127  ||  o2 > 127) { hasHighBytes = true; }

					output += String.fromCharCode(o0);
					if (i2 !== 64) { output += String.fromCharCode(o1); }
					if (i3 !== 64) { output += String.fromCharCode(o2); }
				}

				return hasHighBytes? decodeUTF8(output) : output;
			},

			encode: function(input) {
				var output = "";
				var i0, i1, i2;
				var o0, o1, o2, o3;

				if (input.match(/[^\0-\x7F]/)) { input = encodeUTF8(input); }	// contains high-bytes: UTF-8 encode it

				var i = 0;
				var inputLength = input.length;
				while (i < inputLength) {
					i0 = input.charCodeAt(i++);
					i1 = input.charCodeAt(i++);
					i2 = input.charCodeAt(i++);

					output += unmap.charAt(i0 >> 2);
					output += unmap.charAt(((i0 & 0x03) << 4) | (i1 >> 4));
					if (! isNaN(i1)) { output += unmap.charAt(((i1 & 0x0F) << 2) | (i2 >> 6)); }
					if (! isNaN(i2)) { output += unmap.charAt(i2 & 0x3F); }
				}

				return output;
			}
		};
	}(),

	SHA1: function() {
		var hex = "0123456789abcdef".split("");

		function leftRotate(n, s) { return (n << s) | (n >>> (32 - s)); }	// rotate n left by s bits
		function littleEndianHex(val) {
			return (
				hex[(val >>> 4) & 0x0f] + hex[(val >>> 0) & 0x0f] +
				hex[(val >>> 12) & 0x0f] + hex[(val >>> 8) & 0x0f] +
				hex[(val >>> 20) & 0x0f] + hex[(val >>> 16) & 0x0f] +
				hex[(val >>> 28) & 0x0f] + hex[(val >>> 24) & 0x0f]
			);
		}
		function bigEndianHex(val) {
			var str = "00000000" + ((val > 0)? val : (val + 0x100000000)).toString(16);
			return str.substring(str.length - 8);
		}


		return function(input) {
			var blockstart;
			var i, j;
			var W = [];
			var H0 = 0x67452301;
			var H1 = 0xEFCDAB89;
			var H2 = 0x98BADCFE;
			var H3 = 0x10325476;
			var H4 = 0xC3D2E1F0;
			var A, B, C, D, E;
			var temp;

			if (input.match(/[^\0-\x7F]/)) { input = HAPI.base64.encode(input); }

			var inputLength = input.length;
			var wordArray = [];
			for (i=0; i < inputLength-3; i+=4) {
				j = (input.charCodeAt(i) << 24) | (input.charCodeAt(i+1) << 16) | (input.charCodeAt(i+2) << 8) | input.charCodeAt(i+3);
				wordArray.push(j);
			}

			switch (inputLength % 4) {
				case 0:
				i = 0x080000000;
				break;
				case 1:
				i = input.charCodeAt(inputLength-1) << 24 | 0x800000;
				break;
				case 2:
				i = input.charCodeAt(inputLength-2) << 24 | input.charCodeAt(inputLength-1) << 16 | 0x8000;
				break;
				case 3:
				i = input.charCodeAt(inputLength-3) << 24 | input.charCodeAt(inputLength-2) << 16 | input.charCodeAt(inputLength-1) << 8 | 0x80;
				break;
			}

			wordArray.push(i);
			while ((wordArray.length % 16) != 14) { wordArray.push(0); }
			wordArray.push(inputLength >>> 29);
			wordArray.push((inputLength << 3) & 0x0ffffffff);

			for (blockstart=0; blockstart < wordArray.length; blockstart += 16) {
				for (i=0; i < 16; ++i) { W[i] = wordArray[blockstart+i]; }
				for (i=16; i <= 79; ++i) { W[i] = leftRotate(W[i-3] ^ W[i-8] ^ W[i-14] ^ W[i-16], 1); }

				A = H0;
				B = H1;
				C = H2;
				D = H3;
				E = H4;

				for (i=0; i<=19; i++) {
					temp = (leftRotate(A,5) + ((B&C) | (~B&D)) + E + W[i] + 0x5A827999) & 0x0ffffffff;
					E = D;
					D = C;
					C = leftRotate(B,30);
					B = A;
					A = temp;
				}

				for (i=20; i<=39; i++) {
					temp = (leftRotate(A,5) + (B ^ C ^ D) + E + W[i] + 0x6ED9EBA1) & 0x0ffffffff;
					E = D;
					D = C;
					C = leftRotate(B,30);
					B = A;
					A = temp;
				}

				for (i=40; i<=59; i++) {
					temp = (leftRotate(A,5) + ((B&C) | (B&D) | (C&D)) + E + W[i] + 0x8F1BBCDC) & 0x0ffffffff;
					E = D;
					D = C;
					C = leftRotate(B,30);
					B = A;
					A = temp;
				}

				for (i=60; i<=79; i++) {
					temp = (leftRotate(A,5) + (B ^ C ^ D) + E + W[i] + 0xCA62C1D6) & 0x0ffffffff;
					E = D;
					D = C;
					C = leftRotate(B,30);
					B = A;
					A = temp;
				}

				H0 = (H0 + A) & 0x0ffffffff;
				H1 = (H1 + B) & 0x0ffffffff;
				H2 = (H2 + C) & 0x0ffffffff;
				H3 = (H3 + D) & 0x0ffffffff;
				H4 = (H4 + E) & 0x0ffffffff;
			}

			return bigEndianHex(H0) + bigEndianHex(H1) + bigEndianHex(H2) + bigEndianHex(H3) + bigEndianHex(H4);
		};
	}()
};


var HObject = function() {
	/* Base Heurist class, gives us a type-detection system */
};
HObject.prototype.toString = function() {
	var id = (this.getID && this.getID())? (" #" + this.getID()) : "";
	return this.constructor.getClass() + id;
};
HObject.getClass = function() { return "HObject"; };
HAPI.HObject = HObject;


var HException = function(text, exceptionType) {
	var _text = text  ||  "";
	var _exceptionType = exceptionType || "HException";

	this.getText = function() { return _text; };
	this.toString = function() { return (_exceptionType + ": " + _text); };
	this.getClass = function() { return _exceptionType; };
};
HException.getClass = function() { return "HException"; };
HAPI.Exception = HException;
HAPI.inherit(HException, HObject);

var HPermissionException = function(text) { HException.call(this, text, "HPermissionException"); };
HAPI.PermissionException = HPermissionException;
HAPI.inherit(HPermissionException, HException);

var HInvalidWorkgroupException = function(text) { HException.call(this, text, "HInvalidWorkgroupException"); };
HAPI.InvalidWorkgroupException = HInvalidWorkgroupException;
HAPI.inherit(HInvalidWorkgroupException, HException);

var HInvalidRecordTypeException = function(text) { HException.call(this, text, "HInvalidRecordTypeException"); };
HAPI.InvalidRecordTypeException = HInvalidRecordTypeException;
HAPI.inherit(HInvalidRecordTypeException, HException);

var HRecordStubException = function(text) { HException.call(this, text, "HRecordStubException"); };
HAPI.RecordStubException = HRecordStubException;
HAPI.inherit(HRecordStubException, HException);

var HDetailVarietyMismatchException = function(text) { HException.call(this, text, "HDetailVarietyMismatchException"); };
HAPI.DetailVarietyMismatchException = HDetailVarietyMismatchException;
HAPI.inherit(HDetailVarietyMismatchException, HException);

var HBadRelationshipException = function(text) { HException.call(this, text, "HBadRelationshipException"); };
HAPI.BadRelationshipException = HBadRelationshipException;
HAPI.inherit(HBadRelationshipException, HException);

var HNotLoggedInException = function(text) { HException.call(this, text, "HNotLoggedInException"); };
HAPI.NotLoggedInException = HNotLoggedInException;
HAPI.inherit(HNotLoggedInException, HException);

var HNotPersonalisedException = function(text) { HException.call(this, text, "HNotPersonalisedException"); };
HAPI.NotPersonalisedException = HNotPersonalisedException;
HAPI.inherit(HNotPersonalisedException, HException);

var HUnknownTagException = function(text) { HException.call(this, text, "HUnknownTagException"); };
HAPI.UnknownTagException = HUnknownTagException;
HAPI.inherit(HUnknownTagException, HException);

var HValueException = function(text) { HException.call(this, text, "HValueException"); };
HAPI.ValueException = HValueException;
HAPI.inherit(HValueException, HException);

var HTypeException = function(text) { HException.call(this, text, "HTypeException"); };
HAPI.TypeException = HTypeException;
HAPI.inherit(HTypeException, HException);

var HUnsavedRecordException = function(text) { HException.call(this, text, "HUnsavedRecordException"); };
HAPI.UnsavedRecordException = HUnsavedRecordException;
HAPI.inherit(HUnsavedRecordException, HException);


var HWorkgroupTag = function(id, name, workgroup) {
	var _id = id;
	var _name = name;
	var _workgroup = workgroup;

	/*ARTEM
	if (HAPI.WorkgroupTagManager) {
		throw "Cannot construct new HWorkgroupTag objects";
	}
	*/

	this.getID = function() { return _id; };
	this.getName = function() { return _name; };
	this.getWorkgroup = function() { return _workgroup; };
};
HWorkgroupTag.getClass = function() { return "HWorkgroupTag"; };
HAPI.inherit(HWorkgroupTag, HObject);
HAPI.WorkgroupTag = HWorkgroupTag;


var HWorkgroup = function(id, name, longName, description, url) {
	var _id = id;
	var _name = name;
	var _longName = longName;
	var _description = description;
	var _url = url;

	if (HAPI.WorkgroupManager) {
		throw "Cannot construct new HWorkgroup objects";
	}

	this.getID = function() { return _id; };
	this.getName = function() { return _name; };
	this.getLongName = function() { return _longName; };
	this.getDescription = function() { return _description; };
	this.getURL = function() { return _url; };
};
HAPI.inherit(HWorkgroup, HObject);
HWorkgroup.getClass = function() { return "HWorkgroup"; };
HAPI.Workgroup = HWorkgroup;



var HRecordType = function(id, name, mask) {
	var _id = id;
	var _name = name;
	var _mask = mask;

	if (HAPI.RecordTypeManager) {
		throw "Cannot construct new HRecordType objects";
	}

	this.getID = function() { return _id; };
	this.getName = function() { return _name; };

	this.getTitleMask = function() { return _mask; };	// package use only ?
};
HRecordType.getClass = function() { return "HRecordType"; };
HAPI.inherit(HRecordType, HObject);
HAPI.RecordType = HRecordType;


var HRatings = function(ratingValues) {
	// ratingValues is of the form { CONTENT: {1: "poor", 2: "good-ish", ...}, QUALITY: .. }
	var vals = {
		ratings: ratingValues,
		isValidRating: function(ratingVal) { return typeof this.ratings[ratingVal] != "undefined"},
		defaultRating: "0",
		getClass: function() { return "HRatings"; }
	};
	return vals;
}(HAPI_commonData.ratings);
HAPI.Ratings = HRatings;


var HUser = function(id, username, realname) {
	var _id = id;
	var _username = username;
	var _realname = realname;

	if (HAPI.UserManager) {
		throw "Cannot construct new HUser objects";
	}

	this.getID = function() { return _id; };
	this.getUsername = function() { return _username; };
	this.getRealName = function() { return _realname; };
};
HUser.getClass = function() { return "HUser"; };
HAPI.inherit(HUser, HObject);
HUser.prototype.constructor = HUser;
HAPI.User = HUser;


var HUserManager = new function(users) {
	/* users is an array, each entry of the form [id, username, realname] */
	var _users = [];
	var _usersMap = {};

	var u, newUser;
	for (var i=0; i < users.length; ++i) {
		u = users[i];
		newUser = new HUser(parseInt(u[0]), u[1], u[2]);

		_users.push(newUser);
		_usersMap[u[0]] = newUser;
	}

	this.getUserById = function(id) { return (_usersMap[id] || null); };
	this.findUser = function(name) {
		name = name.toLowerCase();
		var matches = [];
		for (var i=0; i < _users.length; ++i) {
			if ((_users[i].getUsername() || "").toLowerCase().indexOf(name) >= 0) {
				matches.push(_users[i]);
			}
			else if ((_users[i].getRealName() || "").toLowerCase().indexOf(name) >= 0) {
				matches.push(_users[i]);
			}
		}
		return matches;
	};
}(HAPI_commonData.users);
HUserManager.prototype = new HObject();
HAPI.UserManager = HUserManager;


var HGeographicValue = function(type, wkt) {
	var _type = HGeographicType.typeForAbbreviation(type);
	var _wkt = wkt;

	if(_wkt){
		_wkt = _wkt.trim().toUpperCase();
	}
	if(_type==undefined || _type==null){
		_type = HGeographicType.getTypeFromValue(_wkt);
		type = HGeographicType.abbreviationForType(_type);
	}


	this.getType = function() { return _type; };
	this.getWKT = function() { return _wkt; };
	this.toString = function() { return "geographic value"; };

	// We use the generic HGeographicValue constructor as a factory for specific Geographic values if GOI is loaded
	if (HAPI.GOI && type) {
		switch (type) {
			case "p": HAPI.GOI.PointValue.call(this, _wkt); break;
			case "r": HAPI.GOI.BoundsValue.call(this, _wkt); break;
			case "c": HAPI.GOI.CircleValue.call(this, _wkt); break;
			case "pl": HAPI.GOI.PolygonValue.call(this, _wkt); break;
			case "l": HAPI.GOI.PathValue.call(this, _wkt); break;
			default:
			throw new HAPI.GOI.InvalidGeographicValueException("unknown geographic type");
		}
	}
};
HGeographicValue.getClass = function() { return "HGeographicValue"; };
HAPI.inherit(HGeographicValue, HObject);
HAPI.GeographicValue = HGeographicValue;


var HGeographicType = {
	BOUNDS: "bounds",
	CIRCLE: "circle",
	POLYGON: "polygon",
	PATH: "path",
	POINT: "point",

	typeForAbbreviation: function(abbrev) {
		return { "r": "bounds", "c": "circle", "pl": "polygon", "l": "path", "p": "point" }[abbrev];
	},
	abbreviationForType: function(type) {
		return { "bounds": "r", "circle": "c", "polygon": "pl", "path": "l", "point": "p" }[type];
	},

	getTypeFromValue: function(wkt){
		if (wkt){
			wkt = wkt.trim().toUpperCase();
		  if (wkt.match(/POINT[(]([^ ]+) ([^ ]+)[)]/)) {
		  		return HGeographicType.POINT;
		  }else if (wkt.match(/POLYGON[(][(]([^ ]+) ([^,]+),([^ ]+) ([^,]+),([^ ]+) ([^,]+),([^ ]+) ([^)]+)[)][)]/)) {
		  		return HGeographicType.POLYGON;
		  }else  if (wkt.match(/LINESTRING[(]([^ ]+) ([^,]+),([^ ]+) /)) {
		  		return HGeographicType.PATH;
		  }
		}
		return undefined;
	},

	getClass: function() { return "HGeographicType"; }
};
HAPI.GeographicType = HGeographicType;


var HRecord = function() {
	var that = this;
	var _id = null, _version = null;
	var _nonce = HAPI.getNonce(this);
	var _type = null;
	var _title = null;
	var _namedDetails = {}, _details = {};
	var _url = null;
	var _notes = null;

	var _workgroup = null, _nonOwnerVisible = 'viewable';	// default to "read-only but visible" outside this workgroup
	var _urlDate = null, _urlError = null;

	var _creationDate = null, _modificationDate = null;
	var _creator = null;
	var _hhash = null;

	var _isPersonalised = false;
	var _bookmarkID = null;
	var _personalNotes = null, _rating = "", _tags = [], _tagsMap = {}, _wgTags = [], _wgTagsMap = {};
	var _notifications = [], _addedNotifications = [], _removedNotifications = [];
	var _comments = [], _addedComments = [], _removedComments = [], _modifiedComments = [];

	var _readonly = false;
	var _modified = true;
	var _error = null;
	var _warning = null;
	// Strictly internal stuff
	var _storageManager = null;	// the HStorageManager from which this record was loaded

	var _isTempNew = false;


	this.getID = function() { return _id; };
	this.getVersion = function() { return _version; };

	this.getNonce = function() { return _nonce; };

	this.setTempNew = function(val){
		_isTempNew = val;
	};
	this.isTempNew = function(){
		return _isTempNew;
	};

	this.getValuesByTypePath = function(typePath) {
		var values = [], values2 = [], value;
		var i, j;
		var record;
		var pathBits = typePath.match(/^([^.]+)(?:[.](.+))?$/);
		var localPath = pathBits[1], remPath = pathBits[2];


		if (_namedDetails[localPath]) {
			for (i in _namedDetails[localPath]) { values.push(_namedDetails[localPath][i]); }
		}
		if (_details[localPath]) {
			for (i=0; i < _details[localPath].length; ++i) { values.push(_details[localPath][i]); }
		}

		if (! remPath) { return values; }

		/* typePath is of the form X.Y or X.Y.Z or ... */
		for (i=0; i < values.length; ++i) {
			if (HAPI.isA(values[i], "HRecord")) {
				record = values[i];
			} else if (HAPI.isA(values[i], "HRecordStub")) {
				record = values[i].getRecord();
				if (! record) {
					throw new HRecordStubException("Record contains references to unloaded records");
				}
			} else {
				continue;
			}

			value = record.getValuesByTypePath(remPath);
			for (j=0; j < value.length; ++j) {
				values2.push(value[j]);
			}
		}

		return values2;
	};

	/* private */ function getValueByTypePath(typePath) {
		// The title mask nominally handles a single value per field;
		// this function handles the case where there are multiple values, and boils them down.
		// The simplest fallback case is to join with commas.

		var values = that.getValuesByTypePath(typePath);

		var bits = typePath.split(/[.]/);
		var detailID = bits.pop();
		var refID = bits.pop();

		if (top.HEURIST.magicNumbers && top.HEURIST.magicNumbers['DT_CREATOR'] && refID === top.HEURIST.magicNumbers['DT_CREATOR']) {
			// an AuthorEditor
			var surnameDT =(top.HEURIST.magicNumbers && top.HEURIST.magicNumbers['DT_GIVEN_NAMES']?top.HEURIST.magicNumbers['DT_GIVEN_NAMES']:0);
			if (values.length === 1) {
				if (! values[0]) { return (detailID === surnameDT)? "Anonymous" : ""; }
				else { return values[0]; }
			}
			else {
				if (! values[0]) { return (detailID === surnameDT)? "multiple anonymous authors" : ""; }
				else {
					if (detailID === surnameDT) { return values[0] + " et al."; }
					else { return values[0]; }
				}
			}
		}
		else {
			return values.join(", ");
		}
	}

	this.getTitle = function() {
		if (_title) { return _title; }

		var mask = (_type && _type.getTitleMask())  ||  "";
		_title = mask.replace(/\[(\d+(?:[.]\d+)*)\]/g, function(m0, m1) { return getValueByTypePath(m1); });

		// Clean up bits of stray punctuation.
		if (! _title.match(/^\s*[0-9a-z]+:\S+\s*$/i)) {	// not a URI
			_title = _title.replace(/^[-:;,.\/\s]*(.*?)[-:;,\/\s]*$/, "$1");
			_title = _title.replace(/\([-:;,.\/\s]+\)/, "");
			_title = _title.replace(/\([-:;,.\/\s]*(.*?)[-:;,.\/\s]*\)/, "($1)");
			_title = _title.replace(/\([-:;,.\/\s]*\)|\[[-:;,.\/\s]*\]/, "");
			_title = _title.replace(/^[-:;,.\/\s]*(.*?)[-:;,\/\s]*$/, "$1");
			_title = _title.replace(/,\s*,+/, ",");
			_title = _title.replace(/\s+,/, ",");
		}
		_title = _title.replace(/  +/, " ");
		_title = _title.replace(/^\s+|\s+$/g, "");

		return _title;
	};

	this.getURL = function() { return _url; };
	this.setURL = function(url) {
		if (_readonly) { throw new HPermissionException("Record is read-only"); }
		_url = url;
		_urlDate = null;
		_urlError = "URL not yet verified";
		_modified = true;
	};

	this.getNotes = function() { return _notes; };
	this.setNotes = function(notes) {
		if (_readonly) { throw new HPermissionException("Record is read-only"); }
		_notes = notes;
		_modified = true;
	};

	this.getRecordType = function() { return _type; };
	this.setRecordType = function(type) {
		/* PRE */ if (! HAPI.isA(type, "HRecordType")) { throw new HTypeException("HRecordType object required"); }
		if (_readonly) { throw new HPermissionException("Record is read-only"); }
		if (_type  &&  _type._isRelationship) {
			// trying to change relationship to non-relationship
			throw new HInvalidRecordTypeException("Cannot change relationship's record-type");
		}
		if (type._isRelationship) {
			// trying to change non-relationship to relationship
			throw new HInvalidRecordTypeException("Cannot change record to a relationship");
		}
		_type = type;
		_modified = true;

		// invalidate hhash ... and title?
		_hhash = null;
	};

	/* private */ function conditionalInvalidate(detailType) {
		// pre: detailType is an HDetailType
		// Tests whether a change to a detail of the given type
		// will change the hhash or the title of this record (or both),
		// and invalidate their cached values as appropriate

		// should check also upstream invalidations
		if (detailType.getVariety() !== HVariety.REFERENCE) {
			if (HDetailManager.getDetailMatching(_type, detailType)) { _hhash = null; }
		}
		else {
			if (HDetailManager.getDetailRequiremence(_type, detailType) === 'required') { _hhash = null; }
		}
		// FIXME
		// need to invalidate title as necessary
	}

	this.getDetail = function(detailType) {
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object required"); }

		if (HDetailManager.getDetailRepeatable(_type, detailType)) { throw new HValueException("detail is repeatable"); }

		var tmpDetails = _namedDetails[detailType.getID()];
		if (! tmpDetails) { return null; }//saw CHECK:  why return null if no existing detail , could be a newly added one not yet saved.

		for (var i in tmpDetails) {
			if(detailType.getVariety()===HVariety.ENUMERATION || detailType.getVariety()===HVariety.RELATIONTYPE){
				return detailType.getEnumerationValueFromId(tmpDetails[i]);
			}
			return tmpDetails[i];	// return the first value, having multiple might mean that the rectype has changed where detail is not repeatable now
		}
	};
	this.getDetails = function(detailType) {
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object required"); }
		var details = [], bdID, i;
		var tmpDetails = _namedDetails[detailType.getID()];
		var termTranslate = (detailType.getVariety()===HVariety.ENUMERATION || detailType.getVariety()===HVariety.RELATIONTYPE);
		if (tmpDetails) {
			// put named details (those that correspond to an existing bd_id) first
			for (bdID in tmpDetails) {
				details.push(termTranslate ? detailType.getEnumerationValueFromId(tmpDetails[bdID]) : tmpDetails[bdID]);
			}
		}
		tmpDetails = _details[detailType.getID()];
		if (tmpDetails) {
			// put un-named details at the end
			for (i=0; i < tmpDetails.length; ++i) {
				details.push(termTranslate ? detailType.getEnumerationValueFromId(tmpDetails[i]) : tmpDetails[i]);
			}
		}
		return (details  ||  null);
	};
	this.getAllDetails = function() {
		var i, j, detailType, termTranslate, details = {};
		for (i in _namedDetails) {
			detailType = HDetailManager.getDetailTypeById(i);
			termTranslate = (detailType.getVariety()===HVariety.ENUMERATION || detailType.getVariety()===HVariety.RELATIONTYPE);
			details[i] = [];
			for (j in _namedDetails[i]) {
				details[i].push(termTranslate ? detailType.getEnumerationValueFromId(_namedDetails[i][j]) : _namedDetails[i][j]);
			}
		}
		for (i in _details) {
			detailType = HDetailManager.getDetailTypeById(i);
			termTranslate = (detailType.getVariety()===HVariety.ENUMERATION || detailType.getVariety()===HVariety.RELATIONTYPE);
			if (! details[i]) {
				details[i] = [];
			}
			details[i].push.apply(details[i], (termTranslate ? detailType.getEnumerationValueFromId(_details[i]) : _details[i]));
		}
		return (details  ||  null);
	};
	this.addDetail = function(detailType, detailValue) {
		if (_readonly) { throw new HPermissionException("Record is read-only"); }
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object required"); }
		// Don't add meaningless non-truthy values
		if (! detailValue  &&  (detailValue !== 0  &&  detailValue !== false)) { return; }

		// check validity of value
		if (_type  &&  ! HDetailManager.isValidDetailValue(_type, detailType, detailValue)) {
			throw new HValueException("invalid value ("+detailValue+") for field "+ detailType.getName()+" in " +_type.getName()+' record');
		}

		// check repeatability, if we can (if the record type isn't set yet, there's nothing we can do)
		if (_type  &&  ! HDetailManager.getDetailRepeatable(_type, detailType)) {
			if (_namedDetails[detailType.getID()]  ||  _details[detailType.getID()]) {
				throw new HValueException("value already exists for non-repeatable detail");
			}
		}

		//check if enum or relationship variety translate values to id
		var termTranslate = (detailType.getVariety()===HVariety.ENUMERATION || detailType.getVariety()===HVariety.RELATIONTYPE);

		if (! _details[detailType.getID()]) {
			_details[detailType.getID()] = [ (termTranslate ? detailType.getIdForEnumerationValue(detailValue):detailValue)];
		}else{
			_details[detailType.getID()].push(termTranslate ? detailType.getIdForEnumerationValue(detailValue):detailValue);
		}
		_modified = true;
	};
	this.changeDetail = function(detailType, oldValue, newValue) {
		if (_readonly) { throw new HPermissionException("Record is read-only"); }
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object required"); }

		// check validity of value
		if (_type  &&  ! HDetailManager.isValidDetailValue(_type, detailType, newValue)) {
			throw new HValueException("invalid new value ("+newValue+") for field "+ detailType.getName()+" in " +_type.getName()+' record');
		}

		if (_type  &&  ! HDetailManager.isValidDetailValue(_type, detailType, oldValue)) {
			throw new HValueException("invalid old value ("+oldValue+") for field "+ detailType.getName()+" in " +_type.getName()+' record');
		}

		// reduce records to stubs for comparison
		if (HAPI.isA(oldValue, "HRecord")) {
			oldValue = _storageManager.getStubForRecord(oldValue);
		}

		// change enum values to ids
		if (detailType.getVariety()===HVariety.ENUMERATION || detailType.getVariety()===HVariety.RELATIONTYPE){
			if (isNaN(oldValue)) {
				oldValue = detailType.getIdForEnumerationValue(oldValue);
			}
			if (isNaN(newValue)) {
				newValue = detailType.getIdForEnumerationValue(newValue);
			}
		}

		// find where the old value is being stored.  It is either in the namedDetails ...
		var tmpDetails = _namedDetails[detailType.getID()];
//		if (! tmpDetails) {
//			throw new HValueException("can't change non-existent value");
//		}
		if (tmpDetails) {
			for (var bdID in tmpDetails) {
				var detail = tmpDetails[bdID];
				if (HAPI.isA(detail, "HRecord")) {
					detail = _storageManager.getStubForRecord(detail);
				}
				if (detail === oldValue) {
					tmpDetails[bdID] = newValue;
					_modified = true;
					return;
				}
			}
		}

		// ... or the un-named details.
		tmpDetails = _details[detailType.getID()];
		if (tmpDetails) {
			for (var i=0; i < tmpDetails.length; ++i) {
				var detail = tmpDetails[i];
				if (HAPI.isA(detail, "HRecord")) {
					detail = _storageManager.getStubForRecord(detail);
				}
				if (detail === oldValue) {
					tmpDetails[i] = newValue;
					_modified = true;
					return;
				}
			}
		}

		// oldValue definitely doesn't exist, throw an exception
		throw new HValueException("can't change non-existent value");
	};
	this.removeDetails = function(detailType) {
		if (_readonly) { throw new HPermissionException("Record is read-only"); }
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object required"); }
		delete _namedDetails[detailType.getID()];
		delete _details[detailType.getID()];
		_modified = true;
	};
	this.setDetails = function(detailType, detailValues) {
		// Convenience function equivalent to ::removeDetails followed by multiple ::addDetail
		if (_readonly) { throw new HPermissionException("Record is read-only"); }
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object required"); }

		var termTranslate = (detailType.getVariety()===HVariety.ENUMERATION || detailType.getVariety()===HVariety.RELATIONTYPE);
		var newDetails = [];
		var detailValue;
		for (var i=0; i < detailValues.length; ++i) {
			detailValue = detailValues[i];
			if (! detailValue  &&  (detailValue !== 0  &&  detailValue !== false)) { continue; }
			// check validity of value
			if (_type  &&  ! HDetailManager.isValidDetailValue(_type, detailType, detailValue)) {
				throw new HValueException("invalid value ("+detailValue+") for field "+ detailType.getName()+" in " +_type.getName()+' record');
			}

			newDetails.push(termTranslate ? detailType.getIdForEnumerationValue(detailValue):detailValue);
		}
		if (newDetails) {
			delete _namedDetails[detailType.getID()];
			_details[detailType.getID()] = newDetails;
		}else{
			delete _namedDetails[detailType.getID()];
			delete _details[detailType.getID()];
		}
		_modified = true;
	};

	this.getWorkgroup = function() { return _workgroup; };
	this.setWorkgroup = function(workgroup) {
		/* PRE */ if (! HAPI.isA(workgroup, "HWorkgroup")) { throw new HTypeException("HWorkgroup object required"); }
		if (_readonly) { throw new HPermissionException("Record is read-only"); }
		if (_workgroup  &&  ! HCurrentUser.isInWorkgroup(_workgroup)) {
			// workgroup is already set for this record
			throw new HPermissionException("Non-administrator cannot remove workgroup restriction");
		}
		if (! HCurrentUser.isInWorkgroup(workgroup)) {
			// naughty!  You can't set the workgroup as one you're not a member of
			throw new HInvalidWorkgroupException("User is not a member of this workgroup");
		}
		_workgroup = workgroup;
		_modified = true;
	};

	this.getNonWorkgroupVisible = function() { return (_workgroup === null  ||  _nonOwnerVisible.toLowerCase !== 'hidden'); };
	this.setNonWorkgroupVisible = function(visible) {
		if (_readonly) { throw new HPermissionException("Record is read-only"); }
		if (! _workgroup) { return; }
		if (! HCurrentUser.isInWorkgroup(_workgroup)) {
			throw new HPermissionException("Non-member cannot change non-workgroup visibility");
		}
// saw removed 30/10/11		_nonOwnerVisible = visible? true : false;
		_nonOwnerVisible = (typeof visible == 'string' &&
									visible.toLowerCase() in {'hidden':1,'viewable':1,'pending':1,'public':1}?
										 visible.toLowerCase() : (!visible || visible == '0'? 'hidden':'viewable'));
		_modified = true;
	};

	this.getURLVerificationDate = function() { return _urlDate; };
	this.getURLError = function() { return _urlError; };

	this.getCreationDate = function() { return _creationDate; };
	this.getModificationDate = function() { return _modificationDate; };
	this.getCreator = function() { return _creator; };

	this.getHHash = function() {
		var hashFields;
		var rawValues, values;
		var i, j;

		if (_hhash) {
			// check whether there are any records referenced by this record which could have had their hhashes changed
			// (in which case our own hhash is invalidated)

			hashFields = HDetailManager.getRequiredDetailTypesForRecordType(_type);
			for (i=0; _hhash && i < hashFields; ++i) {
				if (hashFields[i].getVariety() !== HVariety.REFERENCE) { continue; }

				rawValues = _namedDetails[hashFields[i].getID()]  ||  [];
				for (j in rawValues) {
					if (rawValues[j].getRecord() && rawValues[j].getRecord().isModified()) {
						_hhash = null;
						break;
					}
				}
				if (! _hhash) { break; }

				rawValues = _details[hashFields[i].getID()]  ||  [];
				for (j=0; j < rawValues.length; ++j) {
					if (rawValues[j].getRecord() && rawValues[j].getRecord().isModified()) {
						_hhash = null;
						break;
					}
				}
			}
			if (_hhash) { return _hhash; }
		}

		// We don't have a pre-cached hhash, have to recalculate it.  Don't worry: this is easy!
		hashFields = HDetailManager.getMatchingDetailTypesForRecordType(_type);

		var hhash = ((_type && _type.getID())? _type.getID() : "N") + ":";

		// go through the non-resource types first
		for (i=0; i < hashFields.length; ++i) {
			if (hashFields[i].getVariety() === HVariety.REFERENCE) { continue; }
			values = [];
			rawValues = _namedDetails[hashFields[i].getID()]  ||  [];
			for (j in rawValues) {
				values.push(rawValues[j].toUpperCase().replace(/[^\0-\10\16-\37\60-\71\101-\132\141-\172\177]+/g, ""));
			}
			rawValues = _details[hashFields[i].getID()]  ||  [];
			for (j=0; j < rawValues.length; ++j) {
				values.push(rawValues[j].toUpperCase().replace(/[^\0-\10\16-\37\60-\71\101-\132\141-\172\177]+/g, ""));
			}
			values.sort();
			hhash += values.join(";");
			if (values) { hhash += ";"; }
		}

		hashFields = HDetailManager.getRequiredDetailTypesForRecordType(_type);
		for (i=0; i < hashFields.length; ++i) {
			if (hashFields[i].getVariety() !== HVariety.REFERENCE) { continue; }
			values = [];
			rawValues = _namedDetails[hashFields[i].getID()]  ||  [];
			for (j in rawValues) {
				values.push("^" + rawValues[j].getHHash() + "$");
			}
			rawValues = _details[hashFields[i].getID()]  ||  [];
			for (j=0; j < rawValues.length; ++j) {
				values.push("^" + rawValues[j].getHHash() + "$");
			}
			values.sort();
			hhash += values.join("");
		}

		_hhash = hhash;
		return hhash;
	};

	this.isPersonalised = function() {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		// It's not enough to check if _bookmarkID is non-null:
		// if the record has been personalised since the last save then bookmark-ID is not yet known
		return _isPersonalised? true : false;
	};
	this.getBookmarkID = function() { return _bookmarkID  ||  null; };	// internal use only
	this.addToPersonalised = function() {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		if (_isPersonalised) { return; }
		_isPersonalised = true;
		_modified = true;
	};
	this.removeFromPersonalised = function() {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		if (! _isPersonalised) { return; }
		_isPersonalised = false;
		_bookmarkID = null;
		_personalNotes = null;
		_rating = "";
		_tags = [];
		_modified = true;
	};

	this.getPersonalNotes = function() {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		if (! _isPersonalised) { throw new HNotPersonalisedException(); }
		return _personalNotes;
	};
	this.setPersonalNotes = function(notes) {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		if (! _isPersonalised) { throw new HNotPersonalisedException(); }
		_personalNotes = notes;
		_modified = true;
	};

	this.getRating = function() {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		if (! _isPersonalised) { throw new HNotPersonalisedException(); }
		if (_rating) {
			return _rating;
		}
		return HRatings.defaultRating;
	};
	this.setRating = function(ratingValue) {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		if (! _isPersonalised) { throw new HNotPersonalisedException(); }
		if (HRatings.isValidRating(ratingValue)) {
			// Check that the rating is okay
			_rating = ratingValue;
			_modified = true;
		}	// ... otherwise the existing rating is kept (silently!)
	};

	this.getTags = function() {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		if (! _isPersonalised) { throw new HNotPersonalisedException(); }
		// Return a copy of the tags array -- prevents tampering
		return _tags.slice(0);
	};
	this.addTag = function(tag) {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		if (! _isPersonalised) { throw new HNotPersonalisedException(); }

		var verifiedTag = HTagManager.getTag(tag);
		if (verifiedTag !== null) {
			if (! _tagsMap[verifiedTag]) {
				// Tag doesn't already exist
				_tags.push(verifiedTag);
				_tagsMap[verifiedTag] = true;
				_modified = true;
			}
		}
		else { throw new HUnknownTagException(); }
	};
	this.removeTag = function(tag) {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		if (! _isPersonalised) { throw new HNotPersonalisedException(); }

		var verifiedTag = HTagManager.getTag(tag);
		var i;
		if (verifiedTag !== null  &&  _tagsMap[verifiedTag]) {
			for (i=0; i < _tags.length; ++i) {
				if (_tags[i] === verifiedTag) {
					// Found the tag in the list; remove it
					_tags.splice(i, 1);
					delete _tagsMap[verifiedTag];
					_modified = true;
					break;
				}
			}
		}
	};

	this.getWgTags = this.getKeywords = function() { return _wgTags.slice(0); };
	this.addWgTag = this.addKeyword = function(tag) {
		/* PRE */ if (! HAPI.isA(tag, "HWorkgroupTag")) { throw new HTypeException("HWorkgroupTag object required"); }
		if (! HCurrentUser.isInWorkgroup(tag.getWorkgroup())) {
			// This shouldn't happen ... you shouldn't be able to get tags for workgroups you're not a member of
			throw new HInvalidWorkgroupException("User is not a member of tag's workgroup");
		}

		if (! _wgTagsMap[tag.getID()]) {
			_wgTags.push(tag);
			_wgTagsMap[tag.getID()] = true;
			_modified = true;
		}
	};
	this.removeWgTag = this.removeKeyword = function(tag) {
		/* PRE */ if (! HAPI.isA(tag, "HWorkgroupTag")) { throw new HTypeException("HWorkgroupTag object required"); }
		if (! HCurrentUser.isInWorkgroup(tag.getWorkgroup())) {
			// This shouldn't happen ... you shouldn't be able to get tags for workgroups you're not a member of
			throw new HInvalidWorkgroupException("User is not a member of tag's workgroup");
		}

		var i;
		if (_wgTagsMap[tag.getID()]) {
			for (i=0; i < _wgTags.length; ++i) {
				if (_wgTags[i] === tag) {
					// Found the tag in the list; remove it
					_wgTags.splice(i, 1);
					delete _wgTagsMap[tag.getID()];
					_modified = true;
					break;
				}
			}
		}
	};

	this.getNotifications = function() {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		if (! _isPersonalised) { throw new HNotPersonalisedException(); }
		return _notifications.slice(0);
	};
	this.addNotification = function(recipient, message, startDate, frequency) {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		if (! _isPersonalised) { throw new HNotPersonalisedException(); }

		var newNotification;
		try {
			newNotification = new HNotification(null, that, recipient, message, startDate, frequency);
			_notifications.push(newNotification);
			_addedNotifications.push(newNotification);
		} catch (e) {
			// Localise the source of the exception
			throw e;
		}
		_modified = true;

		return newNotification;
	};
	this.removeNotification = function(notification) {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }

		for (var i=0; i < _notifications.length; ++i) {
			if (_notifications[i] === notification) {
				_notifications.splice(i, 1);
				if (notification.getID()) {
					// Only put a notification on the remove list if it is known to upstream storage
					_removedNotifications.push(notification);
					_modified = true;
				}
				break;
			}
		}
	};

	this.getComments = function() {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		return _comments.slice(0);
	};
	this.addComment = function(comment) {
		// Slightly byzantine method of checking that this function is only called by the Comment constructor
		/* PRE */ if (that.addComment.caller !== comment.constructor) { throw "Do not call HRecord::addComment"; }
		_addedComments.push(comment);
		if (! comment.getParent()) {
			_comments.push(comment);
		}
		_modified = true;
	};
	this.modifyComment = function(comment) {
		/* PRE */ if (that.modifyComment.caller !== comment.setText) { throw "Do not call HRecord::modifyComment"; }
		_modifiedComments.push(comment);
		_modified = true;
	};
	this.removeComment = function(comment) {
		if (_readonly) { throw new HPermissionException("Record is read-only"); }
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		if (comment.getUser() !== HCurrentUser) { throw new HPermissionException(); }

		var i;
		if (comment.getParent()  &&  comment.getID()) {
			// comments that are already existing, and are replies to other comments
			_removedComments.push(comment);
			_modified = true;
		}
		else {
			for (i=0; i < _comments.length; ++i) {
				// top-level comments ...
				if (_comments[i] === comment) {
					_comments.splice(i, 1);
					if (comment.getID()) {
						// a top-level comment that has already been saved
						_removedComments.push(comment);
						_modified = true;
						return;
					}
				}
			}

			for (i=0; i < _addedComments.length; ++i) {
				// ... and the comments that the server doesn't yet know about, top-level and otherwise
				if (_addedComments[i] === comment) {
					_addedComments.splice(i, 1);
					return;
				}
			}
		}
	};

	this.getRelatedRecords = function() { return _storageManager.getRelatedRecordsFor(that); };
	this.getRelationships = function() { return _storageManager.getRelationshipsFor(that); };
	this.getAnonymousRelativeNonces = function() {
		// Go through all records that this links to, and return a list of those that have not been saved
		var relatives = [];
		var relativesMap = {};
		var type, i, record;
		for (type in _details) {
			if (_details[type]  &&  HAPI.isA(_details[type][0], "HRecord")) {
				for (i=0; i < _details[type].length; ++i) {
					record = _details[type][i];
					if (! record.getID()  &&  ! relativesMap[record.getNonce()]) {
						relatives.push(record.getNonce());
						relativesMap[record.getNonce()] = true;
					}
				}
			}
		}
		return relatives;
	};

	this.hasError = function() { return _error && _error.length > 0 ? true : false };
	this.getError = function() { return _error };
	this.setError = function(err) { _error = err; }; //TODO add checking to ensure this is an array
	this.hasWarning = function() { return _warning && _warning.length > 0 ? true : false };
	this.getWarning = function() { return _warning };
	this.setWarning = function(warn) { _warning = warn; }; //TODO add checking to ensure this is an array
	this.isReadOnly = function() { return _readonly? true : false; };

	this.isModified = function() { return _modified? true : false; };

	this.isValid = function() {
		var requirements = HDetailManager.getRequiredDetailTypesForRecordType(_type);
		if (! requirements) { return true; }

		for (var i=0; i < requirements.length; ++i) {
			if (! _details[requirements[i].getID()]  &&  ! _namedDetails[requirements[i].getID()]) { return false; }
		}
		return true;
	};

	this.toJSO = function() {
		/* Create a JSO-representation of this record in HTTP POST format;
		 * if fullDetails is truthy, then the nonce of this record will be embedded in each variable name
		 */
		var bits = [];
		var i, j;

		var param;
		var type;
		var key, value;
		var details;

		/* private */ function encodeDetail(val, nonceOkay) {
			if (HAPI.isA(val, "HRecordStub")) {
				if (val.getID()) { return val.getID(); }
				else if (nonceOkay  &&  val.getRecord()) { return val.getRecord().getNonce(); }
				else { throw new HUnsavedRecordException("Cannot save reference to unsaved record"); }
			} else if (HAPI.isA(val, "HRecord")  &&  ! HAPI.isA(value, "HNotes")) {
				if (val.getID()) { return val.getID(); }
				else if (nonceOkay) { return val.getNonce(); }
				else { throw new HUnsavedRecordException("Cannot save reference to unsaved record"); }
			}else if (HAPI.isA(val, "HFile")) {
				return (val.getID()>0)?val.getID():val.getURL();
			}else if (HAPI.isA(val, "HGeographicValue")) {
				return (HGeographicType.abbreviationForType(val.getType()) + " " + val.getWKT());
			}else if (typeof(val) === "object"  &&  val.constructor === Date) {
				return 	(val.getFullYear() + "-" +
					(val.getMonth() < 9  ?  "0" + (val.getMonth()+1)  :  val.getMonth()+1) + "-" +
					(val.getDate() <= 9  ?  "0" + val.getDate()  :  val.getDate()) +
					(val.getSeconds() != 0 ? " "+  (val.getHours() < 10 ? "0"+ val.getHours() : val.getHours()) + ":" +
					(val.getMinutes() < 10 ? "0"+ val.getMinutes() : val.getMinutes()) + ":" +
					(val.getSeconds() < 10 ? "0"+ val.getSeconds() : val.getSeconds()) :
					((val.getHours() !=0 || val.getMinutes()!=0) ? " " + (val.getHours() < 10 ? "0"+ val.getHours() : val.getHours()) + ":" +
					(val.getMinutes() < 10 ? "0"+ val.getMinutes() : val.getMinutes()) : "")));
			}else{
				return (val + "");
			}
		}

		var jso = {
			id: (_id || ""),
			type: _type.getID(),
			url: (_url || ""),
			notes: (_notes || ""),
			group: (_workgroup? _workgroup.getID() : ""),
			vis: (_nonOwnerVisible || ""),

			bookmark: (_isPersonalised? 1 : 0),
			pnotes: (_personalNotes || ""),
			rating: _rating,
			tags: (_tags.join(",") || "")
		};
		var wgTags = [];
		for (i=0; i < _wgTags.length; ++i) {
			wgTags.push(_wgTags[i].getID());
		}
		jso.wgTags = wgTags.join(",");

		jso.detail = {};
		for (type in _namedDetails) {
			jso.detail["t:"+type] = {};

			details = _namedDetails[type];
			for (bdID in details) {
				jso.detail["t:"+type]["bd:"+bdID] = (details[bdID] === null ? null :encodeDetail(details[bdID],true));
			}
		}
		for (type in _details) {
			if (! jso.detail["t:"+type]) { jso.detail["t:"+type] = {}; }

			details = _details[type];
			for (i=0; i < details.length; ++i) {
				jso.detail["t:"+type][i] = (details[i] === null ? null : encodeDetail(details[i],true)) ;
			}
		}

		// List the notifications to remove, by their id#
		jso["-notify"] = [];
		for (i=0; i < _removedNotifications.length; ++i) {
			jso["-notify"].push(_removedNotifications[i].getID());
		}

		// List the notifications to add
		jso["+notify"] = [];
		for (i=0; i < _addedNotifications.length; ++i) {
			jso["+notify"].push(_addedNotifications[i].toJSO());
		}

		// List the comments to remove, by their id#
		jso["-comment"] = [];
		for (i=0; i < _removedComments.length; ++i) {
			jso["-comment"].push(_removedComments[i].getID());
		}

		// List the comments to modify
		jso["comment"] = [];
		for (i=0; i < _modifiedComments.length; ++i) {
			jso["comment"].push(_modifiedComments[i].toJSO());
		}

		// List the comments to add
		jso["+comment"] = [];
		for (i=0; i < _addedComments.length; ++i) {
			jso["+comment"].push(_addedComments[i].toJSO());
		}

		return jso;
	};

		// setAll relies on the loadSearch ordering of record data
	this.setAll = function(sm, id, version, type, title, details, url, notes, wg, nonwgVis, urlDate, urlError, cDate, mDate, creator, hhash, bkmkID, pNotes, rating, irate, qrate, tags, wgTags, readonly) {
		// Set all the details (even the secret ones!) for a record in one place ... only available to the storage manager
		if (! HAPI.isA(sm, "HStorageManager")) { throw "Do not call HRecord::setAll"; }
		// saw TODO add code to check irate and qrate are null as they are deprecated
		var i;
		_storageManager = sm;
		_id = id;
		_version = version;
		_type = HRecordTypeManager.getRecordTypeById(type);
		_title = title;
		_namedDetails = details;
		_url = url;
		_notes = notes;
		_workgroup = wg? HWorkgroupManager.getWorkgroupById(wg) : null;
		_nonOwnerVisible = (typeof nonwgVis == 'string' &&	// if vis is set just usw it otherwise if ole value is 0 then use hidden default to viewable
									nonwgVis.toLowerCase() in {'hidden':1,'viewable':1,'pending':1,'public':1}?
										 nonwgVis.toLowerCase() : (!nonwgVis || nonwgVis == '0'? 'hidden':'viewable'));
		_urlDate = urlDate;
		_urlError = urlError;
		_creationDate = cDate;
		_modificationDate = mDate;
		_creator = creator ? HUserManager.getUserById(creator) : null;
		_hhash = hhash;
		if (bkmkID) {
			_isPersonalised = true;
			_bookmarkID = bkmkID;
			_personalNotes = pNotes;
			_rating = rating;
			_tags = tags || [];
			_tagsMap = {};
			for (i=0; i < _tags.length; ++i) { _tagsMap[_tags[i]] = true; }

			_addedNotifications = [];
			_removedNotifications = [];
		}

		_wgTags = wgTags || [];
		_wgTagsMap = {};
		for (i=0; i < _wgTags.length; ++i) {
			_wgTagsMap[_wgTags[i]] = true;
			_wgTags[i] = HWorkgroupTagManager.getWgTagById(_wgTags[i]);
		}

		_addedComments = [];
		_removedComments = [];
		_modifiedComments = [];

		_readonly = readonly;
		_modified = false;	// mark this as UNMODIFIED (this is our starting point)
	};
	this.setNotificationsAndComments = function(sm, notifications, comments) {
		// Set some more details for a record in one place ... only available to the storage manager
		if (! HAPI.isA(sm, "HStorageManager")) { throw "Do not call HRecord::setNotificationsAndComments"; }
		_notifications = notifications;
		_comments = comments;
	};
	this.saveChanges = function(sm, id, version, bkmkID, title, detailIDs, notifyIDs, commentIDs, mDate, warnings) {
		// Called internally by the HStorageManager to update the local HRecord when it is saved.
		// We assume that (e.g.) the actual values of the details were saved alright,
		// but we need to know what their IDs are in their respective tables.

		if (! HAPI.isA(sm, "HStorageManager")) { throw "Do not call HRecord::setID"; }
		_id = id;
		_version = version;
		_bookmarkID = bkmkID;
		_title = title  ||  null;

		// This is a bit obscure:
		// the server returns a list of the new bdIDs for the details in _details,
		// so now we can put them into _namedDetails
		var i, type, tmpDetails = [];
		for (type in _details) {
			for (i=0; i < _details[type].length; ++i) {
				tmpDetails.push([type, _details[type][i]]);
			}
		}
		if ((! detailIDs.inserted  &&  tmpDetails.length > 0)  ||  (detailIDs.inserted  &&  tmpDetails.length !== detailIDs.inserted.length)) {
			/* FIXME: an inconsistency from the server.  Should reload this record, huh. */
			console.log("saveChanges: " + this.toString() + ": detailIDs / _details mismatch");
		}

		var detailTypeID;
		for (i=0; i < tmpDetails.length; ++i) {// WARNING this code relies on the order of inserts to remain the same
			detailTypeID = tmpDetails[i][0];
			if (! _namedDetails[detailTypeID]) {
				_namedDetails[detailTypeID] = {};
			}
			_namedDetails[detailTypeID][detailIDs.inserted[i]] = tmpDetails[i][1];
		}
		_details = [];

		if (detailIDs.translatedIDs) { // we had to translate the id as the detail ID was bad but the user has changed the value
			for (i=0; i < detailIDs.translatedIDs.length; i++) {
				var oldID = detailIDs.translatedIDs[i];
				var newID = detailIDs.translated[oldID]['new_bdID'];
				var dtType = detailIDs.translated[oldID]['dtType'];
				if (_namedDetails[dtType] && _namedDetails[dtType][oldID]) {
					_namedDetails[dtType][newID] = _namedDetails[dtType][oldID]; // ok since the old ID's value was used to insert
					delete _namedDetails[dtType][oldID];
				}
			}
		}

		if (warnings) {
			_error = [];
			_warning = warnings;
		}

		if (notifyIDs) {
			for (i=0; i < notifyIDs.length; ++i) {
				if (notifyIDs[i].id  &&  _addedNotifications[i]) {
					_addedNotifications[i].setID(notifyIDs[i].id);
	// FIXME: should check errors etc
				}
			}
		}
		_addedNotifications = [];
		_removedNotifications = [];

		if (commentIDs) {
			for (i=0; i < commentIDs.length; ++i) {
				if (commentIDs[i].id  &&  _addedComments[i]) {
					_addedComments[i].setID(commentIDs[i].id);
				}
			}
		}
		_addedComments = [];
		_removedComments = [];
		_modifiedComments = [];

		_modificationDate = mDate;
		if (! _creationDate) _creationDate = mDate;
		if (! _creator) _creator = HCurrentUser;

		_modified = false;	// mark this as UNMODIFIED (this is our starting point)
	};

	this.reload = function(sm, loader) {
		/* PRE */ if (! HAPI.isA(sm, "HStorageManager")) { throw new HTypeException("HStorageManager object required for argument #1"); }
		/* PRE */ if (loader  &&  ! HAPI.isA(loader, "HLoader")) { throw new HTypeException("HLoader object required for argument #2"); }

		if (! _id) { throw new HUnsavedRecordException("Cannot reload unsaved record"); }
		if (_locked) { _locked = false; } // reload overrides locking

		sm.loadRecords(new HSearch("ids:"+_id), loader);
	};

	this.loadRelated = function(sm, loader) {
		/* PRE */ if (! HAPI.isA(sm, "HStorageManager")) { throw new HTypeException("HStorageManager object required for argument #1"); }
		/* PRE */ if (loader  &&  ! HAPI.isA(loader, "HLoader")) { throw new HTypeException("HLoader object required for argument #2"); }

		if (! _id) { throw new HUnsavedRecordException("Cannot reload unsaved record"); }
		if (_locked) { _locked = false; } // reload overrides locking

		//sm.loadRecords(new HSearch("relatedto:"+_id+" OR linkto:"+_id+" OR linkedto:"+_id), loader);
		sm.loadRecords(new HSearch("relationsfor:"+_id), loader);
	};

	var _locked = false;
	this.lock = function() { _locked = true; };
	this.unlock = function() { _locked = false; };
	this.isLocked = function() { return _locked; };

	this.internalLock = function() { _readonly = true; };
	this.internalUnlock = function() { _readonly = false; };
};
HAPI.inherit(HRecord, HObject);
HRecord.getClass = function() { return "HRecord"; };
HAPI.Record = HRecord;

var HRelationship = function(primaryRecord, relationshipType, secondaryRecord) {
	var that = this;

	HRecord.apply(this);	// inherit methods / private vars from HRecord

	if (! HRelationship.PrimaryRecordType) {
		/* Keep a static storage of the types essential to a relationship record */
		HRelationship.PrimaryRecordType = HDetailManager.getDetailTypeById(top.HEURIST.magicNumbers['DT_PRIMARY_RESOURCE']);
		HRelationship.SecondaryRecordType = HDetailManager.getDetailTypeById(top.HEURIST.magicNumbers['DT_TARGET_RESOURCE']);
	}
	if (! HRelationship.RelationshipTypeType) {
		HRelationship.RelationshipTypeType = HDetailManager.getDetailTypeById(top.HEURIST.magicNumbers['DT_RELATION_TYPE']);
		HRelationship.relatedEnums = HRelationship.RelationshipTypeType.getRelatedEnumerationValues();
	}

	this.setRecordType(HRecordTypeManager.getRecordTypeById(top.HEURIST.magicNumbers['RT_RELATION']));

	if (arguments) {
		try { this.addDetail(HRelationship.PrimaryRecordType, primaryRecord); }
		catch (e) { throw new HTypeException("HRecord object required for argument #1"); }

		try { this.addDetail(HRelationship.SecondaryRecordType, secondaryRecord); }
		catch (e) { throw new HTypeException("HRecord object required for argument #3"); }

		try { this.addDetail(HRelationship.RelationshipTypeType, relationshipType); }
		catch (e) { throw new HTypeException("Valid relationship-type required for argument #2"); }
	}

	this.getPrimaryRecord = function() { var val = that.getDetails(HRelationship.PrimaryRecordType); return val? val[0] : null; };
	this.getSecondaryRecord = function() { var val = that.getDetails(HRelationship.SecondaryRecordType); return val? val[0] : null; };
	this.getType = function() { var val = that.getDetails(HRelationship.RelationshipTypeType); return val? val[0] : null; };
	this.getInverseType = function() {
		return (HRelationship.relatedEnums[that.getType()]  ||  "inverse of " + that.getType());
	};
};
HAPI.inherit(HRelationship, HRecord);
HRelationship.getClass = function() { return "HRelationship"; };
HRelationship.getRelationshipTypes = function() {
	if (! HRelationship.RelationshipTypeType) {
		HRelationship.RelationshipTypeType = HDetailManager.getDetailTypeById(top.HEURIST.magicNumbers['DT_RELATION_TYPE']);
	}
	return HRelationship.RelationshipTypeType.getEnumerationValues();
};
HAPI.Relationship = HRelationship;

var HNotes = function() {
	/* Notes in Heurist have been a nasty, ugly, evil hack from the very beginning.
	 * Ideally we should probably define some abstraction above HRecord from which HNotes inherits,
	 * rather than overload HRecord's functions.
	 */

	var returnTrue = function() { return true; };
	var returnNull = function() { return null; };
	var throwException = function() { throw new HInvalidRecordTypeException(); };

	var _personalTitle = null, _personalURL = null;

	var _modified = false;

	HRecord.call(this);

	this.getID = returnNull;
	this.getVersion = returnNull;

	this.getTitle = function() { return _personalTitle; };
	this.setTitle = function(title) { _personalTitle = title; _modified = true; };
	this.getURL = function() { return _personalURL; };
	this.setURL = function(URL) { _personalURL = URL; _modified = true; };
	this.getNotes = this.getPersonalNotes;
	this.setNotes = this.setPersonalNotes;

	this.setRecordType = throwException;

	this.getWorkgroup = throwException;
	this.setWorkgroup = throwException;
	this.getNonWorkgroupVisible = throwException;
	this.setNonWorkgroupVisible = throwException;

	this.getURLVerificationDate = returnNull;
	this.getURLError = returnNull;

	this.getHHash = returnNull;

	this.getDetails = throwException;
	this.addDetail = throwException;
	this.removeDetails = throwException;
	this.setDetails = throwException;

	this.isPersonalised = returnTrue;
	this.addToPersonalised = returnNull;
	this.removeFromPersonalised = returnNull;

	this.getKeywords = throwException;
	this.addKeyword = throwException;
	this.removeKeyword = throwException;
	this.getWgTags = throwException;
	this.addWgTag = throwException;
	this.removeWgTag = throwException;

	this.getNotifications = throwException;
	this.addNotification = throwException;
	this.removeNotification = throwException;

	this.getComments = throwException;
	this.removeComment = throwException;

	this.getRelatedRecords = throwException;
	this.getRelationships = throwException;

	this.isReadOnly = function() { return false; };
	this.isModified = function() { return _modified? true : false; };
	this.isValid = returnTrue;
};
HNotes.getClass = function() { return "HNotes"; };
HAPI.inherit(HNotes, HRecord);
HAPI.Notes = HNotes;


var HAnonymousAuthor = new function() {
	this.prototype = new HRecord();
	// FIXME: need to handle this carefully.  What functions need to be overridden?  What values do they return?
}();
HAPI.AnonymousAuthor = HAnonymousAuthor;


var HRecordStub = function(id, title, hhash, sm) {
	var _id = id;
	var _title = title;
	var _hhash = hhash;
	var _storageManager = sm;

	this.getID = function() { return _id; };
	this.getTitle = function() { return _title; };
	this.getHHash = function() { return _hhash; };
	this.getRecord = function() { return sm.getRecord(_id); };
};
HRecordStub.getClass = function() { return "HRecordStub"; };
HAPI.inherit(HRecordStub, HObject);
HAPI.RecordStub = HRecordStub;


var HNotification = function(id, record, recipient, message, startDate, frequency) {
	var _id = id;
	var _record = record;
	var _recipient = recipient;
	var _message = message;
	var _startDate = startDate;
	var _frequency = frequency;

	if (! HAPI.isA(record, "HRecord")  ||  HNotification.caller !== record.addNotification) {
		throw "HNotification must be constructed using HRecord::addNotification";
	}
	/* PRE */ if (! (HAPI.isA(recipient, "HUser")  ||  HAPI.isA(recipient, "HWorkgroup")  ||
			("" + recipient).match(/^[-!#$%*\/?|&^{}`~&'+=_A-Za-z0-9]+@[-.A-Za-z0-9]+$/) /* patent-pending: very simple email address regexp */)) {
		throw "Invalid notification recipient";
	}

	if (typeof(startDate) !== "object"  ||  startDate.constructor !== Date  ||  startDate.getTime() < (new Date()).getTime()) {
		throw "Invalid start date for notifications";
	}

	if (! HFrequency.isValidFrequency(frequency)) {
		throw "Invalid notification frequency";
	}

	this.toJSO = function() {
		var jso = {};

		if (HAPI.isA(recipient, "HUser")) { jso.user = recipient.getID(); }
		else if (HAPI.isA(recipient, "HWorkgroup")) { jso.workgroup = recipient.getID(); }
		else { jso.email = recipient; }

		jso.date = _startDate;
		jso.frequency = _frequency;
		jso.message = message;

		return jso;
	};

	this.setID = function(id) { _id = id; /* FIXME: make sure this is only called by saveRecord */ };
	this.getID = function() { return _id; };
	this.getRecord = function() { return _record; };
	this.getWorkgroupRecipient = function() { return HAPI.isA(_recipient, "HWorkgroup")? _recipient : null; };
	this.getUserRecipient = function() { return HAPI.isA(_recipient, "HUser")? _recipient : null; };
	this.getEmailRecipient = function() { return (("" + _recipient) === _recipient)? _recipient : null; };
	this.getRecipient = function() { return _recipient; };

	this.getMessage = function() { return _message; };
	this.getStartDate = function() { return _startDate; };
	this.getFrequency = function() { return _frequency; };
};
HNotification.getClass = function() { return "HNotification"; };
HAPI.inherit(HNotification, HObject);
HAPI.Notification = HNotification;


var HFrequency = {
	ONCE: "once",
	DAILY: "daily",
	WEEKLY: "weekly",
	MONTHLY: "monthly",
	ANNUALLY: "annually",

	isValidFrequency: function(value) {
		switch (value.toLowerCase()) {
			case "once":
			case "daily":
			case "weekly":
			case "monthly":
			case "annually":
			return true;
			default:
			return false;
		}
	},

	getClass: function() { return "HFrequency"; }
};
HAPI.Frequency = HFrequency;


var HDetailType = function(id, name, prompt, variety, enums, constraint) {
	var _id = id;
	var _name = name;
	var _prompt = prompt;
	var _variety = variety;
	var _enums = [];
	var _enumsMap = {};
	var _termsMap = {};
	var _relatedEnums = {};
	var _constraint = null;
	var _constrainedRecTypeID = null
	var i;


	if (constraint) {
		if (typeof constraint == "string") {
			constraint = constraint.split(",");
		}
		if( constraint instanceof Array) {
			_constraint = [];
			_constrainedRecTypeID = [];
			for (i=0; i<constraint.length; i++) {
				hRecType = HRecordTypeManager.getRecordTypeById(constraint[i]);
				if (hRecType) {
					_constraint.push(hRecType);
					_constrainedRecTypeID.push(constraint[i]);
				}
			}
		}
	}

	if (HAPI.DetailManager) {
		throw "Cannot construct new HDetailType objects";
	}

	var i;
	if (enums && enums instanceof Array) {

			// related values are given as well; enums is an array of string-string pairs
			// enum(trmID,trmLabel,[invID, invLabel])
			if (top.HEURIST && typeof top.HEURIST.terms != "undefined"){
				ciIndex = top.HEURIST.terms.fieldNamesToIndex['trm_ConceptID'];
				enumLookup = top.HEURIST.terms.termsByDomainLookup['enum'];
				relLookup = top.HEURIST.terms.termsByDomainLookup['relation'];

				for (i=0; i < enums.length; ++i) {
					_enums.push(enums[i][1]);
					_enumsMap[("" + enums[i][1]).toLowerCase()] = enums[i][1];//id or string to enum
					_enumsMap["" + enums[i][0]] = enums[i][1];
					_termsMap[("" + enums[i][1]).toLowerCase()] = enums[i][0];//string to term id
					if (variety === "enumeration" && enumLookup && enumLookup[enums[i][0]] && enumLookup[enums[i][0]][ciIndex].length > 0){
						_termsMap[("" + enumLookup[enums[i][0]][ciIndex])] = enums[i][0];//conceptID to term id
						_enumsMap[("" + enumLookup[enums[i][0]][ciIndex])] = enums[i][1];//conceptID to term id
					}
					if (variety === "relationtype" && relLookup && relLookup[enums[i][0]] && relLookup[enums[i][0]][ciIndex].length > 0){
						_termsMap[("" + relLookup[enums[i][0]][ciIndex])] = enums[i][0];//conceptID to term id
						_enumsMap[("" + relLookup[enums[i][0]][ciIndex])] = enums[i][1];//conceptID to term id
					}
					if (variety === "" && relLookup && relLookup[ciIndex] && relLookup[ciIndex].length > 0){
						_termsMap[("" + relLookup[ciIndex])] = enums[i][0];//conceptID to term id
					}
					if (variety === "relationtype" && enums[i][2] && enums[i][3]){ // there is an inverse term
						_relatedEnums[enums[i][0]] = enums[i][2];
						_relatedEnums[enums[i][1]] = enums[i][3];
						_termsMap[("" + enums[i][3]).toLowerCase()] = enums[i][2];
					}
				}
		}
	}

	this.getID = function() { return _id; };
	this.getName = function() { return _name; };
	this.getPrompt = function() { return _prompt; };
	this.getVariety = function() { return _variety; };
	this.getEnumerationValues = function() { return (_enums  ||  null); };
	this.getConstrainedRecordType = function() {
		if(_constraint){
			return _constraint[0];
		} else {
			return null;
		}
	};
	this.getConstrainedRecordTypes = function() {
		return _constraint;
	};
	this.getConstrainedRecordTypeIDs = function() {
		return _constrainedRecTypeID;
	};
	this.getRelatedEnumerationValues = function() { return _relatedEnums; };
	this.getIdForEnumerationValue = function(value) { return (isNaN(value) ? _termsMap[("" + value).toLowerCase()]  ||  null :
																			(_enumsMap[value] ? value : null)); };
	this.getEnumerationValueFromId = function(id) { return (isNaN(id) ? (_enumsMap[("" + id).toLowerCase()] || null) :
																			(_enumsMap[id] || null)); };



	this.checkValue = function(value) {
		if (! value  &&  (value !== 0  &&  value !== false)) {
			// Empty value doesn't count for anything
			return false;
		}

		var valueType = typeof(value);
		switch (_variety) {
			case HVariety.NUMERIC:

			return !isNaN(Number(value));

			case HVariety.LITERAL:
			case HVariety.BLOCKTEXT:
			case HVariety.BOOLEAN:
			return (valueType === "boolean"  ||  valueType === "number"  ||  valueType === "string");

			case HVariety.URLINCLUDE:
			return (valueType === "string");

			case HVariety.DATE:
			// Date object is alright; so are certain numbers and strings
			return ((valueType === "object"  &&  value.constructor === Date)
					||  valueType === "number"  ||  valueType === "string");

			case HVariety.ENUMERATION:
			case HVariety.RELATIONTYPE:
			// Check that we were passed a literal ...
			if (valueType !== "boolean"  &&  valueType !== "number"  &&  valueType !== "string") { return false; }
			// ... now check that it is one of the legitimate enumeration values for this type.
			return (_enumsMap[("" + value).toLowerCase()] !== undefined);	// saw TODO it's necessary to check enums by recType

			case HVariety.REFERENCE:
			return (HAPI.isA(value, "HRecordStub")  ||  (HAPI.isA(value, "HRecord")  &&  ! HAPI.isA(value, "HNotes")));

			case HVariety.FILE:
			return HAPI.isA(value, "HFile");

			case HVariety.GEOGRAPHIC:
			return HAPI.isA(value, "HGeographicValue");

			default:
			return false;
		}
	};
};
HDetailType.getClass = function() { return "HDetailType"; };
HAPI.inherit(HDetailType, HObject);
HAPI.DetailType = HDetailType;


var HVariety = {
	NUMERIC: "numeric",
	LITERAL: "literal",
	DATE: "date",
	ENUMERATION: "enumeration",
	RELATIONTYPE: "relationtype",
	REFERENCE: "reference",
	FILE: "file",
	GEOGRAPHIC: "geographic",
	BOOLEAN: "boolean",
	BLOCKTEXT: "blocktext",
	URLINCLUDE: "urlinclude",

	getClass: function() { return "HVariety"; }
};
HAPI.Variety = HVariety;


var HRequiremence = {
	REQUIRED: 'required',
	RECOMMENDED: 'recommended',
	OPTIONAL: 'optional',
	FORBIDDEN: 'forbidden',

	getClass: function() { return "HRequiremence"; }
};
HAPI.Requiremence = HRequiremence;


var HComment = function(parentObject, autoAdd) {
	var that = this;
	var _id = null;
	var _text = "";

	var _record;
	var _parentComment, _children = [];

	var _creationDate = new Date();
	var _modificationDate = null;

	var _user = HCurrentUser;

	if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }

	if (! parentObject  ||  typeof(parentObject) !== "object"  ||
		(! HAPI.isA(parentObject, "HRecord")  &&  ! HAPI.isA(parentObject, "HComment"))) {
		throw new HTypeException("HRecord or HComment object required");
	}
	if (HAPI.isA(parentObject, "HNotes")) {
		throw new HInvalidRecordTypeException("Cannot add comment to personal note");
	}

	if (HAPI.isA(parentObject, "HRecord")) {
		_record = parentObject;
		_parentComment = null;
	}
	else {	// isA "HComment"
		_record = parentObject.getRecord();
		_parentComment = parentObject;
		_parentComment.addReply(this);

		if (! _parentComment.getID()) {
			throw "Can't reply to un-saved comment";
		}
	}

	if (_record.isReadOnly()) {
		throw new HPermissionException("Record is read-only");
	}

	this.getID = function() { return _id; };
	this.setID = function(id) { _id = id; };

	this.getText = function() { return _text; };
	this.setText = function(text) {
		if (_user !== HCurrentUser) { throw new HPermissionException("You can't modify someone else's comment"); }
		if (_id  &&  _text !== text) { _record.modifyComment(this); }
		_text = text;
	};
	this.getParent = function() { return (_parentComment  ||  null); };
	this.getReplies = function() { return _children.slice(0); };
	this.removeReply = function(child) {
		for (var i=0; i < _children.length; ++i) {
			if (_children[i] === child) {
				_children.splice(i, 1);
				_record.removeComment(child);
				return;
			}
		}
		throw new HCommentMismatchException("Supplied HComment is not a reply to this HComment");
	};
	this.addReply = function(child) {
		if (that.addReply.caller !== HComment) { throw "Do not call HComment::addReply"; }
		_children.push(child);
	};
	this.setAll = function(id, date, modDate, text, user) {
		_id = id;
		_text = text;
		_creationDate = date;
		_modificationDate = modDate;
		_user = user;
	};
	this.getCreationDate = function() { return _creationDate; };
	this.getModificationDate = function() { return _modificationDate; };
	this.getUser = function() { return _user; };
	this.getRecord = function() { return _record; };
	this.toJSO = function() {
		// JSO only needs this ID, the ID of the parent-comment, and the text of the comment
		return {
			id: (_id || ""),
			parentComment: (_parentComment? _parentComment.getID() : ""),
			text: (_text || "")
		};
	};

	if (! autoAdd) { _record.addComment(this); }
};
HComment.getClass = function() { return "HComment"; };
HAPI.inherit(HComment, HObject);
HAPI.Comment = HComment;


var HSaver = function(onsaveCallback, onerrorCallback) {
	if (! onsaveCallback  ||  typeof(onsaveCallback) !== "function") {
		throw new HTypeError("Callback function required for argument #1");
	}
	if (onerrorCallback  &&  typeof(onerrorCallback) !== "function") {
		throw new HTypeError("Callback function required for argument #2");
	}

	this.onsave = onsaveCallback;
	this.onerror = onerrorCallback  ||  null;
};
HSaver.getClass = function() { return "HSaver"; };
HAPI.inherit(HSaver, HObject);
HAPI.Saver = HSaver;

var HDeletor = function(ondeleteCallback, onerrorCallback) {
	if (! ondeleteCallback  ||  typeof(ondeleteCallback) !== "function") {
		throw new HTypeError("Callback function required for argument #1");
	}
	if (onerrorCallback  &&  typeof(onerrorCallback) !== "function") {
		throw new HTypeError("Callback function required for argument #2");
	}

	this.ondelete = ondeleteCallback;
	this.onerror = onerrorCallback  ||  null;
};
HDeletor.getClass = function() { return "HDeletor"; };
HAPI.inherit(HDeletor, HObject);
HAPI.Deletor = HDeletor;


var HLoader = function(onloadCallback, onerrorCallback) {
	if (! onloadCallback  ||  typeof(onloadCallback) !== "function") {
		throw new HTypeError("Callback function required for argument #1");
	}
	if (onerrorCallback  &&  typeof(onerrorCallback) !== "function") {
		throw new HTypeError("Callback function required for argument #2");
	}

	this.onload = onloadCallback;
	this.onerror = onerrorCallback  ||  null;
};
HLoader.getClass = function() { return "HLoader"; };
HAPI.inherit(HLoader, HObject);
HAPI.Loader = HLoader;


var HSearch = function(query, options) {
	var _query = query;
	var _options = options  ||  {};

	if (! _query) { throw new HTypeException("Query string required for argument #1"); }

	this.getQuery = function() { return _query; };
	this.getOptions = function() { return _options; };
};
HSearch.getClass = function() { return "HSearch"; };
HAPI.inherit(HSearch, HObject);
HAPI.Search = HSearch;


var HFile = function(sm, id, originalName, size, type, URL, thumbnailURL, description) {
	var _id = id;
	var _originalName = originalName;
	var _size = size;
	var _type = type;  //extension
	var _URL = URL;
	var _thumbnailURL = thumbnailURL;
	var _description = description;

	if (HFile.caller !== sm.initFile) {
		throw "Cannot construct new HFile objects";
	}

	this.getID = function() { return _id; };
	this.getOriginalName = function() { return _originalName; };
	this.getSize = function() { return _size; };
	this.getType = function() { return _type; };
	this.getDescription = function() { return _description; };
	this.getURL = function() { return _URL; };
	this.getThumbnailURL = function() { return _thumbnailURL; };
};
HFile.getClass = function() { return "HFile"; };
HAPI.inherit(HFile, HObject);
HAPI.File = HFile;



var HTagManager = new function(tags) {
	// tags is an array of tag strings
	var _tags = tags || [];
	var _tagsMap = {};

	/* private */ function canonicalForm(tag) {
		return tag.replace(/^\s+|\s+$/g, '').toLowerCase();
	}

	for (var i=0; i < _tags.length; ++i) {
		_tagsMap[canonicalForm(_tags[i])] = _tags[i];
	}

	this.getAllTags = function() {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		return _tags.slice(0);
	};
	this.getTag = function(tag) {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		var _tag = _tagsMap[canonicalForm(tag)];
		return (_tag  ||  null);
	};
	this.getMatchingTags = function(term, count) {
		var regex, regexSafeTerm;
		var bits, firstBit;
		var startMatches, otherMatches, matches;
		var i;
		var match;

		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }

		if (term === "") { return []; }

		term = term.replace(/[^a-zA-Z0-9]/g, " ");	  // remove punctuation and other non-alphanums
		term = term.toLowerCase().replace(/^ +| +$/, "");	   // trim whitespace from start and end
		regexSafeTerm = term.replace(/[\\^.$|()\[\]*+?{}]/g, "\\$0");	   // neutralise any special regex characters
		if (regexSafeTerm.indexOf(" ") === -1) {
			// term is a single word ... look for it to start any word in the tag
			regex = new RegExp("(.?)\\b" + regexSafeTerm);
		}
		else {
			// multiple words: match all of them at the start of words in the tag
			/*
			for input
				WORD1 WORD2 WORD3 WORD4 ...
			want output
				(?=.*?\bWORD2)(?=.*?\bWORD3)(?=.*?\bWORD4) ... (^.*?)\bWORD1
			This matches all words in the search term using look-ahead (no matter what order they are in)
			except for the first word, which it prefers to match at the beginning of the tag.
			When it matches at the beginning, $1 is empty: we test this to float it to the top of the results.
			*/
			bits = regexSafeTerm.split(/ /);
			firstBit = bits.shift();

			regexSafeTerm = "(?=.*?\\b" + bits.join(")(?=.*?\\b") + ")(^.*?)\\b" + firstBit;
			regex = new RegExp(regexSafeTerm);
		}
		startMatches = [];
		otherMatches = [];

		for (i=0; i < _tags.length; ++i) {
			match = _tags[i].toLowerCase().match(regex);

			if (match  &&  match[1] === "") {
				startMatches.push(_tags[i]);
			}
			else if (match) {
				otherMatches.push(_tags[i]);
			}
		}

		// splice start matches at the beginning of the other matches
		matches = otherMatches;
		startMatches.unshift(0, 0);
		matches.splice.apply(matches, startMatches);

		if (matches.length > count) {
			return matches.slice(0, count);
		}
		else {
			return matches;
		}
	};
	this.addTag = function(tag) {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		if (tag.indexOf(",") >= 0) { throw "Invalid tag name (tags cannot contain commas)"; }

		var _tag = _tagsMap[canonicalForm(tag)];
		if (! _tag) {
			_tags.push(tag);
			_tagsMap[canonicalForm(tag)] = tag;
		}
	};
}(HAPI_userData.tags || []);
HTagManager.getClass = function() { return "HTagManager"; };
HTagManager.prototype = new HObject();
HAPI.TagManager = HTagManager;


var HWorkgroupManager = new function(workgroups, users) {
	/* workgroups is an array, each entry is a 5let of [id, name, longname, description, url] */
	var _workgroups = [];
	var _workgroupsMap = {};
	var _workgroupsNameMap = {};

	var wg, newWorkgroup;
	for (var i=0; i < workgroups.length; ++i) {
		wg = workgroups[i];
		newWorkgroup = new HWorkgroup(parseInt(wg[0]), wg[1], wg[2], wg[3], wg[4]);

		_workgroups.push(newWorkgroup);
		_workgroupsMap[wg[0]] = newWorkgroup;
		_workgroupsNameMap[wg[1].toLowerCase()] = newWorkgroup;
	}
	for (var i=0; i < users.length; ++i) {
		wg = users[i];
		newWorkgroup = new HWorkgroup(parseInt(wg[0]), wg[1], wg[2], 'user', '');

		_workgroups.push(newWorkgroup);
		_workgroupsMap[wg[0]] = newWorkgroup;
		_workgroupsNameMap[wg[1].toLowerCase()] = newWorkgroup;
	}

	this.getWorkgroupById = function(id) { return (_workgroupsMap[id] || null); };
	this.getWorkgroupByName = function(name) { return (_workgroupsNameMap[name.toLowerCase()] || null); };
	this.getWorkgroups = function() { return _workgroups.slice(0); };
}(HAPI_commonData.workgroups || [],HAPI_commonData.users || []);
HWorkgroupManager.prototype = new HObject();
HAPI.WorkgroupManager = HWorkgroupManager;


var HWorkgroupTagManager = new function(__wgTags) {
	/* wgTags is an array, each entry is a triplet of [id, name, workgroupID] */
	var _wgTags = [];
	var _wgTagsById = {};
	var _wgTagsByName = {};
	var _wgTagsByGroup = {};

	this.loadTags = function(wgTags){

		_wgTags = [];
		_wgTagsById = {};
		_wgTagsByName = {};
		_wgTagsByGroup = {};

		/* wgTags are constructed by the tag manager */
		var i, workgroup, newWgTag;
		for (i=0; i < wgTags.length; ++i) {
			workgroup = HWorkgroupManager.getWorkgroupById(wgTags[i][2]);
			if (! workgroup) { continue; }

			newWgTag = new HWorkgroupTag(parseInt(wgTags[i][0]), wgTags[i][1], workgroup);

			_wgTags.push(newWgTag);
			_wgTagsById[wgTags[i][0]] = newWgTag;
			_wgTagsByName[wgTags[i][1].toLowerCase()] = newWgTag;
			if (_wgTagsByGroup[wgTags[i][2]]) {
				_wgTagsByGroup[wgTags[i][2]].push(newWgTag);
			}
			else {
				_wgTagsByGroup[wgTags[i][2]] = [ newWgTag ];
			}
		}
	};

	this.getWgTagById = function(id) {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		return (_wgTagsById[id] || null);
	};
	this.getWgTagByName = function(name) {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		return (_wgTagsByName[name.toLowerCase()] || null);
	};
	this.getAllWgTags = function() {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		return _wgTags.slice(0);
	};
	this.getWorkgroupTags = function(workgroup) {
		if (! HCurrentUser.isLoggedIn()) { throw new HNotLoggedInException(); }
		if (! HCurrentUser.isInWorkgroup(workgroup)) {
			throw new HInvalidWorkgroupException("User is not a member of this workgroup");
		}
		return (_wgTagsByGroup[workgroup.getID()] || []).slice(0);
	};

	this.loadTags(__wgTags);
}(HAPI_userData.workgroupTags || []);
HWorkgroupTagManager.prototype = new HObject();
HAPI.WorkgroupTagManager = HWorkgroupTagManager;


var HRecordTypeManager = new function(types) {
	// types is an array of [id, name, mask] values

	var _types = [];
	var _typesById = {}, _typesByName = {};

	var i, newType;
	for (i=0; i < types.length; ++i) {
		newType = new HRecordType(parseInt(types[i][0]), types[i][1], types[i][2]);
		_types.push(newType);
		_typesById[types[i][0]] = newType;
		_typesByName[types[i][1].toLowerCase()] = newType;
	}

	var _privateNoteType = new HRecordType(null, "Private note");
	_typesByName["private note"] = _privateNoteType;

	this.getRecordTypes = function() { return _types.slice(0); };
	this.getRecordTypeById = function(id) { return id? (_typesById[id] || null) : _privateNoteType; };
	this.getRecordTypeByName = function(name) { return _typesByName[name.toLowerCase()] || null; };
}(HAPI_commonData.recordTypes);
HRecordTypeManager.prototype = new HObject();
HAPI.RecordTypeManager = HRecordTypeManager;


var HDetailManager = new function(detailTypes, detailRequirements) {
	// detailTypes is an array of  OLD VALUES[id, name, prompt, variety, enums, contraint] values
		// 0-dty_ID
		// 1-dty_Name
		// 2-dty_HelpText
		// 3-dty_Type
		// 4-enums [trmID, trmLabel [, invID, invLabel]]
		// 5-dty_PtrTargetRectypeIDs
		//-------------- TODO need to integrate new information
		// 6-dty_JsonTermIDTree
		// 7-dty_TermIDTreeNonSelectableIDs
		// 8-dty_ExtendedDescription
		// 9-dty_DetailTypeGroupID,
		//10-dty_FieldSetRecTypeID
		//11-dty_ShowInLists
		//12-dty_NonOwnerVisibility
	// detailRequirements is an array of
		// OLD VALUES [recordTypeID, detailTypeID, requiremence, repeatable, name, prompt, match, size, order, default] values
		// 0-recTypeID
		// 1-detailTypeID
		// ------------- slice point reorder
		// 2- 0-RequirementType
		// 3- 1-MaxValue
		// 4- 2-Name
		// 5- 3-HelpText
		// 6- 4-Match Order
		// 7- 5-DisplayWidth
		// 8- 6-Display Order
		// 9- 7-Extended Description
		//10- 8-Default Value
		//11- 9-MinValue
		//12- 10-DetailGroupID
		//13- 11-Filtered Enum Term IDs
		//14- 12-Extended Disabled Term IDs
		//15- 13-Detail Type Disabled Term IDs
		//16- 14-Filtered Pointer Constraint Rectype IDs
		//17- 15-Calc Function ID
		//18- 16-Thumbnail selection Order
		//19- 17-Status
		//20- 18-Non-Owner Visibility

	var _typesById = {};
	var _typesByName = {};
	var _detailTypesByRecordType = {};
	var _requiredDetailTypesByRecordType = {};
	var _recommendedDetailTypesByRecordType = {};
	var _optionalDetailTypesByRecordType = {};
	var _matchingDetailTypesByRecordType = {};

	var _recordPlusTypeDetails = {};

	var i, dt, newType;
	for (i=0; i < detailTypes.length; ++i) {
		dt = detailTypes[i];
		newType = new HDetailType(parseInt(dt[0]), dt[1], dt[2], dt[3], dt[4], dt[5]);
		_typesById[dt[0]] = newType;
		_typesByName[dt[1].toLowerCase()] = newType;
	}

	var dr, type;
	for (i=0; i < detailRequirements.length; ++i) {
		dr = detailRequirements[i];
		_recordPlusTypeDetails[dr[0]+"."+dr[1]] = dr.slice(2);
		type = null;
		if (dr[2] !== 'forbidden') {// non-excluded detail
			type = _typesById[dr[1]];
			if (_detailTypesByRecordType[dr[0]]) { _detailTypesByRecordType[dr[0]].push(type); }
			else { _detailTypesByRecordType[dr[0]] = [type]; }

			if (dr[2] === 'required') {// required detail
				if (_requiredDetailTypesByRecordType[dr[0]]) { _requiredDetailTypesByRecordType[dr[0]].push(type); }
				else { _requiredDetailTypesByRecordType[dr[0]] = [type]; }
			}
			else if (dr[2] === 'recommended') {// recommended detail
				if (_recommendedDetailTypesByRecordType[dr[0]]) { _recommendedDetailTypesByRecordType[dr[0]].push(type); }
				else { _recommendedDetailTypesByRecordType[dr[0]] = [type]; }
			}
			else {// optional detail
				if (_optionalDetailTypesByRecordType[dr[0]]) { _optionalDetailTypesByRecordType[dr[0]].push(type); }
				else { _optionalDetailTypesByRecordType[dr[0]] = [type]; }
			}
		}

		if (dr[6] && type) {
			if (_matchingDetailTypesByRecordType[dr[0]]) { _matchingDetailTypesByRecordType[dr[0]].push(type); }
			else { _matchingDetailTypesByRecordType[dr[0]] = [type]; }
		}
	}

	this.getDetailTypeById = function(id) { return (_typesById[id] || null); };
	this.getDetailTypeByName = function(name) { return (_typesByName[name.toLowerCase()] || null); };
	this.getDetailTypesForRecordType = function(recordType) {
		/* PRE */ if (! HAPI.isA(recordType, "HRecordType")) { throw new HTypeException("HRecordType object expected"); }
		return (_detailTypesByRecordType[recordType.getID()] || []).slice(0);
	};
	this.getRequiredDetailTypesForRecordType = function(recordType) {
		/* PRE */ if (! HAPI.isA(recordType, "HRecordType")) { throw new HTypeException("HRecordType object expected"); }
		return (_requiredDetailTypesByRecordType[recordType.getID()] || []).slice(0);
	};
	this.getRecommendedDetailTypesForRecordType = function(recordType) {
		/* PRE */ if (! HAPI.isA(recordType, "HRecordType")) { throw new HTypeException("HRecordType object expected"); }
		return (_recommendedDetailTypesByRecordType[recordType.getID()] || []).slice(0);
	};
	this.getOptionalDetailTypesForRecordType = function(recordType) {
		/* PRE */ if (! HAPI.isA(recordType, "HRecordType")) { throw new HTypeException("HRecordType object expected"); }
		return (_optionalDetailTypesByRecordType[recordType.getID()] || []).slice(0);
	};
	this.getMatchingDetailTypesForRecordType = function(recordType) {
		// Internal function, this one ... use it to construct the HHash, for database
		/* PRE */ if (! HAPI.isA(recordType, "HRecordType")) { throw new HTypeException("HRecordType object expected"); }
		return (_matchingDetailTypesByRecordType[recordType.getID()] || []).slice(0);
	};

	this.getDetailRequiremence = function(recordType, detailType) {
		/* PRE */ if (! HAPI.isA(recordType, "HRecordType")) { throw new HTypeException("HRecordType object expected for argument #1"); }
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object expected for argument #2"); }
		var details = _recordPlusTypeDetails[recordType.getID()+"."+detailType.getID()];
		return details? details[0] : HRequiremence.OPTIONAL;
	};
	this.getDetailRepeatable = function(recordType, detailType) {
		/* PRE */ if (! HAPI.isA(recordType, "HRecordType")) { throw new HTypeException("HRecordType object expected for argument #1"); }
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object expected for argument #2"); }
		var details = _recordPlusTypeDetails[recordType.getID()+"."+detailType.getID()];
		return (details  && (details[1] && details[1] != 1 || !details[1]))? true : false;
	};
	this.getDetailMaxRepeat = function(recordType, detailType) {
		/* PRE */ if (! HAPI.isA(recordType, "HRecordType")) { throw new HTypeException("HRecordType object expected for argument #1"); }
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object expected for argument #2"); }
		var details = _recordPlusTypeDetails[recordType.getID()+"."+detailType.getID()];
		return (details  &&  details[1] > 0)? details[1] : 0;
	};
	this.getDetailMinRequired = function(recordType, detailType) {
		/* PRE */ if (! HAPI.isA(recordType, "HRecordType")) { throw new HTypeException("HRecordType object expected for argument #1"); }
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object expected for argument #2"); }
		var details = _recordPlusTypeDetails[recordType.getID()+"."+detailType.getID()];
		return (details  &&  details[9]>0)? details[9] : 0;
	};
	this.getDetailNameForRecordType = function(recordType, detailType) {
		/* PRE */ if (! HAPI.isA(recordType, "HRecordType")) { throw new HTypeException("HRecordType object expected for argument #1"); }
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object expected for argument #2"); }
		var details = _recordPlusTypeDetails[recordType.getID()+"."+detailType.getID()];
		return (details  &&  details[2])? details[2] : detailType.getName();
	};
	this.getDetailPromptForRecordType = function(recordType, detailType) {
		/* PRE */ if (! HAPI.isA(recordType, "HRecordType")) { throw new HTypeException("HRecordType object expected for argument #1"); }
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object expected for argument #2"); }
		var details = _recordPlusTypeDetails[recordType.getID()+"."+detailType.getID()];
		return (details  &&  details[3])? details[3] : detailType.getPrompt();
	};
	this.getDetailMatching = function(recordType, detailType) {
		/* PRE */ if (! HAPI.isA(recordType, "HRecordType")) { throw new HTypeException("HRecordType object expected for argument #1"); }
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object expected for argument #2"); }
		var details = _recordPlusTypeDetails[recordType.getID()+"."+detailType.getID()];
		return (details  &&  details[4])? true : false;
	};
	this.getDetailMatchingOrder = function(recordType, detailType) {
		/* PRE */ if (! HAPI.isA(recordType, "HRecordType")) { throw new HTypeException("HRecordType object expected for argument #1"); }
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object expected for argument #2"); }
		var details = _recordPlusTypeDetails[recordType.getID()+"."+detailType.getID()];
		return (details  &&  details[4])? details[4] : null;
	};
	this.getDetailInputSize = function(recordType, detailType) {
		/* PRE */ if (! HAPI.isA(recordType, "HRecordType")) { throw new HTypeException("HRecordType object expected for argument #1"); }
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object expected for argument #2"); }
		var details = _recordPlusTypeDetails[recordType.getID()+"."+detailType.getID()];
		return (details  &&  details[5])? details[5] : null;
	};
	this.getDetailOrder = function(recordType, detailType) {
		/* PRE */ if (! HAPI.isA(recordType, "HRecordType")) { throw new HTypeException("HRecordType object expected for argument #1"); }
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object expected for argument #2"); }
		var details = _recordPlusTypeDetails[recordType.getID()+"."+detailType.getID()];
		return (details  &&  details[6])? details[6] : null;
	};
	this.getDetailDefaultValue = function(recordType, detailType) {
		/* PRE */ if (! HAPI.isA(recordType, "HRecordType")) { throw new HTypeException("HRecordType object expected for argument #1"); }
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object expected for argument #2"); }
		var details = _recordPlusTypeDetails[recordType.getID()+"."+detailType.getID()];
		return (details  &&  details[8])? details[8] : null;
	};
	this.isValidDetailValue = function(recordType, detailType, detailValue) {
		/* PRE */ if (! HAPI.isA(recordType, "HRecordType")) { throw new HTypeException("HRecordType object expected for argument #1"); }
		/* PRE */ if (! HAPI.isA(detailType, "HDetailType")) { throw new HTypeException("HDetailType object expected for argument #2"); }
		if (! detailType.checkValue(detailValue)) {
			throw new HDetailVarietyMismatchException("Unexpected value: '"+detailValue+"' for " + detailType.getVariety() + " value");
		}
		var details = _recordPlusTypeDetails[recordType.getID()+"."+detailType.getID()];
		//saw TODO for enum or relationtype check recstructure values
		return true;
	};

}(HAPI_commonData.detailTypes, HAPI_commonData.detailRequirements);
HDetailManager.prototype = new HObject();
HAPI.DetailManager = HDetailManager;


var HCurrentUser = function(userDetails) {
	/* if userDetails is provided, it is an array of the form [id, is-administrator, workgroupIDs, display-preferences] */
	var returnTrue = function() { return true; };
	var returnFalse = function() { return false; };
	var throwException = function() { throw new HNotLoggedInException(); };

	var user, dummyUser;
	var _workgroups = [], _workgroupsMap = {};
	var wg;

	var i;
	if (userDetails) {
		for (i=0; i < userDetails[2].length; ++i) {
			wg = HWorkgroupManager.getWorkgroupById(userDetails[2][i]);
			if (wg) {
				_workgroups.push(wg);
				_workgroupsMap[wg.getID()] = wg;
			}
		}

		user = HUserManager.getUserById(userDetails[0]);
		user.isLoggedIn = returnTrue;
		user.isAdministrator = userDetails[1]? returnTrue : returnFalse;
		user.isInWorkgroup = function(wg) { return (wg && wg.getID() && _workgroupsMap[wg.getID()])? true : false; };
		user.getWorkgroups = function() { return _workgroups.slice(0); };
		user.displayPreferences = userDetails[3];
		user.getDisplayPreference = function(name) { return (user.displayPreferences[name] || null); };
		return user;
	}
	else {	// no logged-in user
		dummyUser = {
			getID: throwException,
			getUsername: throwException,
			getRealName: throwException,
			isLoggedIn: returnFalse,
			isAdministrator: throwException,
			isInWorkgroup: returnFalse,
			getDisplayPreference: throwException,
			getClass: function() { return "HUser"; }
		};
		return dummyUser;
	}
}(HAPI_userData.currentUser);
HAPI.CurrentUser = HCurrentUser;


HAPI.XHR = {
	defaultURLPrefix: "",

	// XMLHttpRequest functions adapted from www.quirksmode.org
	sendRequest: function(url, callback, jso) {
		var req;
		if (HAPI.XHR.xhrPool.length < 1) {
			req = HAPI.XHR.createXMLHTTPObject();
		}
		else {
			req = HAPI.XHR.xhrPool.pop();
		}
		if (! req) { return; }

		if (url.match(/^[-a-zA-Z]+$/)) { /* only the HAPI method was supplied, not an absolute URL */
			url = HAPI.XHR.defaultURLPrefix + "hapi/php/" + url + ".php?db=" +
				(HAPI.database?HAPI.database:(HeuristInstance?HeuristInstance:
					(location.search.match(/db=([^&]+)/) ? location.search.match(/db=([^&]+)/)[1]: ""))); //saw FIXME: add instance code.
		}

		var method = jso? "POST" : "GET";
		req.open(method, url, true);

		req.setRequestHeader("User-Agent", "XMLHTTP/1.0");
		if (jso) {
			req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		}
		req.onreadystatechange = function() {
			if (req.readyState !== 4) { return; }
			if (req.status !== 200  &&  req.status !== 304) { return; }

			var response = HAPI.XHR.evalJSON(req.responseText) || { error: req.error };
			HAPI.XHR.releaseXMLHTTPObject(req);
			callback(response);
		};
		if (req.readyState === 4) { return; }
		if (jso) {
			req.send("data=" + encodeURIComponent(HAPI.XHR.convertToJSON(jso)));
		}
		else {
			req.send(null);
		}
	},

	XMLHttpFactories: [
		function () { return new XMLHttpRequest(); },
		function () { return new ActiveXObject("Msxml2.XMLHTTP"); },
		function () { return new ActiveXObject("Msxml3.XMLHTTP"); },
		function () { return new ActiveXObject("Microsoft.XMLHTTP"); }
	],

	createXMLHTTPObject: function() {
		var xmlhttp = null;
		for (var i=0; i < HAPI.XHR.XMLHttpFactories.length; i++) {
			try { xmlhttp =  HAPI.XHR.XMLHttpFactories[i](); }
			catch (e) { continue; }
			break;
		}
		return xmlhttp;
	},

	releaseXMLHTTPObject: function(xhr) {
		xhr.onreadystatechange = HAPI.XHR.nullFunction;
		HAPI.XHR.xhrPool.push(xhr);
	},
	nullFunction: function() {},
	xhrPool: [],

	evalJSON: function() {
		// Note that we use a different regexp from RFC 4627 --
		// the only variables available now to malicious JSON are those made up of the characters "e" and "E".
		// EEEEEEEEEEEEEEEEEEeeeeeeeeeeeeeeeeeEEEEEEEEEEEEEEEEEEEeEEEEEEEEEE
		var re1 = /"(\\.|[^"\\])*"|true|false|null/g;
		var re2 = /[^,:{}\[\]0-9.\-+Ee \n\r\t]/;
		return function(testString) {
			return ! re2.test(testString.replace(re1, " "))  &&  eval("(" + testString + ")");
		};
	}(),
	convertToJSON: function() {
		function f(n) {
			return n < 10 ? '0' + n : n;
		}

		Date.prototype.toJSON = function () {
			return this.getUTCFullYear()   + '-' +
				 f(this.getUTCMonth() + 1) + '-' +
				 f(this.getUTCDate())	  + 'T' +
				 f(this.getUTCHours())	 + ':' +
				 f(this.getUTCMinutes())   + ':' +
				 f(this.getUTCSeconds())   + 'Z';
		};


		var m = { '\b': '\\b', '\t': '\\t', '\n': '\\n', '\f': '\\f', '\r': '\\r', '"' : '\\"', '\\': '\\\\' };

		function stringify(value, whitelist) {
			var a, i, k, l, r = /["\\\x00-\x1f\x7f-\x9f]/g, v;
			var val;

			switch (typeof value) {
				case 'string':
				val = (r.test(value) ?
					'"' + value.replace(r, function (a) {
						var c = m[a];
						if (c) { return c; }
						c = a.charCodeAt();
						return '\\u00' + Math.floor(c / 16).toString(16) + (c % 16).toString(16);
					}) + '"' :
					'"' + value + '"');
				return val;

				case 'number':
				return isFinite(value) ? String(value) : 'null';

				case 'boolean':
				case 'null':
				return String(value);

				case 'object':
				if (! value) { return 'null'; }

				if (typeof value.toJSON === 'function') { return stringify(value.toJSON()); }
				a = [];
				if (typeof value.length === 'number' && !(value.propertyIsEnumerable('length'))) {
					l = value.length;
					for (i = 0; i < l; i += 1) {
						a.push(stringify(value[i], whitelist) || 'null');
					}
					return '[' + a.join(',') + ']';
				}
				if (whitelist) {
					l = whitelist.length;
					for (i = 0; i < l; i += 1) {
						k = whitelist[i];
						if (typeof k === 'string') {
							v = stringify(value[k], whitelist);
							if (v) { a.push(stringify(k) + ':' + v); }
						}
					}
				} else {
					for (k in value) {
						if (typeof k === 'string') {
							v = stringify(value[k], whitelist);
							if (v) {
								a.push(stringify(k) + ':' + v);
							}
						}
					}
				}

				return '{' + a.join(',') + '}';
			}
		}
		return stringify;
	}(),

	_xssWebPrefix: HeuristBaseURL + "hapi/php/dispatcher.php?",

	txURLLen: 1900,
	rxURLLen: (navigator.userAgent.match(/MSIE/))? 2000 : null,

	xssComm: function(method, callback, data, xssWebPrefix) {
		// A method for cross-site data send/receive using URL snippets
		// Note that URL length is severely restricted on IE, so use xssGet / xssPut instead

		var jsonData = HAPI.XHR.convertToJSON(data);
		if (jsonData.length * 4 / 3 > HAPI.XHR.txURLLen) {
			// too much data to transmit via URL fragments -- use xssPut instead
			return HAPI.XHR.xssPut(method, callback, data, xssWebPrefix);
		}

		var fr = HAPI.XHR.getIframe();
		var frOnload = function() {
			var data;
			var hash, token;
			try {
				hash = fr.contentWindow.document.location.hash;
				if ( (token=hash.match(/^#token=(data[a-f0-9]+)/)) ) {
					// too much data to transmit as a URL fragment -- use xssGet to retrieve it
					HAPI.removeListener(handler);
					setTimeout(function (){HAPI.XHR.releaseIframe(fr);},0);
					return HAPI.XHR.xssGet("fetchResultsFromSession", callback, { token: token[1] }, xssWebPrefix);
				} else if (hash.match(/^#data=/)) {
					data = HAPI.XHR.evalJSON(HAPI.base64.decode(decodeURIComponent(hash.substring(6))));	// #data=....
				}
				else {//saw FIXME: decide what todo for the default case.
				}
			} catch (e) {
				alert(e.description || e);
				return;
			}

			setTimeout(function() {
				HAPI.XHR.releaseIframe(fr);
				callback(data);
			}, 0);

			HAPI.removeListener(handler);
		};
		var handler = HAPI.addListener(fr, "load", frOnload);

		void( frames[fr.name].name );	// something about lazy evaluation perhaps -- this yanks the frame violently into existence.
		fr.contentWindow.location.replace((xssWebPrefix || HAPI.XHR._xssWebPrefix) + "method=" + encodeURIComponent(method) +
		//				"&key=" + encodeURIComponent(HAPI.key || "") +
				"&data=" + encodeURIComponent(HAPI.base64.encode(jsonData)) +
				"&db=" + encodeURIComponent(HAPI.database || window.HeuristInstance) +	// saw TODO: check if there is a more appropriate db name location
				(HAPI.XHR.rxURLLen? ("&rxlen="+HAPI.XHR.rxURLLen) : ""));
	},

	iframePool: [],
	iframePoolByName: {},
	getIframe: function(src) {
		var fr;
		var name;

		if (HAPI.XHR.iframePool.length > 0) {
			fr = HAPI.XHR.iframePool.pop();
		}
		else {
			// Pick a random (unique) frame name
			do {
				name = "fr" + Math.round(Math.random() * 1000000);
			} while (HAPI.XHR.iframePoolByName[name]);

			try {
				// try creating the iframe IE style
				fr = document.createElement("<iframe name=\"" + name + "\">");
			} catch (e) {
				fr = document.createElement("iframe");
				fr.name = name;
			}
			fr.style.position = "absolute";
			fr.style.width = fr.style.height = 0;
			fr.frameBorder = 0;
		}

		if (src) { fr.src = src; }

		document.body.appendChild(fr);
		HAPI.XHR.iframePoolByName[name] = fr;

		return fr;
	},

	releaseIframe: function(fr) {
		fr.contentWindow.location.replace("about:blank");
		document.body.removeChild(fr);
		//HAPI.XHR.iframePool.push(fr);
	},

	formPool: [],
	getForm: function() {
		if (HAPI.XHR.formPool.length > 0) {
			return HAPI.XHR.formPool.pop();
		}

		var form = document.createElement("form");
		form.style.position = "absolute";
		form.style.width = form.style.height = 0;

		document.body.appendChild(form);

		return form;
	},
	releaseForm: function(form) {
		form.innerHTML = "";
		HAPI.XHR.formPool.push(form);
	},

	xssPut: function(method, callback, data, xssWebPrefix) {
		// A method for cross-site data send/receive that creates a form with data in it, and submits it to the remote host;
		// the remote host returns data via a URL snippet.
		// This allows the sending of essentially unlimited amounts of data to the server.
		// Note that URL length is severely restricted on IE, so use xssGet to grab large amounts of data.

		var fr = HAPI.XHR.getIframe();
		var form = HAPI.XHR.getForm();

		form.method = "post";
		form.target = fr.name;
		form.action = (xssWebPrefix || HAPI.XHR._xssWebPrefix) +
							"db=" + encodeURIComponent(HAPI.database || window.HeuristInstance) +
							"&method=" + encodeURIComponent(method);

		var elt = document.createElement("input");
			elt.type = "hidden";
			elt.name = "data";
			elt.value = HAPI.XHR.convertToJSON(data);
		form.appendChild(elt);

		/*
		elt = document.createElement("input");
			elt.type = "hidden";
			elt.name = "key";
			elt.value = ;
		form.appendChild(elt);
		*/
		fr.onload = function() {
			var data;
			var hash, token;
			try {
				hash = fr.contentWindow.document.location.hash;
				if ( (token=hash.match(/^#token=(data[a-f0-9]+)/)) ) {
					// too much data to transmit as a URL fragment -- use xssGet to retrieve it
					HAPI.XHR.releaseIframe(fr);
					return HAPI.XHR.xssGet("fetchResultsFromSession", callback, { token: token[1] }, xssWebPrefix);
				} else if (hash.match(/^#data=/)) {
					data = HAPI.XHR.evalJSON(HAPI.base64.decode(decodeURIComponent(hash.substring(6))));	// #data=....
				}
				else {
				}
			} catch (e) { return; }

			setTimeout(function() {
				HAPI.XHR.releaseIframe(fr);
				callback(data);
			}, 0);
			fr.onload = null;
		};
		form.submit();
	},

	xssGet: function(method, callback, data, xssWebPrefix) {
		// A method for cross-site data send/receive that creates a new SCRIPT tag with some small amount of data encoded in the URL;
		// the remote host returns data in the JavaScript, and issues a callback.
		// This allows the fetching of essentially unlimited amounts of data from the server.
		// Note that URL length is severely restricted on IE, so use xssPut to send large amounts of data.

		var name;
		do {
			// Pick a random (unique) callback name
			name = "cb" + Math.round(Math.random() * 1000000);
		} while (window[name]);

		var scr = document.createElement("script");
		scr.type = "text/javascript";
		scr.src = (xssWebPrefix || HAPI.XHR._xssWebPrefix) + "method=" + encodeURIComponent(method) +
					/*"&key=" + encodeURIComponent(HAPI.key || "") + */
					"&db=" + encodeURIComponent(HAPI.database || window.HeuristInstance) +
					"&data=" + encodeURIComponent(HAPI.base64.encode(HAPI.XHR.convertToJSON(data))) + "&cb=" + name;

		var headElt = document.getElementsByTagName("head")[0];

		// Create a callback with the name specified to the XSS handler
		window[name] = function(jsonData) {

			data = HAPI.XHR.evalJSON(jsonData);

			// remove the SCRIPT from the document, remove the callback from the global namespace
			headElt.removeChild(scr);
			try {
				delete window[name];
			}
			catch (e) {
				window[name] = null;
			}

			setTimeout(function() { callback(data); }, 0);
		};
		headElt.appendChild(scr);
	}
};


var HStorageManager = function() {
	var that = this;
	var _cache = {};
	var _stubCache = {};

	var _relationshipCache = {};	// lists of loaded relationships that point to each record (indexed by recID)

	this.getRecord = function(id) { return (_cache[id]  ||  null); };
	this.getRecordByNonce = function(nonce) { return (HAPI.recordCache[nonce]  ||  null); };

	this.addRecordToCache = function(id, record) { _cache[id] = record; };
	this.removeRecordFromCache = function(id) {
		delete _cache[id];
		that.removeStubFromCache(id);
	};

	this.getStubForRecord = function(record) {
		var stub;
		if (record.getID()) { return that.getRecordStub(record.getID(), record.getTitle(), record.getHHash()); }
		else if (_stubCache[record.getNonce()]) { return _stubCache[record.getNonce()]; }
		else {
			stub = new HRecordStub(null, record.getTitle(), record.getHHash());
			_stubCache[record.getNonce()] = stub;
			return stub;
		}
	};

	this.getRecordStub = function(id, title, hhash) {
		if (! _stubCache[id]) { _stubCache[id] = new HRecordStub(id, title, hhash, that); }
		return _stubCache[id];
	};

	this.removeStubFromCache = function(id) {
		delete _stubCache[id];
	};

	this.getRelatedRecordsFor = function(record) {
		var id = record.getID();
		var relationships = _relationshipCache[id];
		if (! relationships) { return []; }

		var records = [];
		var otherRecord;
		for (var i in relationships) {
			try {
				if (relationships[i].getPrimaryRecord().getRecord() === record) {
					otherRecord = relationships[i].getSecondaryRecord().getRecord();
				}
				else if (relationships[i].getSecondaryRecord().getRecord() === record) {
					otherRecord = relationships[i].getPrimaryRecord().getRecord();
				}
				if (otherRecord) { records.push(otherRecord); }
			} catch (e) {
				// presumably a malformed relationship ... a hanging reference?
			}
		}
		return records;
	};

	this.getRelationshipsFor = function(record) {
		var id = record.getID();
		var relationships = _relationshipCache[id];
		if (! relationships) { return []; }

		var records = [];
		for (var i in relationships) {
			try {
				if (relationships[i].getPrimaryRecord().getID() === id) {
					records.push(relationships[i]);
				}
				else if (relationships[i].getSecondaryRecord().getID() === id) {
					records.push(relationships[i]);
				}
			} catch (e) {
				// presumably a malformed relationship ... a hanging reference?
			}
		}
		return records;
	};

	/* internal */ this.addRelationshipToCache = function(relation) {
		var primaryRecord = relation.getPrimaryRecord();
		var secondaryRecord = relation.getSecondaryRecord();

		if (primaryRecord  &&  primaryRecord.getID()) {
			if (! _relationshipCache[primaryRecord.getID()]) { _relationshipCache[primaryRecord.getID()] = {}; }
			_relationshipCache[primaryRecord.getID()][relation.getID()] = relation;
		}
		if (secondaryRecord  &&  secondaryRecord.getID()) {
			if (! _relationshipCache[secondaryRecord.getID()]) { _relationshipCache[secondaryRecord.getID()] = {}; }
			_relationshipCache[secondaryRecord.getID()][relation.getID()] = relation;
		}
	};

	/* internal */ this.removeRelationshipFromCache = function(relation) {
		var primaryRecord = relation.getPrimaryRecord();
		var secondaryRecord = relation.getSecondaryRecord();

		if (primaryRecord  &&  primaryRecord.getID()) {
			if (_relationshipCache[primaryRecord.getID()]) {
				delete _relationshipCache[primaryRecord.getID()][relation.getID()];
			}
		}
		if (secondaryRecord  &&  secondaryRecord.getID()) {
			if (_relationshipCache[secondaryRecord.getID()]) {
				delete _relationshipCache[secondaryRecord.getID()][relation.getID()];
			}
		}
	}

	this.saveRecord = function() { throw "saveRecord not implemented by this StorageManager"; };
	this.saveRecords = function() { throw "saveRecords not implemented by this StorageManager"; };
	this.loadRecords = function() { throw "loadRecords not implemented by this StorageManager"; };

	this.findSimilarRecords = function() { throw "findSimilarRecords not implemented by this StorageManager"; };

	this.deleteRecord = function() { throw "deleteRecord not implemented by this StorageManager"; };

	this.saveFile = function() { throw "saveFile not implemented by this StorageManager"; };
	this.findFile = function() { throw "findFile not implemented by this StorageManager"; };
};
HStorageManager.getClass = function() { return "HStorageManager"; };
HAPI.inherit(HStorageManager, HObject);
HAPI.StorageManager = HStorageManager;


var HeuristScholarDB = new HStorageManager();
(function(dbWebPrefix) {	// HeuristScholarDB -specific initialisation
	var that = this;
	var _dbWebPrefix = (HAPI.XHR.defaultURLPrefix = dbWebPrefix);


	/* private */ function saveRecordCallback(record, saver, response) {
		var callback;
		var errorString;

		if (! response) {
			errorString = "internal Heurist error";
			if (saver  &&  saver.onerror) { callback = function() { record.internalUnlock(); saver.onerror(record, errorString); }; }
		}
		else if (response.error) {
			errorString = response.error;

			if (saver  &&  saver.onerror) { callback = function() { record.internalUnlock(); saver.onerror(record, errorString); }; }
		}
		else {
			record.saveChanges(that, response.bibID, response.version, response.bkmkID, response.title, response.detail, response.notify, response.comment, response.modified);
			that.addRecordToCache(response.bibID, record);

			// saved a relationship ... make sure it's in the _relationshipCache
			if (HAPI.isA(record, "HRelationship")) { that.addRelationshipToCache(record); }

			if (saver  &&  saver.onsave) { callback = function() { record.internalUnlock(); saver.onsave(record); }; }
		}
		if (callback) { setTimeout(callback, 0); }
		else { record.internalUnlock(); }
	}
	this.saveRecord = function(record, saver) {
		/* PRE */ if (! HAPI.isA(record, "HRecord")) { throw new HTypeException("HRecord object expected for argument #1"); }
		/* PRE */ if (saver && ! HAPI.isA(saver, "HSaver")) { throw new HTypeException("HSaver object expected for argument #2"); }

		if (! record.isValid()) {

			if (saver.onerror) {
				saver.onerror(record, "Record is missing required field(s)");
			} else {
				alert("Record is missing required field(s)");
			}
			return;
		}

		record.internalLock();
		var recordData = record.toJSO();
		HAPI.XHR.sendRequest("saveRecord", function(response) { saveRecordCallback(record, saver, response); }, recordData);
	};

	/* private */ function saveRecordsCallback(recordSet, saver, response) {
		var i;
		var callback;
		var errorString;
		var recordInfo, record;

		if (! response) {
			errorString = "internal Heurist error";
			if (saver  &&  saver.onerror) { callback = function() { saver.onerror(recordSet, errorString); }; }
		} else if (response.error || recordSet.length == 1 && recordSet[0]['error']) {
			errorString = response.error ? response.error : recordSet[0]['error'].join(',');
			if (saver  &&  saver.onerror) { callback = function() { saver.onerror(recordSet, errorString); }; }
		} else {
			for (i=0; i < recordSet.length; ++i) {
				recordInfo = response.record[i];
				if (recordInfo.error) { // save sent back an error and did not save this record
					recordSet[i].setError(recordInfo.error);
				}else{
					recordSet[i].saveChanges(that, parseInt(recordInfo.bibID),
												recordInfo.version,
												parseInt(recordInfo.bkmkID),
												recordInfo.title,
												recordInfo.detail,
												recordInfo.notify,
												recordInfo.comment,
												recordInfo.modified,
												recordInfo.warning);
					that.addRecordToCache(recordInfo.bibID, recordSet[i]);
				}
			}
			for (i=0; i < recordSet.length; ++i) {
				if (recordSet[i].hasError()) {
					continue;
				}
				record = recordSet[i];

				// saved a relationship ... make sure it's in the _relationshipCache
				if (HAPI.isA(record, "HRelationship")) { that.addRelationshipToCache(record); }
			}
			if (saver  &&  saver.onsave) {
				callback = function() {
					for (var j=0; j < recordSet.length; ++j) { recordSet[j].internalUnlock(); }
					saver.onsave(recordSet);
				};
			}
		}
		if (callback) { setTimeout(callback, 0); }
	}
	this.saveRecords = function(recordSet, saver) {
		var i, j;
		/* PRE */ for (i=0; i < recordSet.length; ++i) {
		/* PRE */	if (! HAPI.isA(recordSet[i], "HRecord")) { throw new HTypeException("HRecord objects expected for argument #1"); }
		/* PRE */ }
		/* PRE */ if (saver && ! HAPI.isA(saver, "HSaver")) { throw new HTypeException("HSaver object expected for argument #2"); }

		var recordData = {};
		for (i=0; i < recordSet.length; ++i) {
			recordSet[i].internalLock();
			recordData[recordSet[i].getNonce()] = recordSet[i].toJSO();
		}
		var anonymousRelatives;
		for (i=0; i < recordSet.length; ++i) {
			anonymousRelatives = recordSet[i].getAnonymousRelativeNonces();
			for (j=0; j < anonymousRelatives.length; ++j) {
				if (! recordData[anonymousRelatives[j]]) { throw new HUnsavedRecordException("Cannot save reference to unsaved record"); }
			}
		}

		HAPI.XHR.sendRequest("saveRecords", function(response) { saveRecordsCallback(recordSet, saver, response); }, { records: recordData });
	};

	/* private */ function deleteRecordCallback(record, deletor, response) {
		var callback;
		var errorString;

		if (! response) {
			errorString = "internal Heurist error";
			if (deletor  &&  deletor.onerror) { callback = function() { record.internalUnlock(); deletor.onerror(record, errorString); }; }
		}
		else if (response.error) {
			errorString = response.error;
			if (deletor  &&  deletor.onerror) { callback = function() { record.internalUnlock(); deletor.onerror(record, errorString); }; }
		}
		else {
			record.internalUnlock();
			that.removeRecordFromCache(record.getID());
			if (HAPI.isA(record, "HRelationship")) {
				that.removeRelationshipFromCache(record);
			}

			if (deletor  &&  deletor.ondelete) { callback = function() { deletor.ondelete(); }; }
		}
		if (callback) { setTimeout(callback, 0); }
		else { record.internalUnlock(); }
	}
	this.deleteRecord = function(record, deletor) {
		/* PRE */ if (! HAPI.isA(record, "HRecord")) { throw new HTypeException("HRecord object expected for argument #1"); }
		/* PRE */ if (deletor && ! HAPI.isA(deletor, "HDeletor")) { throw new HTypeException("HDeletor object expected for argument #2"); }

		/* could check for refs here and avoid contacting the server */

		record.internalLock();
		HAPI.XHR.sendRequest("deleteRecord", function(response) { deleteRecordCallback(record, deletor, response); }, { "id": record.getID() });
	};


	/* private */ function makeDetail(hDetailType, details) {
		/* Create a new detail of the appropriate type for the given detail string -- used to deserialise records */
		var val, f,
			variety = hDetailType? hDetailType.getVariety() : "literal";

		switch (variety) {
			case HVariety.NUMERIC:
			case HVariety.LITERAL:
			case HVariety.BOOLEAN:
			case HVariety.BLOCKTEXT:
			case HVariety.DATE:
			case HVariety.URLINCLUDE:
			return details;

			case HVariety.ENUMERATION:
			case HVariety.RELATIONTYPE:
			return hDetailType.getIdForEnumerationValue (details);

			case HVariety.REFERENCE:
			return that.getRecordStub(details.id, details.title, details.hhash);

			case HVariety.FILE:
			f = details.file;
			// Oh, wow ... this really isn't the best way to protect the HFile constructor.  FIXME
			that.initFile = makeDetail;
			// HFile (sm, id, originalName, size, type, URL, thumbnailURL, description)
			val = new HFile(that, parseInt(f.id), f.origName, f.fileSize, f.ext, f.URL, f.thumbURL, f.description);
			delete that.initFile;
			return val;

			case HVariety.GEOGRAPHIC:
			return new HGeographicValue(details.geo.type, details.geo.wkt);

			default:
			return null;
		}
	}

	/* private */ function loadRecordsCallback(searchSpec, loader, response) {
		var results, errorString;
		var callback;
		var i, j, id, bdID;
		var recordDetails;
		var details, detailTypeID, hDetailType;
		var notifications, comments, commentsMap, newComment, processedComments, user;
		var args, recipient, text, date, modDate, freq;

		var record,recCount;

		if (! response) {
			errorString = "internal Heurist error";
			if (loader  &&  loader.onerror) { callback = function() { loader.onerror(searchSpec, errorString); }; }
		}
		else if (response.error) {
			errorString = response.error;
			if (loader  &&  loader.onerror) { callback = function() { loader.onerror(searchSpec, errorString); }; }
		}
		else {
			results = [];
			recCount = response.records ? response.records.length:0;
			for (i=0; i < recCount; ++i) {
				// first sweep: create place-holder records
				id = parseInt(response.records[i][0]);
				if (! (record = that.getRecord(id))) {
					if (parseInt(response.records[i][2]) !== top.HEURIST.magicNumbers['RT_RELATION']) {	// saw TODO need to add check for rectype relation
						record = new HRecord();
					}
					else {
						record = new HRelationship();
					}
					that.addRecordToCache(id, record);
				}
				results.push(record);
			}
			for (i=0; i < recCount; ++i) {
				// FIXME: doesn't work for private notes
				recordDetails = response.records[i];
				id = parseInt(recordDetails[0]);
				record = that.getRecord(id);
				if (record.isLocked()) { continue; }	// record is locked against update

				// construct the DETAILS -- they are provided in literal form, transform them into full objects
				details = recordDetails[4];
				for (detailTypeID in details) {
					hDetailType = HDetailManager.getDetailTypeById(detailTypeID);
					for (j in details[detailTypeID]) {
						if (details[detailTypeID][j] === null  ||  details[detailTypeID][j] === "") {
							delete details[detailTypeID][j];
						} else {
							details[detailTypeID][j] = makeDetail(hDetailType, details[detailTypeID][j]);
						}
					}
				}

				// notifications and comments need to be added after the rest of the record is set up
				notifications = recordDetails[22]; //cache notifications
				comments = recordDetails[23]; //cache comments
				recordDetails.pop(); //remove comments
				recordDetails.pop(); //remove notifications

				// Set all of the new record's details ... we add "that" (the storage manager) onto the start of the argument list
				recordDetails.unshift(that);
				record.setAll.apply(record, recordDetails); //passes all args except readonly which is passed as null

				// If this is a relationship, add appropriate relationship-cache entries
				if (HAPI.isA(record, "HRelationship")) { that.addRelationshipToCache(record); }

				for (j=0; j < notifications.length; ++j) {
					args = notifications[j];
					if (args[1]) { recipient = HWorkgroupManager.getWorkgroupById(args[1]); }
					else if (args[2]) { recipient = HUserManager.getUserById(args[3]); }
					else { recipient = args[3]; }

					text = args[4];
					date = new Date(args[5].replace(/-/g, "/"));
					freq = args[6];

					try {	// make a best effort -- no guarantees
						record.addNotification(recipient, text, date, freq).setID(parseInt(args[0]));
					} catch (e) { }	// saw TODO check what exceptions we are likely catching and handle
				}

				commentsMap = {};
				processedComments = [];
				for (j=0; j < comments.length; ++j) {
					// comments are an array: id, owner-comment, date, mod-date, text, user-id, deleted?
					if (comments[j][6] !== 0) {	// comment has been deleted: refer any replies to its parent
						commentsMap[comments[j][0]] = commentsMap[comments[j][1]];
						continue;
					}

					date = new Date(comments[j][2].replace(/-/g,"/"));
					modDate = comments[j][3]? new Date(comments[j][3].replace(/-/g,"/")) : null;
					user = HUserManager.getUserById(comments[j][5]);

					try {
						newComment = new HComment(commentsMap[comments[j][1]] || record, true);
						newComment.setAll(parseInt(comments[j][0]), date, modDate, comments[j][4], user || null);
						commentsMap[comments[j][0]] = newComment;
						if (newComment.getParent() === null) { processedComments.push(newComment); }
					} catch (e) { console.log(e); }
				}
				comments = processedComments;

				// XXX: why do the comments and notifications have to be handled after the record's details have been set, and then we have to re-set the details?
				if (notifications  ||  comments) {
					record.setNotificationsAndComments(that, notifications, comments);
				}
			}
			if (loader  &&  loader.onload) { callback = function() { loader.onload(searchSpec, results, response.resultCount); }; }
		}
		if (callback) { setTimeout(callback, 0); }

	}
	this._defaultLoadRecordLimit = 100;
	this.getDefaultLoadRecordLimit = function () {
		return this._defaultLoadRecordLimit;
	}
	this.setDefaultLoadRecordLimit = function (limit) {
		if (!limit || parseInt(limit) == "NaN") {
			throw new HTypeException("expected a number to be passed into setLoadRecordLimit");
		}
		var temp = parseInt(limit);
		if (temp>1000) {
			temp = 1000;
		}
		if (temp<1) {
			temp = 1;
		}
		this._defaultLoadRecordLimit = temp;
	}
	this.loadRecords = function(searchSpec, loader) {
		var searchData;
		var options;
		var limit;
		var offset;
		var matches;

		/* PRE */ if (! HAPI.isA(searchSpec, "HSearch")) { throw new HTypeException("HSearch object expected for argument #1"); }
		/* PRE */ if (! HAPI.isA(loader, "HLoader")) { throw new HTypeException("HLoader object expected for argument #2"); }

		searchData = {
			ver: 1,
			q: searchSpec.getQuery()
		};
		options = searchSpec.getOptions();

		matches = searchData.q.match(/\boffset:(\d+)\b/);
		if (matches && matches[1]) {
			offset = parseInt(matches[1]);
			searchData.q = searchData.q.replace(/\boffset:\s*\d+\b/,"");
		} else if (options["offset"]) {
			offset = parseInt(options["offset"]);
		}
		if (typeof offset == "number") {
			searchData.o = offset;
		}

		matches = searchData.q.match(/\blimit:(\d+)\b/);
		if (matches && matches[1]) {
			limit = parseInt(matches[1]);
			searchData.q = searchData.q.replace(/\blimit:\s*\d+\b/,"");
		}else if (options["limit"]) {
			limit = parseInt(options["limit"]);
		}else{
			limit = this._defaultLoadRecordLimit;
		}
		if (typeof limit == "number") {
			searchData.l = limit;
		}

		if (options["favourites-only"]  ||  options["personalized-only"]  ||  options["personalised-only"]) {
			searchData.w = "bookmark";
		}
		if (options["recent-only"]) { searchData.r = "recent"; }
		if (options["fresh"]) { searchData.f = true; }

		// Could do this with a GET request, but IE tends to cache it
		HAPI.XHR.sendRequest("loadSearch", function(response) { loadRecordsCallback(searchSpec, loader, response); }, searchData);
	};

	/* private */ function findSimilarRecordsCallback(record, loader, response) {
		var results, errorString;
		var m, stub;
		var callback;
		var i;

		if (! response) {
			errorString = "internal Heurist error";
			if (loader  &&  loader.onerror) { callback = function() { loader.onerror(record, errorString); }; }
		}
		else if (response.error) {
			errorString = response.error;
			if (loader  &&  loader.onerror) { callback = function() { loader.onerror(record, errorString); }; }
		}
		else {
			results = [];
			for (i = 0; response.matches  &&  i < response.matches.length; ++i) {
				m = response.matches[i];
				stub = that.getRecordStub(parseInt(m.id), m.title, m.hhash);
				results.push(stub);
			}

			if (loader  &&  loader.onload) { callback = function() { loader.onload(record, results); }; }
		}
		if (callback) { setTimeout(callback, 0); }
	};
	this.findSimilarRecords = function(record, loader, options) {
		var details, newDetails;
		var recordData;
		var t, i;

		/* PRE */ if (! HAPI.isA(record, "HRecord")) { throw new HTypeException("HRecord object expected for argument #1"); }
		/* PRE */ if (! HAPI.isA(loader, "HLoader")) { throw new HTypeException("HLoader object expected for argument #2"); }

		details = record.toJSO().detail;
		newDetails = {};
		for (t in details) {
			newDetails[t] = [];
			for (i in details[t]) {		// This is OK! details[t] is an object, not an array, even for unnamed details
				newDetails[t].push(details[t][i]);
			}
		}

		recordData = {
			details: newDetails,
			types: [ record.getRecordType().getID() ],
			id: record.getID()
		};

		HAPI.XHR.sendRequest("findSimilarRecords", function(response) { findSimilarRecordsCallback(record, loader, response); }, recordData);
	};

	/* private */ function findFilesCallback(searchSpec, loader, response) {
		var i;
		var results;
		var callback, errorString;
		var newFile;
		var f;

		if (! response) {
			errorString = "internal Heurist error";
			if (loader  &&  loader.onerror) { callback = function() { loader.onerror(searchSpec, errorString); }; }
		}
		else if (response.error) {
			errorString = response.error;
			if (loader  &&  loader.onerror) { callback = function() { loader.onerror(searchSpec, errorString); }; }
		}
		else {
			results = [];
			that.initFile = findFilesCallback;
			for (i=0; i < response.files.length; ++i) {
				// each file's details are returned as an array:
				// id, name, size, mime-type, download URL, thumbnail URL
				f = response.files[i];
				//newFile = new HFile(that, parseInt(d[0]), d[1], d[2], d[3], d[4], d[5], d[6], '');
				newFile = new HFile(that, parseInt(f.id), f.origName, f.fileSize, f.ext, f.URL, f.thumbURL, f.description);
				results.push(newFile);
			}
			delete that.initFile;

			if (loader  &&  loader.onload) { callback = function() { loader.onload(searchSpec, results); }; }
		}
		if (callback) { setTimeout(callback, 0); }
	}
	this.findFiles = function(searchSpec, loader) {
		/* PRE */ if (! searchSpec  ||  typeof(searchSpec) !== "object") { throw new HTypeException("HFileSearchOptions expected for argument #1"); }
		/* PRE */ if (! HAPI.isA(loader, "HLoader")) { throw new HTypeException("HLoader object expected for argument #2"); }

		// Could do this with a GET request, but IE tends to cache it
		HAPI.XHR.sendRequest("getFileMetadata", function(response) { findFilesCallback(searchSpec, loader, response); }, searchSpec);
	};
	this.saveFile = function(opts, saver, progressCallback) {
		// Previously, one saved a file by providing a FileInputElement as the first argument.
		// Now, one may provide multiple details in the first argument, which is an object used as an associative array.
		//  - file: a FileInputElement
		//  - description: a short text description of the file
		var fileInput = opts.file  ||  opts;
		var description = opts.description || "";
		var doc = fileInput.ownerDocument;

		/* PRE */ if (! fileInput  ||  typeof(fileInput) !== "object"  ||  fileInput.type !== "file") { throw new HTypeException("file input expected for argument #1"); }
		/* PRE */ if (saver  &&  ! HAPI.isA(saver, "HSaver")) { throw new HTypeException("HSaver object expected for argument #2"); }
		/* PRE */ if (progressCallback  &&  typeof(progressCallback) !== "function") { throw new HTypeException("callback function expected for argument #3"); }
		/* PRE */ if (fileInput.value === "") { throw new HValueException("file input is empty"); }

		/* WEB 2.0 FILE UPLOADING
		 * The idea:
		 * Any time the user selects a new file, we create a new form,
		 * move the file input to the new form, and submit that form to a new iframe.
		 * When the file is uploaded, some data is sent back by the form,
		 * and the new iframe is disposed of.
		 * This is necessary because we can't do XMLHTTPRequest submission of FILE inputs.
		 *
		 * Note that the file input will be removed from its parent node (if it has one);
		 * it will be supplied to the HSaver if you really need to reuse it though.
		 */

		if (! that.uploadsInProgress) { that.uploadsInProgress = { names: {}, counter: 0 }; }
		that.uploadsInProgress.counter += 1;

		var uploadIdentifier, uploadName;
		do {
			uploadIdentifier = Math.floor(Math.random() * 1000000000); //file upload handle/identifier
			uploadName = "upload-" + uploadIdentifier;
		} while (that.uploadsInProgress.names[uploadName]); //keep trying until we find an unused id

		/* Oh, hello ... what's this?  An undocumented feature?
		 * If a third parameter (a function) is passed to saveFile, then it is used as a PROGRESS callback.
		 * The function will be called occasionally with three parameters:
		 * the fileInput, the number of bytes uploaded so far, and the total number of bytes expected to be uploaded.
		 * See saveFileProgress / saveFileProgressCallback
		 */
		that.uploadsInProgress.names[uploadName] = { fileInput: fileInput, callback: progressCallback };

		var newIframe;
		var s;
		try {
			// IE style -- have to create the iframe with the name given
			newIframe = doc.createElement("<iframe name=\"" + uploadName + "\">");
		} catch (e) {
			newIframe = doc.createElement("iframe");
			newIframe.name = uploadName;
		}
		newIframe.frameBorder = 0;
			s = newIframe.style;
			s.position = "absolute";
			s.width = "0";
			s.height = "0";
			s.left = "-10000px";

		var newForm = doc.createElement("form");
			newForm.enctype = "multipart/form-data";
			newForm.encoding = "multipart/form-data";
			newForm.method = "post";
			newForm.target = newIframe.name; // connect the frame to the form
			s = newForm.style;
			s.position = "absolute";
			s.width = "0";
			s.height = "0";
			s.left = "-10000px";

			// ANY.SUB.DOMAIN.hapi.heuristscholar.org will act the same as hapi.heuristscholar.org,
			// but browsers treat it as a different server, so you can have more simultaneous connections.
			// (in this case, n*101 instead of n, where n is typically equal to 2 on firefox or IE).

			// obviously this won't work if hapi is being served from a numbered IP address
		var baseURL = _dbWebPrefix;
			if (! baseURL.match(/\d+\.\d+\.\d+\.\d+/)) {
			//				baseURL = baseURL.replace(/^http:\/\//, "http://" + Math.round(Math.random()*100) + ".");
			}
			// Insert XSS incantations if HAPI.key is set.
			newForm.action = baseURL + "hapi/php/dispatcher.php?method=saveFile"+  //saw FIXME: add instance code.
									"&db=" + encodeURIComponent(HAPI.database || window.HeuristInstance) //+
									/* &key=" + encodeURIComponent(HAPI.key)) : "saveFile")*/;

		doc.body.appendChild(newForm);

		var uploadIdentifierInput = doc.createElement("input");
			uploadIdentifierInput.type = "hidden";
			uploadIdentifierInput.name = "UPLOAD_IDENTIFIER";	// magic name, works with progress indicator - passes to php in request.
			uploadIdentifierInput.value = uploadIdentifier;
			newForm.appendChild(uploadIdentifierInput);

		var descriptionInput = doc.createElement("input");
			descriptionInput.type = "hidden";
			descriptionInput.name = "description";
			descriptionInput.value = description;
			newForm.appendChild(descriptionInput);

		var matches;
		var heurist_sessionidInput;
		if (! HAPI.key  &&  (matches = document.cookie.match(/heurist-sessionid=([a-f0-9]{40})/))) {
			heurist_sessionidInput = doc.createElement("input");
			heurist_sessionidInput.type = "hidden";
			heurist_sessionidInput.name = "heurist-sessionid";
			heurist_sessionidInput.value = matches[1];
			newForm.appendChild(heurist_sessionidInput);
		}

		newForm.appendChild(fileInput);
		fileInput.name = "file";	// saw NOTE!! this must be the same as the $_FILES['file'] becoasue on form submit this is passed

		// XXX -- should this be done with attachEvent etc?  Might avoid double-load events
		newIframe.onload = function() {
			var data;
			try {
				data = HAPI.XHR.evalJSON(HAPI.base64.decode(decodeURIComponent(newIframe.contentWindow.document.location.hash.substring(6))));	// #data=....
			} catch (e) {
				//				 setTimeout(function() { that.onload();},100);
				 return; // return seems to try again
			}

			var response = (data  ||  { error: "internal Heurist error while uploading file" });
			var newFile, d;
			var callback = null;

			if (response.error) {
				// There was an error!  Use the callback
				if (saver.onerror) { callback = function() { saver.onerror(fileInput, response.error); }; }
			}
			else {
				d = response.file;

				// the file's details are returned as an array:
				// id, name, sizeKB, mime-type, download URL, thumbnail URL, description
				//sm, id, originalName, size, type, URL, thumbnailURL, description
				that.initFile = newIframe.onload;
				//ARTEM newFile = new HFile(that, parseInt(d[0]), d[1], d[2], d[3], d[4], d[5], d[6]);
				newFile = new HFile(that, parseInt(d.id), d.origName, d.fileSize, d.ext, d.URL, d.thumbURL, d.description);
				delete that.initFile;

				if (saver.onsave) { callback = function() { saver.onsave(fileInput, newFile); }; }
			}

			newIframe.onload = null;

			// housekeeping
			newForm.parentNode.removeChild(newForm);
			that.uploadsInProgress.counter -= 1;
			delete that.uploadsInProgress.names[uploadIdentifier];

			// Remove the iframe from the DOM ...
			// This is done in a setTimeout callback: otherwise we remove the iframe while still
			// in its onload handler (this leads to endless loading animation on firefox,
			// certainly leaves the opportunity for even WORSE things)
			setTimeout(function() { newIframe.parentNode.removeChild(newIframe); }, 0);

			if (callback) { setTimeout(callback, 0); }
		};

		doc.body.appendChild(newIframe);

		/* Submit the form -- detach it from the main thread so that the new frame can be inserted into the frames hierarchy
		 * (a slight delay is also necessary)
		 */
		var counter = { value: 0 };
		var intervalID = setInterval(function() {
			if (doc.getElementsByName(newIframe.name).length > 0) {		// check that the frame has been inserted into the hierarchy ...
				clearInterval(intervalID);
				newForm.submit();		// ... because we submit the form to it (the iframe's name is the form's target)
			}
			else if (++counter.value > 50) {	// It has taken too long!  Bail.
				clearInterval(intervalID);
				if (saver.onerror) {
					saver.onerror(fileInput, "internal browser error while uploading file");
				}
				else {
					alert("Internal browser error while uploading file");
				}
			}
		}, 100);

		if (progressCallback) {
			if (! HAPI.progressIntervalID) {
				HAPI.progressIntervalID = setInterval(saveFileProgress, 1000);
			}
		}
	};

	/* private */ function saveFileProgress() {
		var ids = [];
		for (var uploadName in that.uploadsInProgress.names) { ids.push(uploadName.replace(/^upload-/, "")); }

		var url;
		if (ids.length > 0  &&  that.uploadsInProgress.counter > 0) {
			HAPI.XHR.sendRequest("reportUploadProgress", saveFileProgressCallback, { uploadIDs: ids });
		}
		else {
			clearInterval(HAPI.progressIntervalID);
			delete HAPI.progressIntervalID;
		}
	}
	/* private */ function saveFileProgressCallback(infos) {
		var progress, fileInfo, callback;
		if (! infos) { return; }

		for (var uploadID in infos) {
			//console.log(uploadID + " => " + infos[uploadID]);
			progress = infos[uploadID];
			fileInfo = that.uploadsInProgress.names["upload-" + uploadID];
			callback = (fileInfo  &&  fileInfo.callback);
			if (! callback) { continue; }

			if (progress.bytesUploaded  &&  progress.bytesTotal) {
				callback(fileInfo.fileInput, parseInt(progress.bytesUploaded), parseInt(progress.bytesTotal));
			}
		}
	}
}).call(HeuristScholarDB, HeuristBaseURL);



HAPI.XHR.vanillaSendRequest = HAPI.XHR.sendRequest;
HAPI.XHR.sendRequest = function(url, callback, jso) {
	if (! HAPI.key  ||  ! url.match(/^[-a-z]+$/)) {
		return HAPI.XHR.vanillaSendRequest(url, callback, jso);
	}
	HAPI.XHR.xssComm(url, callback, jso);
};

HAPI.addListener = function(obj, eventName, handler) {
	if (obj.addEventListener) {
		obj.addEventListener(eventName, handler, false);
	}
	else if (obj.attachEvent) {
		obj.attachEvent("on" + eventName, handler);
	}
	else {
		obj["on" + eventName] = handler;
	}

	return { obj: obj, eventName: eventName, handler: handler };
};
HAPI.removeListener = function(details) {
	if (details.obj.removeEventListener) {
		details.obj.removeEventListener(details.eventName, details.handler, false);
	}
	else if (details.obj.detachEvent) {
		details.obj.detachEvent("on" + details.eventName, details.handler);
	}
	else {
		obj["on" + details.eventName] = null;
	}
};


HAPI.PJ = {
	// persistent JavaScript objects -- the PJ party

	store: function(name, value, opts) {
		if (! opts) { opts = {}; }

		var data = { name: name, value: value };
		if (opts.crossDomain) { data.crossSession = data.crossDomain = true; }
		if (opts.crossSession) { data.crossSession = true; }

		var callback = opts.callback  ||  function() { };

		HAPI.XHR.sendRequest("storePersistentJS", function(response) { callback(name, value, response); }, data);
	},

	retrieve: function(name, callback, opts) {
		if (! opts) { opts = {}; }

		var data = { name: name };
		if (opts.crossDomain) { data.crossSession = data.crossDomain = true; }
		if (opts.crossSession) { data.crossSession = true; }

		var callback = callback  ||  function() { };

		HAPI.XHR.sendRequest("retrievePersistentJS", function(response) { callback(name, response.value, response); }, data);
	}
};

HAPI.importSymbols = function(from, to) {
	// Copies all the symbols defined hererin from one namespace to another.
	// Do something like importSymbols(top, this) from within a frame to "import" HAPI into that frame.
	var symbols = [
		"HAPI",
		"HObject",
		"HException",
		"HPermissionException",
		"HInvalidWorkgroupException",
		"HInvalidRecordTypeException",
		"HRecordStubException",
		"HDetailVarietyMismatchException",
		"HBadRelationshipException",
		"HNotLoggedInException",
		"HNotPersonalisedException",
		"HUnknownTagException",
		"HValueException",
		"HTypeException",
		"HUnsavedRecordException",
		"HWorkgroupTag",
		"HWorkgroup",
	//		"HColleagueGroup",
		"HRecordType",
		"HRatings",
		"HUser",
		"HUserManager",
		"HGeographicValue",
		"HGeographicType",
		"HRecord",
		"HRelationship",
		"HNotes",
		"HAnonymousAuthor",
		"HRecordStub",
		"HNotification",
		"HFrequency",
		"HDetailType",
		"HVariety",
		"HRequiremence",
		"HComment",
		"HSaver",
		"HDeletor",
		"HLoader",
		"HSearch",
		"HFile",
		"HTagManager",
		"HWorkgroupManager",
		"HWorkgroupTagManager",
	//		"HColleagueGroupManager",
		"HRecordTypeManager",
		"HDetailManager",
		"HCurrentUser",
		"HStorageManager",
		"HeuristScholarDB"
	];

	for (var i = 0; i < symbols.length; ++i) {
		to[symbols[i]] = from[symbols[i]];
	}

};

if (window["HeuristApiKey"]) { HAPI.setKey(HeuristApiKey, "" + window["HeuristInstance"], "" + window["HeuristBaseURL"]); }
if (HeuristIconURL) HAPI.HeuristIconURL = HeuristIconURL;
if (HeuristBaseURL && !HAPI.HeuristBaseURL) HAPI.HeuristBaseURL = HeuristBaseURL;
if (top.HEURIST && top.HEURIST.fireEvent) top.HEURIST.fireEvent(top, "heurist-HAPI-loaded");

