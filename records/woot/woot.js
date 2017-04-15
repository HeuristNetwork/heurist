/*
* Copyright (C) 2005-2016 University of Sydney
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
* JS woot interface   
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Records/Woot 
*/


if (! window["HAPI"]) {
	alert(
		"It seems the Heurist API libraries have failed to load.\n\n" +
		"This can be caused by network problems, so please try relaoding the page.\n\n" +
		"If the problem persists, please email info@HeuristNetwork.org and tell us the URL you are accessing."
	);
}

var HWootException = function(text) { HException.call(this, text, "HWootException"); };
HAPI.inherit(HWootException, HException);
HAPI.WootException = HWootException;


HAPI.WOOT = function() {
	var _xssWebPrefix = (window.HeuristBaseURL ? window.HeuristBaseURL :
							(window.HEURIST && window.HEURIST.baseURL ? HEURIST.baseURL :
								(window.HAPI && window.HAPI.XHR.defaultURLPrefix))) + "records/woot/php/wootDispatcher.php?";

	function Woot(id, title, version, creatorId, permissions, chunks) {
		this.id = id;
		this.title = title;
		this.version = version;
		this.creatorId = creatorId || HCurrentUser.getID();
		this.permissions = (permissions  &&  permissions.length > 0)? permissions.slice(0) : HAPI.WOOT.DEFAULT_WOOT_PERMISSIONS;
		this.chunks = chunks? chunks.slice(0) : [];
	}
	Woot.prototype.getId = function() { return this.id; };
	Woot.prototype.getTitle = function() { return this.title; };
	Woot.prototype.getVersion = function() { return this.version; };
	Woot.prototype.getCreatorId = function() { return this.creatorId; };
	Woot.prototype.isReadOnly = function() { return isReadOnly(this.permissions, this.creatorId); };
	Woot.prototype.setPermissions = function(permissions) {
		this.permissions = permissions.slice(0);
	};
	Woot.prototype.appendChunk = function(chunk) {
		this.chunks.push(chunk);
	};
	Woot.prototype.insertBefore = function(newChunk, existingChunk) {
		for (var i=0; i < this.chunks.length; ++i) {
			if (this.chunks[i] === existingChunk) {
				this.chunks.splice(i, 0, newChunk);
				return;
			}
		}
		this.chunks.push(newChunk);
	};
	Woot.prototype.toJso = function() {
		var permJso = [];
		var chunkJso = [];
		for (var i=0; i < this.permissions.length; ++i) {
			permJso.push(this.permissions[i].toJso());
		}
		for (var i=0; i < this.chunks.length; ++i) {
			chunkJso.push(this.chunks[i].toJso());
		}
		return (! this.id  ||  this.id === "new")? {
			title: this.title,
			permissions: permJso,
			chunks: chunkJso
		} : {
			id: this.id,
			permissions: permJso,
			chunks: chunkJso
		};
	};

	var chunksByNonce = {};
	function Chunk(number, text, ownerId, editorId, permissions) {
		this.number = number || null;
		this.text = text || "";
		this.ownerId = ownerId || HCurrentUser.getID();
		var user = HUserManager.getUserById(this.ownerId);
		this.ownerRealName = user ? user.getRealName() : "Unknown user";
		this.editorId = editorId || HCurrentUser.getID();
		this.permissions = permissions? permissions.slice(0) : HAPI.WOOT.DEFAULT_CHUNK_PERMISSIONS;
		this.modified = false;

		do {
			this.nonce = Math.floor(Math.random() * 1000000);
		} while (chunksByNonce[this.nonce]);
		chunksByNonce[this.nonce] = this;
	}
	Chunk.prototype.isReadOnly = function() { return isReadOnly(this.permissions, this.ownerId); };
	Chunk.prototype.setText = function(text) {
		if (this.text !== text) {
			this.modified = true;
			this.text = text;
		}
	};
	Chunk.prototype.getText = function() { return this.text; };
	Chunk.prototype.isModified = function() { return this.modified; };
	Chunk.prototype.getOwnerId = function() {  return this.ownerId; };
	Chunk.prototype.getOwnerRealName = function() {  return this.ownerRealName; };
	Chunk.prototype.getEditorId = function() { return this.editorId; };

	Chunk.prototype.setPermissions = function(permissions) {
		this.permissions = permissions.slice(0);
		this.modified = true;
	};

	Chunk.prototype.toJso = function() {
		var permJso = [];
		for (var i=0; i < this.permissions.length; ++i) {
			permJso.push(this.permissions[i].toJso());
		}
		return {
			number: this.number,
			nonce: this.nonce,
			unmodified: ! this.modified,
			text: this.text,
			permissions: permJso
		};
	};

	function Permission(opts) {
		if (! (opts && (opts.groupId || opts.userId))) {
			throw new HWootException("invalid permission: userId or groupId required");
		}

		/* Restrict the special userId=-1 and groupId=-1 cases to singletons */
		if (! opts.force) {
			if (opts.groupId == -1) {
				return (opts.type === "RO")? WORLD_READONLY_PROTECTION : WORLD_PROTECTION;
			}
			if (opts.userId == -1) {
				return OWNER_PROTECTION;
			}
			if (HCurrentUser.isLoggedIn()  &&  opts.userId == HCurrentUser.getID()) {
				return OWNER_PROTECTION;
			}
		}

		this.groupId = opts.groupId || null;
		this.userId = opts.userId || null;
		this.groupName = opts.groupName || null;
		this.userName = opts.userName || null;
		this.type = ((opts.type + "").toUpperCase() === "RO")? "RO" : "RW";
	}
	Permission.prototype.toJso = function() {
		return {
			type: this.type,
			userId: this.userId,
			groupId: this.groupId
		};
	};


	function loadWoot(idOrTitle, loader) {
		var loadSpec = (typeof idOrTitle === "number")? { id: idOrTitle } : { title: idOrTitle };

		HAPI.XHR.xssComm("loadWoot", function(jso) {
			var callback;
			var i, j;
			var woot;
			var permissions, jsoPerms;
			var chunks, jsoChunks;
			var chunkPermissions, jsoChunkPerms;

			if (jso.success) {
				permissions = [];
				jsoPerms = jso.woot.permissions;
				for (i=0; i < jsoPerms.length; ++i) {
					permissions.push( new Permission(jsoPerms[i]) );
				}

				chunks = [];
				jsoChunks = jso.woot.chunks;
				for (i=0; i < jsoChunks.length; ++i) {
					chunkPermissions = null;
					if ( (jsoChunkPerms = jsoChunks[i].permissions) ) {
						chunkPermissions = [];
						for (j=0; j < jsoChunkPerms.length; ++j) {
							chunkPermissions.push( new Permission(jsoChunkPerms[j]) );
						}
					}
					chunks.push( new Chunk(jsoChunks[i].number, jsoChunks[i].text, jsoChunks[i].ownerId, jsoChunks[i].editorId, chunkPermissions) );
				}

				woot = new Woot(jso.woot.id, jso.woot.title, jso.woot.version, jso.woot.creator, permissions, chunks);
				if (loader && loader.onload) {
					callback = function() { loader.onload(idOrTitle, woot); };
				}
			}
			else {
				if (loader && loader.onerror) {
					callback = function() { loader.onerror(idOrTitle, jso.errorType); };
				}
			}

			if (callback) { setTimeout(callback, 0); }
		}, loadSpec, _xssWebPrefix);
	}


	function saveWoot(woot, saver) {
		HAPI.XHR.xssComm("saveWoot", function(jso) {
			var callback;
			var nonce;
			var chunk;

			if (jso.success) {
				if (woot.id === "new") woot.id = jso.id;
				woot.version = jso.version;
				for (nonce in jso.chunks) {
					chunk = chunksByNonce[nonce];
					if (! chunk) { continue; } // this really especially shouldn't happen
					chunk.number = jso.chunks[nonce];
					chunk.modified = false;
				}
				if (saver && saver.onsave) {
					callback = function() { saver.onsave(woot); };
				}
			}
			else {
				if (saver && saver.onerror) {
					callback = function() { saver.onerror(jso.errorType, chunksByNonce[jso.chunkNonce]); };
				}
			}

			if (callback) { setTimeout(callback, 0); }
		}, woot.toJso(), _xssWebPrefix);
	}


	function searchWoots(query, loader) {
		HAPI.XHR.xssComm("searchWoot", function(jso) {
			var callback;

			if (! jso.error) {
				if (loader && loader.onload) {
					callback = function() { loader.onload(jso.woots || []); };
				}
			}
			else {
				if (loader && loader.onerror) {
					callback = function() { loader.onerror(jso.error); };
				}
			}

			if (callback) { setTimeout(callback, 0); }
		}, { q: query }, _xssWebPrefix);
	}

	function isReadOnly(permissions, ownerId) {
		// Does this set of permissions => read-only to the current user?
		// Look for a permission that gives this user write-access -- in the absence of that, we are read-only
		if (HCurrentUser.isAdministrator()) return false;
		var perm;
		for (var i=0; i < permissions.length; ++i) {
			perm = permissions[i];
			if (perm.type === "RO") { continue; }
			if (perm.userId == HCurrentUser.getID()) { return false; /* not read-only */ }
			if (perm.userId == -1  &&  HCurrentUser.getID() == ownerId) { return false; /* not read-only */ }
			if (perm.groupId == -1  ||
			    HCurrentUser.isInWorkgroup( HWorkgroupManager.getWorkgroupById(perm.groupId) )) {
				return false;	/* not read-only */
			}
		}
		// No write access, so we must be read-only
		return true;
	}

	// WORLD_PROTECTION				== world-RW ("public")
	// WORLD_READONLY_PROTECTION	== world-RO
	// OWNER_PROTECTION				== owner-RW
	// CURRENT_USER_PROTECTION		== current-user-RW

	// *** note ***  groupId: -1  means ALL groups, but userId: -1 means OWNER

	var WORLD_PROTECTION = new Permission({ groupId: -1, force: true });
	var WORLD_READONLY_PROTECTION = new Permission({ groupId: -1, type: "RO", force: true });
	var OWNER_PROTECTION = new Permission({ userId: -1, force: true });
	var CURRENT_USER_PROTECTION = HCurrentUser.isLoggedIn() ? new Permission({ userId: HCurrentUser.getID(), force: true }) : null;

	return {
		Woot: Woot,
		Chunk: Chunk,
		Permission: Permission,

		loadWoot: loadWoot,
		saveWoot: saveWoot,
		searchWoots: searchWoots,

		WORLD_PROTECTION: WORLD_PROTECTION,
		WORLD_READONLY_PROTECTION: WORLD_READONLY_PROTECTION,
		OWNER_PROTECTION: OWNER_PROTECTION,
		CURRENT_USER_PROTECTION: CURRENT_USER_PROTECTION,

		DEFAULT_WOOT_PERMISSIONS: [ WORLD_PROTECTION ],
		DEFAULT_CHUNK_PERMISSIONS: [ WORLD_READONLY_PROTECTION, OWNER_PROTECTION ]
	};
}();


