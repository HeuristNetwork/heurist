/*
* Copyright (C) 2005-2015 University of Sydney
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

/*
OLD. NOT USED
*/

function checkField (selector, name) {
	if ($(selector).val() == '') {
		alert(name + ' is required');
		return false;
	}
	return true;
}

function checkEmail (selector) {
	if (! $(selector).val().match(/\S+@\S+\.\S+/)) {
		alert('Invalid Email address');
		return false;
	}
	return true;
}

function validateForm () {
	if (checkField('#name', 'Name')  &&
	    checkField('#email', 'Email')  &&
	    checkEmail('#email')  &&
	    checkField('#message', 'Message')) {
		return true;
	}
	return false;
}

$(function () {
	var elems, i, matches;

	if (location.search.match(/\bsuccess=1/)) {
		alert('Message sent');
	}

	if (location.search.match(/\berror=incorrect-captcha-sol/)) {
		alert('Incorrect CAPTCHA.  Please try again.');
	}

	elems = ['name', 'email', 'phone', 'message'];
	for (i = 0; i < elems.length; ++i) {
		matches = location.search.match(new RegExp('\\b' + elems[i] + '=([^&]+)'));
		if (matches) {
			$('#' + elems[i]).val(unescape(matches[1].replace(/\+/g, ' ')));
		}
	}
});
