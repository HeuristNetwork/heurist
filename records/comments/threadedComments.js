var installDir = function(){
	 var path = (top ? top.location.pathname : (window ? window.location.pathname : ""));
	 if ( path && path != "undefined") {
	 	path = path.match(/\/[^\s\/]\//);
	 	path = path ? path.replace(/\//g,"") : "";
	 	return path;
	 }
	 return "";
}();
var CommentManager = function(elem, record) {

	this.commentsDiv = elem;
	this.record = record;
	var thisRef = this;

	this.printAllComments = function() {
		thisRef.commentsDiv.innerHTML = "";

		var hComments = this.record.getComments();
		var comments = {};

		this.topLevelComment = { document: document, id: 0, div: this.commentsDiv };
			comments[0] = this.topLevelComment;

		this.commentButton = document.createElement("a");
		this.commentButton.className = "add-comment-link";
		this.commentButton.href = "#";
		this.commentButton.appendChild(document.createTextNode("Add comment"));
		this.commentButton.onclick = function() {
				thisRef.addComment();
				return false;
			};
		this.commentsDiv.appendChild(this.commentButton);

		this.printComments(hComments, comments, this.topLevelComment);
	};

	this.printComments = function(hComments, comments, topLevelComment) {
		for (var i = 0; i < hComments.length; ++i) {
			var hComment = hComments[i];

			var parentComment = hComment.getParent();

			if (parentComment  &&  comments[parentComment.getID()]) {
				comments[hComment.getID()] = new Comment(comments[parentComment.getID()], hComment);
			}
			else {	/* ownerless comment is a top-level comment */
				comments[hComment.getID()] = new Comment(topLevelComment, hComment);
			}

			this.printComments(hComment.getReplies(), comments, topLevelComment);
		}
	};

	this.addComment = function() {
		var editComment = new EditableComment(null, this.topLevelComment, this.record);
		this.commentButton.parentNode.insertBefore(editComment.div, this.commentButton.nextSibling);
		editComment.textarea.focus();
	}

}

var Comment = function(parentComment, hComment) {
	this.document = parentComment.document;
	this.parentComment = parentComment;
	if (parentComment) {
		if (! parentComment.childComments) parentComment.childComments = [];
		parentComment.childComments.push(this);
	}
	this.hComment = hComment;

	this.id = hComment.getID();

	this.div = parentComment.div.appendChild(document.createElement("div"));
		this.div.className = "comment";
	this.innerDiv = this.div.appendChild(this.document.createElement("div"));

	this.innerDiv.className = "block";

	var detailText = hComment.getText() || "";
		detailText = detailText.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
		detailText = detailText.replace(/(http:\/\/[\041-\176]+)/g, "<a href=\"$1\">$1</a>");
		detailText = detailText.replace(/\[(\d+)\]/g, "[<a href=\"/"+ installDir +"/records/view/viewRecord.php?bib_id=$1\">$1</a>]");

	this.textDiv = this.innerDiv.appendChild(this.document.createElement("div"));
		this.textDiv.className = "text";
		this.textDiv.innerHTML = detailText;

	this.footerDiv = this.innerDiv.appendChild(this.document.createElement("div"));
		this.footerDiv.className = "footer";
	this.author = this.footerDiv.appendChild(this.document.createElement("a"));
		this.author.className = "author";
		this.author.appendChild(this.document.createTextNode(hComment.getUser().getRealName()));
	if (hComment.getCreationDate()) {
		var date = this.footerDiv.appendChild(this.document.createElement("span"));
			date.className = "date";
			date.appendChild(this.document.createTextNode(this.formatDate(hComment.getCreationDate())));
	}
	if (hComment.getModificationDate()) {
		var modDate = this.footerDiv.appendChild(this.document.createElement("span"));
			modDate.className = "moddate";
			modDate.appendChild(this.document.createTextNode(" (modified " + this.formatDate(hComment.getModificationDate()) + ")"));
	}

	//this.footerDiv.appendChild(this.document.createTextNode("("));
	this.replyLink = this.footerDiv.appendChild(this.document.createElement("a"));
		this.replyLink.href = "#";
		this.replyLink.appendChild(this.document.createTextNode("reply"));
	//this.footerDiv.appendChild(this.document.createTextNode(")"));

	var thisRef = this;
	this.replyLink.onclick = function() { thisRef.reply(); return false; };

	if (hComment.getUser().getID() == HCurrentUser.getID()) {
		this.footerDiv.appendChild(this.document.createTextNode(" - "));
		this.editLink = this.footerDiv.appendChild(this.document.createElement("a"));
			this.editLink.href = "#";
			this.editLink.appendChild(this.document.createTextNode("edit"));
		//this.footerDiv.appendChild(this.document.createTextNode(") "));

		var thisRef = this;
		this.editLink.onclick = function() { thisRef.edit(); return false; };

		this.footerDiv.appendChild(this.document.createTextNode(" - "));
		this.deleteLink = this.footerDiv.appendChild(this.document.createElement("a"));
			this.deleteLink.href = "#";
			this.deleteLink.appendChild(this.document.createTextNode("delete"));
		//this.footerDiv.appendChild(this.document.createTextNode(") "));

		this.deleteLink.onclick = function() { thisRef.remove(); return false; };
	}
};
Comment.prototype.edit = function() {
	var editComment = new EditableComment(this, null, this.hComment.getRecord());
	this.div.replaceChild(editComment.innerDiv, this.innerDiv);
	editComment.textarea.focus();
};
Comment.prototype.reply = function() {
	var editComment = new EditableComment(null, this, this.hComment.getRecord());

	// Put the editable reply immediately after the comment being replied to,
	// even though the eventual comment will be at the bottom
	if (! this.innerDiv.nextSibling) {
		this.div.appendChild(editComment.div);
	} else {
		this.div.insertBefore(editComment.div, this.innerDiv.nextSibling);
	}
	editComment.textarea.focus();
};
Comment.prototype.remove = function() {
	if (! confirm("Delete this comment?")) return;

	this.hComment.getRecord().removeComment(this.hComment);

	var thisRef = this;
	var saver = new HSaver(
		function(r) {
			thisRef.deleted = true;
			thisRef.div.removeChild(thisRef.innerDiv);

			thisRef.innerDiv = thisRef.document.createElement("div");
				thisRef.innerDiv.className = "deleted";
				thisRef.innerDiv.appendChild(thisRef.document.createTextNode("(This comment has been deleted)"));

			if (thisRef.div.firstChild)
				thisRef.div.insertBefore(thisRef.innerDiv, thisRef.div.firstChild);
			else
				thisRef.div.appendChild(thisRef.innerDiv);
		},
		function(r,e) { alert("Error while deleting: " + e); }
	);
	HeuristScholarDB.saveRecord(this.hComment.getRecord(), saver);

};
Comment.prototype.formatDate = function(date) {
	//var date = new Date(Date.parse(date.replace(/-/g, "/")));
	//var now = new Date(Date.parse(top.HEURIST.record.retrieved.replace(/-/g, "/")));
	var now = new Date();

	var dateTime = Math.round(date.getTime() / 1000);
	var nowTime = Math.round(now.getTime() / 1000);

	/* less than two hours ago */
	if ((nowTime - dateTime) <= 119*60) {
		var mins = Math.round((nowTime - dateTime) / 60);
		if (mins == 0) return "less than one minute ago";
		else if (mins == 1) return "1 minute ago";
		return mins + " minutes ago";
	}

	/* less than a day ago */
	if ((nowTime - dateTime) <= 23*60*60) {
		var hours = Math.round((nowTime - dateTime) / (60*60));
		return hours + " hours ago";
	}

	/* e.g. "yesterday 6:39pm" */
	if ((nowTime - dateTime) < 48*60*60  &&  now.getDay() == date.getDay()+1) {
		return "yesterday " + this.formatTime(date.getHours(), date.getMinutes());
	}

	/* less than a week ago -- e.g. "Tuesday 6:39pm" */
	if ((nowTime - dateTime) < 7*24*60*60) {
		return this.dayNames[date.getDay()] + " " + this.formatTime(date.getHours(), date.getMinutes());
	}

	/* this year */
	if (now.getFullYear() == date.getFullYear()) {
		return this.monthNames[date.getMonth()] + " " + date.getDate() + ", " + this.formatTime(date.getHours(), date.getMinutes());
	}

	/* else ... */
	return this.monthNames[date.getMonth()] + " " + date.getDate() + " " + date.getFullYear() + ", " + this.formatTime(date.getHours(), date.getMinutes());
};
Comment.prototype.formatTime = function(hours, minutes) {
	if (hours > 12)
		return (hours - 12) + ":" + minutes + "pm";
	else
		return hours + ":" + minutes + "am";
};
Comment.prototype.dayNames = [ "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday" ];
Comment.prototype.monthNames = [ "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec" ];

var EditableComment = function(associatedComment, parentComment, record) {
	this.comment = associatedComment;

	if (associatedComment) {
		this.document = associatedComment.document;
		this.hComment = this.comment.hComment;
		this.id = this.hComment.getID();
		this.parentComment = associatedComment.parentComment;
		this.div = associatedComment.div;
	} else {
		this.document = parentComment.document;
		this.hComment = new HComment(parentComment  &&  parentComment.hComment ? parentComment.hComment : record);
		this.id = 0;
		this.parentComment = parentComment;
		this.div = this.document.createElement("div");
		this.div.className = "comment";
	}
	this.innerDiv = this.document.createElement("div");
		this.innerDiv.className = "editing-block";

	if (! associatedComment) {
		this.div.appendChild(this.innerDiv);
	}

	this.textarea = this.innerDiv.appendChild(this.document.createElement("textarea"));
		this.textarea.value = this.textarea.defaultValue = this.hComment.getText()? this.hComment.getText() : "";
//	var helpDiv = this.innerDiv.appendChild(this.document.createElement("div"));
//		helpDiv.className = "help prompt";
//		helpDiv.appendChild(this.document.createTextNode("Reference other records as [XXX] where XXX is the record number"));

	var thisRef = this;
	this.saveButton = this.document.createElement("input");
		this.saveButton.type = "button";
		this.saveButton.value = "Save edit";
		this.saveButton.onclick = function() { thisRef.save(); };
		this.innerDiv.appendChild(this.saveButton);

	this.cancelButton = this.document.createElement("input");
		this.cancelButton.type = "button";
		this.cancelButton.value = "Cancel edit";
		this.cancelButton.onclick = function() { thisRef.cancel(); return false; };
		this.innerDiv.appendChild(this.cancelButton);
};
EditableComment.prototype.save = function() {
	if (this.textarea.value == "") {
		// Don't bother saving an empty input
		this.cancel();
		return;
	}

	var thisRef = this;
	this.hComment.setText(this.textarea.value);

	var saver = new HSaver(
		function(r) {
			newComment = new Comment(thisRef.parentComment, thisRef.hComment);
			if (thisRef.comment) {
				// replace this new comment where the old one was
				thisRef.div.parentNode.replaceChild(newComment.div, thisRef.div);
			} else {
				// won't happen any more
				thisRef.div.parentNode.removeChild(thisRef.div);
			}
			newComment.replyLink.focus();
			newComment.author.focus();
		},
		function(r,e) { alert("Error while saving: " + e); }
	);
	HeuristScholarDB.saveRecord(this.hComment.getRecord(), saver);
}
EditableComment.prototype.cancel = function() {
	if (this.comment) {
		/* replace this editable comment with the plain comment that it replaced */
		this.div.replaceChild(this.comment.innerDiv, this.innerDiv);
	} else {
		this.div.parentNode.removeChild(this.div);
	}
	if (this.id == 0) {
		if (this.hComment.getParent())
			this.hComment.getParent().removeReply(this.hComment);
		else
			this.hComment.getRecord().removeComment(this.hComment);
	}
};