HAPI.WOOT.GUI = function() {
    // WootEditor constructor   opts -   "woot" : wootObj   or "title" : "record : rec_ID"   and "id" : docElementId  or "element": docElement or "container" : docElementContainer
    // SAW adding code to pass in eventHandling
	function WootEditor(opts) {
		var that = this;

		if (! (opts.title || opts.woot)) { throw new HWootException("require Woot or woot title"); }
		if (! (opts.id || opts.element || opts.container)) { throw new HWootException("require element, element id or container for new element"); }

		this.labels = labelsForLanguage[opts.language || "en"];

		if (opts.id) {
			this.div = document.getElementById(opts.id);
			if (! this.div) { throw new HWootException("no element for id " + opts.id); }
		}
		else if (opts.element) {
			this.div = opts.element;                                               //FIXME  assumes element is a  DIV or BLOCK element
			if (! this.div.nodeType) { throw new HWootException("invalid value for element"); }
		}
		else {
			if (! opts.container.nodeType) { throw new HWootException("invalid value for container"); }
			this.div = opts.container.appendChild(document.createElement("div"));
		}

		// Do a shimmy: insert a FORM element directly above the DIV containing this WootEditor
        //this will hook the save and cancel from  tinyMCE
		this.form = document.createElement("form");
		this.div.parentNode.insertBefore(this.form, this.div);
		this.form.appendChild(this.div);
		this.form.expando = true;
		this.form.onsubmit = function() {
			setTimeout(function() { that.save(); }, 0);      // map the onsubmit for this form to WootEditor.save
			return false;
		};
		this.form.oncancel = function() {
			setTimeout(function() { if(that.unlockedChunk) that.unlockedChunk.lock(); }, 0);   // map the form.oncancel to WootEditor.unlockedChunk.lock
			return false;
		};

		this.chunks = [];
		this.unlockedChunk = null;      //current unlocked chunk

        // save pass in event handlers
        if (opts.events) {
            this.events = opts.events;
        }
		if (opts.woot) {
			this.initWoot(opts.woot);
		}
		else {        // if we have a record then load it's woot
			HAPI.WOOT.loadWoot(opts.title, { onload: function(_, woot) {
														that.initWoot(woot);
													} });
		}
	}

	WootEditor.prototype.initWoot = function(woot) {
		this.woot = woot;
		this.setTitle(woot.title);
        this.createModeDiv();

		if (this.chunks.length) { this.clearChunks(); }     // clear any exisitng chunks

		this.removeEmptyMessage();                          // clear any message in the woot

		var chunk;
		for (var i=0; i < woot.chunks.length; ++i) {        //instantiate the editable Chunks for the woot.chunks text and store a reference in the editor
			chunk = new EditableChunk({ wootEditor: this, chunk: woot.chunks[i] });
			this.chunks.push(chunk);
		}
		if (! woot.chunks.length) {
			this.showEmptyMessage();
		}
		// start in edit mode
		this.div.className = "woot-editor edit";
	};
	WootEditor.prototype.showEmptyMessage = function() {
		if (! this.emptyMessageDiv) {
			var that = this;
			this.emptyMessageDiv = document.createElement("div");
			this.emptyMessageDiv.className = "empty-message";
			this.emptyMessageDiv.innerHTML = this.labels.emptyMessage.label;
			this.emptyMessageDiv.title = this.labels.emptyMessage.title;
			this.emptyMessageDiv.onclick = function() {
				// remove this "empty" message
				that.removeEmptyMessage();
				// and add a new chunk
				that.appendNewChunk(null);
			};
		}
		this.div.appendChild(this.emptyMessageDiv);
	};
	WootEditor.prototype.removeEmptyMessage = function() {
		if (this.emptyMessageDiv  &&  this.emptyMessageDiv.parentNode)
			this.emptyMessageDiv.parentNode.removeChild(this.emptyMessageDiv);
	};
	WootEditor.prototype.appendNewChunk = function(existingChunk) {
		// add a new empty chunk after the given one
		if (this.woot.isReadOnly()) {
			alert("insufficient permissions to add new chunk");
			return;
		}

		// create an empty editable chunk
		var newChunk = new EditableChunk({ wootEditor: this, chunk: new HAPI.WOOT.Chunk(
			null, "", HCurrentUser.getID(), HCurrentUser.getID()) });

		if (existingChunk) {
			this.div.insertBefore(newChunk.div, existingChunk.div.nextSibling);

			for (var i=0; i < this.chunks.length; ++i) {
				if (this.chunks[i] === existingChunk) {
					this.chunks.splice(i+1, 0, newChunk);
					this.woot.insertBefore(newChunk.chunk, this.woot.chunks[i+1]);
					if (! this.unlockedChunk) { newChunk.unlock(); }

					return;
				}
			}
			this.chunks.push(newChunk);
			this.woot.chunks.push(newChunk.chunk);
		}
		else {
			this.div.appendChild(newChunk.div);
			this.chunks.push(newChunk);
			this.woot.appendChunk(newChunk.chunk);
		}
		if (! this.unlockedChunk) { newChunk.unlock(); }
	};

	WootEditor.prototype.setTitle = function(title) {
		if (! this.titleDiv) {
			this.titleDiv = this.div.appendChild(document.createElement("div"));
			this.titleDiv.className = "woot-title";
		}else{
			this.titleDiv.innerHTML = "";
		}

		this.titleDiv.appendChild(document.createTextNode(title));
	};

	WootEditor.prototype.createModeDiv = function() {
		var that = this;

		this.edit = true;
		this.div.className += " edit";
		/*		var editLink = document.createElement("a");
			editLink.className = "woot-mode-edit-link";
			editLink.href = "#";
			editLink.onclick = function() {
				that.edit = true;
				that.div.className += " edit";
				return false;
			};
			editLink.appendChild(document.createTextNode("click here to edit"));

		var viewLink = document.createElement("a");
			viewLink.className = "woot-mode-view-link";
			viewLink.href = "#";
			viewLink.onclick = function() {
				that.save();
				that.edit = false;
				that.div.className = that.div.className.replace(/ edit/g, "");
				return false;
			};
			viewLink.appendChild(document.createTextNode("stop editing"));

		var msg = document.createElement("span");
			msg.className = "woot-edit-mode-msg";
			msg.appendChild(document.createTextNode("click on text to start editing"));

		this.modeDiv = this.div.appendChild(document.createElement("div"));
		this.modeDiv.className = "woot-mode-div";

		this.modeDiv.appendChild(msg);
		this.modeDiv.appendChild(editLink);
		this.modeDiv.appendChild(viewLink);
		*/
	};

	WootEditor.prototype.clearChunks = function() {
		var chunkDiv;
		if (this.unlockedChunk)
			this.unlockedChunk.lock();
		for (var i=0; i < this.chunks.length; ++i) {
			chunkDiv = this.chunks[i].div;
			chunkDiv.parentNode.removeChild(chunkDiv);
		}
		this.chunks = [];
	};

	WootEditor.prototype.isModified = function() {
		for (var i=0; i < this.chunks.length; ++i) {
			if (this.chunks[i].isModified()) { return true; }
		}
		return false;
	};

    // set up for saving a chunk  - prep the chunk for saving (copy the text from tinyMCE to the chunk) and set the save handlers in WOOT.saveWoot
	WootEditor.prototype.save = function() {
		var woot = this.woot;
		var unlockedChunk = this.unlockedChunk;
		var originalText;

		if (unlockedChunk) {
			originalText = unlockedChunk.chunk.getText();
			unlockedChunk.chunk.setText( tinyMCE.activeEditor.getContent() );
		}

		HAPI.WOOT.saveWoot(woot, {
			onsave: function() {
				if (unlockedChunk) { unlockedChunk.lock(); }
			},
			onerror: function(errorType) {
				alert("An error occurred while saving: " + errorType);
				if (unlockedChunk) {
					unlockedChunk.chunk.setText(originalText);
				}
			}
		});
	};


	function EditableChunk(opts) {
		var that = this;


		this.wootEditor = opts.wootEditor;
		this.chunk = opts.chunk;

		this.locked = true;
		this.modified = false;

		this.div = this.wootEditor.div.appendChild(document.createElement("div"));
		this.div.className = "woot-chunk " + this.getClassForPermissions();
		var id = "chunk-" + Math.round(Math.random() * 1000000);
		while (document.getElementById(id)) {
			id = "chunk-" + Math.round(Math.random() * 1000000);
		}
		this.div.id = id;

		var labels = this.wootEditor.labels;
		var editButton = null;
		if (! this.chunk.isReadOnly()) {
			editButton = document.createElement("div");
			editButton.className = "edit-button";
			editButton.innerHTML = labels.edit.label;
			editButton.title = labels.edit.title;
			editButton.onclick = function() {
												that.unlock();
											};
			this.editButton = editButton;
		}
		var addButton = document.createElement("div");
			addButton.className = "add-button";
			addButton.innerHTML = labels.append.label;
			addButton.title = labels.append.title;
			addButton.onclick = function() {
				that.wootEditor.appendNewChunk(that);
			};
			this.addButton = addButton;

		var ownerText = document.createElement("div");
			ownerText.className = "owner-text";
			ownerText.innerHTML = "Author: "+ this.chunk.getOwnerRealName();
			this.ownerText = ownerText;

		// wrap text in a new div element to avoid onclick event conflict with add button in Edit mode
		var chunkText = document.createElement("div");
			chunkText.className = "chunk-content";
			chunkText.innerHTML = this.chunk.getText();
			if (! this.chunk.getText()) {
				chunkText.innerHTML = "(empty chunk)";
				this.div.className += " empty";
			}else{
				this.div.className = this.div.className.replace(/\sempty/g,"");
			}

		/*			if (! this.chunk.isReadOnly()) {
				chunkText.onclick = function() {
					if (that.wootEditor.edit) {
						that.unlock();
						return false;
					} else {
						return true;
					}
				};
			}
		*/
			this.chunkText = chunkText;

			this.div.appendChild(chunkText);
			if (ownerText)  { this.div.insertBefore(this.ownerText, this.div.firstChild); }


			this.div.insertBefore(this.addButton, this.div.firstChild);
			if (this.editButton) {
				this.div.insertBefore(this.editButton, this.addButton);
			}

			this.div.appendChild(document.createElement("br")).style.clear = "both";	// prevent floating contents from protruding
	}
	EditableChunk.prototype.getClassForPermissions = function() {
		/* Work out what the permissions mode of this chunk is, and return a CSS class name representing that */
		var perm;
		var perms = this.chunk.permissions;
		if (perms.length === 2  &&
		    ((perms[0] === HAPI.WOOT.WORLD_READONLY_PROTECTION && perms[1] === HAPI.WOOT.OWNER_PROTECTION) ||
		     (perms[1] === HAPI.WOOT.WORLD_READONLY_PROTECTION && perms[0] === HAPI.WOOT.OWNER_PROTECTION))) {
			return "world-ro-visible";
		}

		var className = "user-visible";
		for (var i=0, len=perms.length; i < len; ++i) {
			perm = perms[i];
			if (perm === HAPI.WOOT.WORLD_PROTECTION) {
				// world permissions trump any other
				return "world-visible";
			}
			if (perm === HAPI.WOOT.WORLD_READONLY_PROTECTION) {
				return "world-ro-visible";
			}
			if (perm !== HAPI.WOOT.OWNER_PROTECTION) {
				className = "mixed-visible";
			}
		}
		return className;
	};
	EditableChunk.prototype.lock = function() {
		if (this.locked) { return; }

		this.wootEditor.unlockedChunk = null;
		this.locked = true;

		uneditChunk(this);

		if (this.div.innerHTML.match(/\S/)) {
			// Need at least one non-whitespace character ...
			this.chunk.setText(this.div.innerHTML);
			this.div.className = this.div.className.replace(/\sempty\b/g, "");
		}
		else {
			// ... or else we regard the chunk as empty.
			this.chunk.setText("");
			this.div.innerHTML = "(empty chunk)";
			this.div.className += " empty";
		}
		this.chunkText.innerHTML = this.div.innerHTML; //re-render chunk text in a div to avoid onclick conflict
		this.div.innerHTML  = "";
		//this.div.appendChild(this.editButton);
		this.div.appendChild(this.chunkText);

		this.div.insertBefore(this.ownerText, this.div.firstChild);
		this.div.insertBefore(this.addButton, this.div.firstChild);
		if (this.editButton) {
				this.div.insertBefore(this.editButton, this.addButton);
		}


//		this.div.appendChild(document.createElement("br")).style.clear = "both";	// prevent floating contents from protruding
	};
	EditableChunk.prototype.unlock = function() {
		if (! this.locked) { return; }

		if (this.chunk.isReadOnly()) {
			alert("insufficient permissions to edit chunk");
			return;
		}

		if (this.locked  &&  this.wootEditor.unlockedChunk) {
			var unlockedChunk = this.wootEditor.unlockedChunk;
			unlockedChunk.lock();
			if (unlockedChunk.isModified()) {
				this.wootEditor.save();
			}
		}
		this.wootEditor.unlockedChunk = this;
		this.locked = false;

		this.div.removeChild(this.addButton);
		this.div.removeChild(this.ownerText);
		this.div.removeChild(this.editButton);
		if (this.chunkText.innerHTML.match(/\(empty\schunk\)/)) {
			this.chunkText.innerHTML = "";
		}
		this.innerHTML = this.chunkText.innerHTML;

		editChunk(this);
	};
	EditableChunk.prototype.isLocked = function() { return this.locked; };
	EditableChunk.prototype.isModified = function() { return this.chunk.isModified(); };
	EditableChunk.prototype.setPermissions = function(perms) { this.chunk.setPermissions(perms); };


	var editorInited = false;
	function editChunk(editableChunk) {
		if (! editorInited) {
			tinyMCE.init({
				mode: "none",

				content_css: (top.HeuristBaseURL?top.HeuristBaseURL:(HAPI.HeuristBaseURL?HAPI.HeuristBaseURL:"../../")) + "common/css/global.css",
				theme: "advanced",
				plugins: "inlinepopups,nonbreaking,permissions,save,hrecords",
				inline_styles: false,

				theme_advanced_buttons1: "save,cancel,|,permissions,|,link,unlink,image,hrecords,|,formatselect,hr,bold,italic,bullist,numlist,|,code,|,undo,redo",
				theme_advanced_buttons2: "",
				theme_advanced_buttons3: "",
				theme_advanced_blockformats: "p,h1,h2,h3",
				theme_advanced_toolbar_location: "top",
				theme_advanced_toolbar_align: "left",

				relative_urls : false,

				height: "400",	// saw TODO calculate from text length/line count

				hidden_input: 0,
				valid_elements: "+a[href|target=_blank],-b,-i,br,-p,img[src|align|width|height|border|vspace|hspace|alt],-h1,-h2,-h3,hr,-ul,-ol"
			});
			editorInited = true;
		}
		editableChunk.div.parentNode.className += " mce-loading";
		//create a new editor  and pass in eventHandlers if available
		if (editableChunk.wootEditor.events) {
			tinyMCE.execCommand("mceAddControl", false, editableChunk.div.id,editableChunk.wootEditor.events);
		}else{
			tinyMCE.execCommand("mceAddControl", false, editableChunk.div.id);
		}
		tinyMCE.settings.auto_focus = editableChunk.div.id;	// Set the new editor as auto-focused
		tinyMCE.activeChunk = editableChunk;
	}

	function uneditChunk(editableChunk) {
		tinyMCE.execCommand("mceRemoveControl", false, editableChunk.div.id);
	}

	var labelsForLanguage = {
		"en": {
			save: { label: "[save]", title: "Save all chunks" },
			edit: { label: "[edit]", title: "Edit this chunk" },
			append: { label: "[add]", title: "Add a new chunk after this one" },
			emptyMessage: { label: "(empty chunk)", title: "Click here to add a chunk to this WOOT" }
		}
	};

	return {
		WootEditor: WootEditor,
		EditableChunk: EditableChunk,
		labelsForLanguage: labelsForLanguage
	};
}();



function loadTinyMCE() {
	if (window.tinyMCE) { return; }

	document.write('<' + 'script src="' + HAPI.HeuristBaseURL + 'external/tinymce/jscripts/tiny_mce/tiny_mce.js">' + '</' + 'script>');
	return;
}

loadTinyMCE();
