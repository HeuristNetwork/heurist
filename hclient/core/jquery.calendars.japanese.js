/**
 * @file Implements the Traditional Japanese Calendar for the jQuery Calendars plugin.
 * This implementation is based on the Gregorian calendar and includes handling for Japanese Imperial Eras.
 * It extends the `$.calendars.baseCalendar` provided by the jQuery Calendars plugin.
 *
 * Note: The original file mentions it's based on Keith Wood's Gregorian calendar implementation.
 */

/**
 * @modulecalendars-japanese
 * @description This IIFE encapsulates the Japanese calendar implementation to avoid global scope pollution
 * and to correctly alias jQuery to `$`.
 */
(function ($) { // Hide scope, no $ conflict

	/**
	 * Constructs a new JapaneseCalendar instance.
	 * Initializes the calendar with regional settings for the specified language,
	 * falling back to default Japanese settings if the language is not found.
	 *
	 * @constructor JapaneseCalendar
	 * @param {string} [language=''] - The language code (e.g., 'ja') for localization.
	 *                                 Defaults to empty string for base Japanese regional settings.
	 * @see $.calendars.baseCalendar
	 */
	function JapaneseCalendar(language) {
		this.local = this.regional[language || ''] || this.regional[''];
	}

	// Inherit from the base calendar implementation.
	JapaneseCalendar.prototype = new $.calendars.baseCalendar;

	// Extend the JapaneseCalendar prototype with specific implementations.
	$.extend(JapaneseCalendar.prototype, {
		/**
		 * The name of this calendar.
		 * @memberof JapaneseCalendar.prototype
		 * @type {string}
		 */
		name: 'Japanese',
		/**
		 * The Julian Day number of the epoch date for this calendar (same as Gregorian).
		 * @memberof JapaneseCalendar.prototype
		 * @type {number}
		 */
		jdEpoch: 1721425.5, // Gregorian's Epoch date in Julian notation

		/**
		 * The number of days in each month of a common year.
		 * @memberof JapaneseCalendar.prototype
		 * @type {number[]}
		 */
		daysPerMonth: [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31],

		/**
		 * Indicates that this calendar does not have a year zero.
		 * @memberof JapaneseCalendar.prototype
		 * @type {boolean}
		 */
		hasYearZero: false,
		/**
		 * The minimum month number (1 for January).
		 * @memberof JapaneseCalendar.prototype
		 * @type {number}
		 */
		minMonth: 1,
		/**
		 * The minimum day number (1).
		 * @memberof JapaneseCalendar.prototype
		 * @type {number}
		 */
		minDay: 1,
		/**
		 * The first month of the year (1 for January).
		 * @memberof JapaneseCalendar.prototype
		 * @type {number}
		 */
		firstMonth: 1,

		/**
		 * Regional localization settings for the Japanese calendar.
		 * @memberof JapaneseCalendar.prototype
		 * @property {Object} '' - Default regional settings (Japanese).
		 * @property {string} ''.name - Calendar name.
		 * @property {string[]} ''.epochs - Placeholder for era names (currently not fully utilized for display here, eras are in `JAPANESE_CALENDAR_DATA`).
		 * @property {string[]} ''.monthNames - Full month names.
		 * @property {string[]} ''.monthNamesShort - Abbreviated month names.
		 * @property {string[]} ''.dayNames - Full day names.
		 * @property {string[]} ''.dayNamesShort - Abbreviated day names.
		 * @property {string[]} ''.dayNamesMin - Minimal day names.
		 * @property {string} ''.dateFormat - Default date format pattern.
		 * @property {number} ''.firstDay - The first day of the week (0 for Sunday).
		 * @property {boolean} ''.isRTL - Indicates if the language is Right-to-Left (false for Japanese).
		 * @property {boolean} ''.showMonthAfterYear - True to display month after year.
		 * @property {string} ''.yearSuffix - Suffix to append to the year (e.g., '年').
		 */
		regional: {
			'': {
				name: 'Japanese',
				epochs: ['', ''], // Likely placeholder; actual era names (Romaji/Kanji) are handled by formatting methods using JAPANESE_CALENDAR_DATA
				monthNames: ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月'],
				monthNamesShort: ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'],
				dayNames: ['日曜日', '月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日'],
				dayNamesShort: ['日', '月', '火', '水', '木', '金', '土'],
				dayNamesMin: ['日', '月', '火', '水', '木', '金', '土'],
				dateFormat: 'yyyy/mm/dd',
				firstDay: 0,
				isRTL: false,
				showMonthAfterYear: true,
				yearSuffix: '年'
			}
		},

		/**
		 * Determines if the given Gregorian year is a leap year.
		 * The Japanese calendar follows the Gregorian rules for leap years.
		 * @memberof JapaneseCalendar.prototype
		 * @param {CDate|number} year - The date object (from which year is extracted) or the year number (Gregorian).
		 * @returns {boolean} True if it's a leap year, false otherwise.
		 * @throws {Error} If an invalid year or a date from a different calendar is provided.
		 */
		leapYear: function (year) {
			let date = this._validate(year, this.minMonth, this.minDay,
				$.calendars.local.invalidYear || $.calendars.regional[''].invalidYear);
			let gregorianYear = date.year();
			// Gregorian calendar rules for leap year
			return gregorianYear % 4 === 0 && (gregorianYear % 100 !== 0 || gregorianYear % 400 === 0);
		},

		/**
		 * Calculates the week of the year for a given date.
		 * Assumes weeks start on Monday, and the first week of the year is the one containing January 4th.
		 *
		 * @memberof JapaneseCalendar.prototype
		 * @param {CDate|number} year - A `CDate` object representing the date, or the Gregorian year number.
		 * @param {number} [month] - The month number (1-12) if `year` is a number.
		 * @param {number} [day] - The day number if `year` is a number.
		 * @returns {number} The week of the year (1-53).
		 */
		weekOfYear: function (year, month, day) {
			let checkDate = this.newDate(year, month, day); // Creates a date in this calendar system
			// Find Thursday of this week (ISO 8601 week date system)
			// Day of week: Sunday is 0, Monday is 1, ..., Saturday is 6 by default in baseCalendar
			// If dayOfWeek() returns 0 (Sunday), use 7 for calculation
			checkDate.add(4 - (checkDate.dayOfWeek() || 7), 'd');
			return Math.floor((checkDate.dayOfYear() - 1) / 7) + 1;
		},

		/**
		 * Retrieves the number of days in a specific month of a given year.
		 * Accounts for leap years for February.
		 *
		 * @memberof JapaneseCalendar.prototype
		 * @param {CDate|number} year - A `CDate` object or the Gregorian year number.
		 * @param {number} [month] - The month number (1-12) if `year` is a number.
		 * @returns {number} The number of days in the specified month.
		 * @throws {Error} If an invalid month or a date from a different calendar is provided.
		 */
		daysInMonth: function (year, month) {
			let date = this._validate(year, month, this.minDay,
				$.calendars.local.invalidMonth || $.calendars.regional[''].invalidMonth);
			// Uses daysPerMonth array and adjusts for February in a leap year
			return this.daysPerMonth[date.month() - 1] +
				(date.month() === 2 && this.leapYear(date.year()) ? 1 : 0);
		},

		/**
		 * Determines if the given date falls on a weekday (Monday to Friday).
		 *
		 * @memberof JapaneseCalendar.prototype
		 * @param {CDate|number} year - A `CDate` object or the Gregorian year number.
		 * @param {number} [month] - The month number if `year` is a number.
		 * @param {number} [day] - The day number if `year` is a number.
		 * @returns {boolean} True if the date is a weekday, false otherwise.
		 */
		weekDay: function (year, month, day) {
			// dayOfWeek(): Sunday is 0, Saturday is 6.
			// (this.dayOfWeek(...) || 7) makes Sunday 7, so weekdays are 1-5.
			return (this.dayOfWeek(year, month, day) || 7) < 6;
		},

		/**
		 * Creates a new CDate object for the Japanese calendar.
		 * If the year is provided as a Japanese era string (e.g., "令和5年"), it's converted to Gregorian.
		 *
		 * @memberof JapaneseCalendar.prototype
		 * @param {CDate|number|string|null} year - A `CDate` object, a Gregorian year number,
		 *                                        a Japanese year string (e.g., "令和5年"), or null for today's date.
		 * @param {number} [month] - The month number if `year` is a number/string.
		 * @param {number} [day] - The day number if `year` is a number/string.
		 * @returns {CDate} The new CDate object.
		 * @throws {Error} If an invalid date or a date from a different calendar is provided.
		 */
		newDate: function (year, month, day) {
			if (year == null) { // No arguments, return today's date
				return this.today();
			}

			// If year is already a CDate object of this calendar, extract components
			if (year.year && year.calendar().name === this.name) {
				this._validate(year, month, day, // Basic validation
					$.calendars.local.invalidDate || $.calendars.regional[''].invalidDate);
				day = year.day();
				month = year.month();
				year = year.year(); // Use Gregorian year from CDate
			} else if (year.year) { // CDate from another calendar
                 // Convert CDate from another calendar to JS Date, then from JS Date to this calendar's CDate
                let jsDate = year.toJSDate();
                return this.fromJSDate(jsDate);
            }


			// If year is a Japanese era string (e.g., "令和5年")
			if (typeof year === 'string' && year.includes('年')) {
                const converted = this._japaneseYearToGregorian(year);
                if (typeof converted === 'string') { // Error string returned
                    throw $.calendars.local.invalidDate || $.calendars.regional[''].invalidDate;
                }
				[, year] = converted; // Extract Gregorian year
			}
			// year should now be a Gregorian year number
			return new $.calendars.cdate(this, parseInt(year, 10), parseInt(month, 10), parseInt(day, 10));
		},

		/**
		 * Checks if a given Gregorian date is valid within the context of the Japanese calendar eras.
		 * Note: This primarily checks if the Gregorian year can be represented in an era, not general date validity (day/month ranges).
		 *
		 * @memberof JapaneseCalendar.prototype
		 * @param {CDate|number} year - A `CDate` object or the Gregorian year number.
		 * @param {number} [month] - The month number if `year` is a number.
		 * @param {number} [day] - The day number if `year` is a number.
		 * @returns {boolean} True if the year falls within a defined Japanese era, false otherwise.
		 */
		isValid: function (year, month, day) {
			// _validateFromGregorian converts Gregorian to Japanese year string or era year number.
			// We only care if the conversion results in a non-zero year (meaning it found an era).
			let [jpYear,,] = this._validateFromGregorian(year, month, day, false); // false for not including Kanji
			return Number.isInteger(+jpYear) && +jpYear !== 0; // Check if jpYear is a valid non-zero number
		},

		/**
		 * Converts a Gregorian date to its Japanese era representation (year, month, day).
		 * The year part will be the year number within the era, optionally prefixed with Kanji.
		 *
		 * @private
		 * @memberof JapaneseCalendar.prototype
		 * @param {CDate|number} yearInput - A `CDate` object or the Gregorian year number.
		 * @param {number} [monthInput] - The month number (1-12) if `yearInput` is a number.
		 * @param {number} [dayInput] - The day number if `yearInput` is a number.
		 * @param {boolean} [include_kanji=true] - If true, prefixes the era year with Kanji (e.g., "令和5"). Otherwise, just the era year number.
		 * @returns {Array<string|number>} An array `[japaneseEraYear, month, day]`. `japaneseEraYear` is a string if Kanji included, else number.
		 *                                 Returns `[0, month, day]` if the date doesn't fall into any defined era.
		 */
		_validateFromGregorian: function (yearInput, monthInput, dayInput, include_kanji = true) {
			let year, month, day;
			if (yearInput.year) { // If CDate object
				day = yearInput.day();
				month = yearInput.month();
				year = yearInput.year();
			} else {
				year = parseInt(yearInput, 10);
				month = parseInt(monthInput, 10);
				day = parseInt(dayInput, 10);
			}

			// Basic validation for month and day
			if(!Number.isInteger(month) || month <= 0 || month > 12) month = 1; // Default to Jan if invalid
			if(!Number.isInteger(day) || day <= 0 || day > 31) day = 1; // Default to 1st if invalid

			let eraYear = 0; // Default if no era found

			// Determine search range in JAPANESE_CALENDAR_DATA for efficiency
			const [start_idx, end_idx_plus_one] = this._getEraIndexes(year); // end_idx_plus_one is exclusive upper bound

			// Handle specific intersection years where an era might end and another begin mid-year.
			// This logic seems specific and might need review if JAPANESE_CALENDAR_DATA structure changes.
			// The years 999, 1171, 1321, 1624 are treated as special cutoffs.
			// This part of the logic seems to assume these are indices, not years directly.
			// Let's assume end_idx_plus_one-1 is the correct index for the current/ending era at these cutoffs.
			const intersectionCutoffYears = [650, 686, 701, 704, 708, 715, 717, 724, 729, 749, 757, 765, 767, 770, 781, 782, 806, 810, 823, 834, 848, 851, 855, 857, 859, 877, 885, 889, 898, 901, 923, 931, 938, 947, 957, 961, 964, 968, 970, 974, 976, 979, 983, 985, 987, 989, 990, 995, 999, 1004, 1013, 1017, 1021, 1024, 1028, 1037, 1040, 1045, 1046, 1053, 1058, 1065, 1069, 1074, 1078, 1081, 1084, 1087, 1095, 1097, 1098, 1099, 1104, 1106, 1108, 1110, 1113, 1118, 1120, 1124, 1126, 1131, 1132, 1135, 1141, 1142, 1144, 1145, 1151, 1154, 1156, 1159, 1160, 1161, 1163, 1165, 1166, 1169, 1171, 1175, 1177, 1181, 1182, 1184, 1185, 1190, 1199, 1201, 1204, 1206, 1207, 1211, 1214, 1219, 1222, 1225, 1228, 1229, 1232, 1233, 1235, 1239, 1240, 1243, 1247, 1249, 1256, 1257, 1259, 1260, 1261, 1264, 1275, 1278, 1288, 1293, 1299, 1303, 1307, 1308, 1311, 1312, 1317, 1319, 1321, 1325, 1326, 1329, 1331, 1332, 1338, 1342, 1345, 1350, 1352, 1356, 1361, 1362, 1368, 1375, 1379, 1381, 1384, 1387, 1389, 1390, 1394, 1428, 1429, 1441, 1444, 1449, 1452, 1455, 1457, 1461, 1466, 1467, 1469, 1487, 1489, 1492, 1501, 1504, 1521, 1528, 1532, 1555, 1558, 1570, 1573, 1593, 1596, 1615, 1624, 1645, 1648, 1652, 1655, 1658, 1661, 1673, 1681, 1684, 1688, 1704, 1711, 1716, 1736, 1741, 1744, 1748, 1751, 1764, 1773, 1781, 1801, 1802, 1804, 1818, 1831, 1845, 1848, 1855, 1860, 1861, 1864, 1865, 1868, 1912, 1926, 1989, 2019];

			let currentEraIndex = -1;
			for (let i = JAPANESE_CALENDAR_DATA.length - 1; i >= 0; i--) {
				const era = JAPANESE_CALENDAR_DATA[i];
				if (year > era.start[0] || (year === era.start[0] && (month > era.start[1] || (month === era.start[1] && day >= era.start[2])))) {
					// Check if also within era end (if defined)
					if (!era.end.length || year < era.end[0] || (year === era.end[0] && (month < era.end[1] || (month === era.end[1] && day <= era.end[2])))) {
						currentEraIndex = i;
						break;
					}
				}
			}

			if (currentEraIndex !== -1) {
				const eraDetails = JAPANESE_CALENDAR_DATA[currentEraIndex];
				eraYear = (year - eraDetails.start[0]) + 1;
				if (include_kanji) {
					eraYear = `${eraDetails.kanji}${eraYear}`;
				}
			}
			// If no era found, eraYear remains 0

			return [eraYear, month, day];
		},

		/**
		 * Converts a date from this calendar to a Julian Day number.
		 * The input year can be a Gregorian year or a Japanese era year string.
		 *
		 * @memberof JapaneseCalendar.prototype
		 * @param {CDate|number|string} year - A `CDate` object, Gregorian year number, or Japanese year string (e.g., "令和5年").
		 * @param {number} [month] - The month number if `year` is a number/string.
		 * @param {number} [day] - The day number if `year` is a number/string.
		 * @returns {number} The Julian Day number.
		 */
		toJD: function (year, month, day) {
			const gregorian_calendar = $.calendars.instance(); // Get default Gregorian calendar instance
			this._validateLevel++; // Internal validation level for base calendar

			// _checkYear converts Japanese year string to Gregorian CDate if necessary
			let dateToCheck = this._checkYear(year, month, day);
			this._validateLevel--;

			let gregorianDate;
			if (dateToCheck && dateToCheck.year) { // If it's a CDate object (already Gregorian or converted)
				gregorian_calendar._validateLevel++;
				// Ensure it's a Gregorian CDate for toJD calculation
				gregorianDate = gregorian_calendar.newDate(dateToCheck.year(), dateToCheck.month(), dateToCheck.day());
				gregorian_calendar._validateLevel--;
			} else if (typeof dateToCheck === 'number') { // Was already a Gregorian year number
                 gregorianDate = gregorian_calendar.newDate(dateToCheck, month, day);
            } else {
				// Handle error or invalid input from _checkYear if it returns null/string
				throw $.calendars.local.invalidDate || $.calendars.regional[''].invalidDate;
			}
			return gregorian_calendar.toJD(gregorianDate); // Use Gregorian calendar's toJD
		},

		/**
		 * Converts a Julian Day number to a date in this (Japanese) calendar.
		 * 
		 * @memberof JapaneseCalendar.prototype
		 * @param {number} jd - The Julian Day number.
		 * @returns {CDate} The corresponding CDate object in the Japanese calendar.
		 */
		fromJD: function (jd) {
			const gregorian_calendar = $.calendars.instance();
			const gregorianDate = gregorian_calendar.fromJD(jd);
			// newDate for JapaneseCalendar will handle the Gregorian year internally
			return this.newDate(gregorianDate.year(), gregorianDate.month(), gregorianDate.day());
		},

		/**
		 * Converts a date from this calendar to a JavaScript Date object.
		 * The input year can be a Gregorian year or a Japanese era year string.
		 *
		 * @memberof JapaneseCalendar.prototype
		 * @param {CDate|number|string} year - A `CDate` object, Gregorian year number, or Japanese year string.
		 * @param {number} [month] - The month number if `year` is a number/string.
		 * @param {number} [day] - The day number if `year` is a number/string.
		 * @returns {Date} The JavaScript Date object.
		 */
		toJSDate: function (year, month, day) {
			const gregorian_calendar = $.calendars.instance();
			this._validateLevel++;
			let dateToCheck = this._checkYear(year, month, day);
			this._validateLevel--;

			let gregorianDate;
            if (dateToCheck && dateToCheck.year) {
                gregorian_calendar._validateLevel++;
                gregorianDate = gregorian_calendar.newDate(dateToCheck.year(), dateToCheck.month(), dateToCheck.day());
                gregorian_calendar._validateLevel--;
            } else if (typeof dateToCheck === 'number') {
                 gregorianDate = gregorian_calendar.newDate(dateToCheck, month, day);
            } else {
                throw $.calendars.local.invalidDate || $.calendars.regional[''].invalidDate;
            }
			return gregorian_calendar.toJSDate(gregorianDate);
		},

		/**
		 * Converts a JavaScript Date object to a date in this (Japanese) calendar.
		 * 
		 * @memberof JapaneseCalendar.prototype
		 * @param {Date} jsd - The JavaScript Date object.
		 * @returns {CDate} The corresponding CDate object in the Japanese calendar.
		 */
		fromJSDate: function (jsd) {
			const gregorian_calendar = $.calendars.instance();
			const gregorianDate = gregorian_calendar.fromJSDate(jsd);
			return this.newDate(gregorianDate.year(), gregorianDate.month(), gregorianDate.day());
		},

		/**
		 * Extracts the era Kanji from a Japanese date string (e.g., "令和5年" -> "令和").
		 * 
		 * @memberof JapaneseCalendar.prototype
		 * @param {string} str - The Japanese date string, expected to contain '年'.
		 * @returns {number} The index of the era in `JAPANESE_CALENDAR_DATA`, or -1 if not found or invalid format.
		 */
		getEraFromJapaneseStr: function(str){
			if (typeof str !== 'string' || str.indexOf('年') === -1) {
				return -1; // Invalid format
			}
			const yearPart = str.split('年')[0]; // Get the part before '年'
			const eraKanji = yearPart.replace(/[0-9０-９]+/, ''); // Remove numbers (half/full-width) to get Kanji

			if (eraKanji === '') {
				return -1; // No era Kanji found
			}

			for (let i = 0; i < JAPANESE_CALENDAR_DATA.length; i++) {
				if (JAPANESE_CALENDAR_DATA[i]['kanji'] === eraKanji) {
					return i; // Return the index of the matched era
				}
			}
			return -1; // Era Kanji not found in data
		},

		/**
		 * Determines the Japanese era index for a given Gregorian date.
		 * 
		 * @memberof JapaneseCalendar.prototype
		 * @param {CDate|number} yearInput - A `CDate` object or the Gregorian year number.
		 * @param {number} [monthInput] - The month number if `yearInput` is a number.
		 * @param {number} [dayInput] - The day number if `yearInput` is a number.
		 * @returns {number} The index of the era in `JAPANESE_CALENDAR_DATA`, or -1 if the date does not fall into any defined era.
		 */
		getEraFromGregorian: function(yearInput, monthInput, dayInput){
			let year, month, day;
			if (yearInput.year) { // CDate object
				day = yearInput.day();
				month = yearInput.month();
				year = yearInput.year();
			} else {
				year = parseInt(yearInput, 10);
				month = parseInt(monthInput, 10);
				day = parseInt(dayInput, 10);
			}

			if (!Number.isInteger(month) || month <= 0 || month > 12) month = 1;
			if (!Number.isInteger(day) || day <= 0 || day > 31) day = 1;

			// Iterate backwards through eras to find the matching one
			for (let i = JAPANESE_CALENDAR_DATA.length - 1; i >= 0; i--) {
				const era = JAPANESE_CALENDAR_DATA[i];
				const start = era.start; // [Y, M, D]
				const end = era.end;   // [Y, M, D] or [] for ongoing

				// Check if date is on or after era start
				const afterStart = year > start[0] ||
								 (year === start[0] && (month > start[1] || (month === start[1] && day >= start[2])));

				if (afterStart) {
					// Check if date is before or on era end (if end is defined)
					if (!end.length || // Ongoing era
						(year < end[0] || (year === end[0] && (month < end[1] || (month === end[1] && day <= end[2]))))) {
						return i; // Found the era
					}
				}
			}
			return -1; // No matching era found
		},

		/**
		 * Converts a Gregorian date to a standard Japanese date string format (e.g., "令和5年10月26日").
		 * 
		 * @memberof JapaneseCalendar.prototype
		 * @param {CDate|number} year - A `CDate` object or the Gregorian year number.
		 * @param {number} [month] - The month number if `year` is a number.
		 * @param {number} [day] - The day number if `year` is a number.
		 * @returns {string} The formatted Japanese date string, or an empty string if conversion is not possible.
		 */
		gregorianToJapaneseStr: function (year, month, day) {
			// _validateFromGregorian returns [japaneseEraYearWithKanji, month, day]
			const [jpYear, jpMonth, jpDay] = this._validateFromGregorian(year, month, day, true);

			if (jpYear === 0 || (typeof jpYear === 'string' && jpYear.startsWith('0'))) { // Era not found or invalid
				return '';
			}
			return `${jpYear}年${jpMonth}月${jpDay}日`;
		},

		/**
		 * Converts a Japanese date string (e.g., "令和5年10月26日") or Japanese CDate components
		 * to a Gregorian CDate object.
		 * 
		 * @memberof JapaneseCalendar.prototype
		 * @param {string|CDate|number} yearOrDateString - Japanese date string, a CDate object (assumed to be Japanese era based if string-like year), or Japanese era year number.
		 * @param {number} [month] - The month number if `yearOrDateString` is a year number/string.
		 * @param {number} [day] - The day number if `yearOrDateString` is a year number/string.
		 * @returns {CDate|string} A Gregorian CDate object if conversion is successful, or an error string (e.g., "Invalid year") on failure.
		 */
		japaneseToGregorian: function (yearOrDateString, month, day) {
			let eraYearStr, eraMonthStr, eraDayStr;

			if (typeof yearOrDateString === 'object' && yearOrDateString.year) { // If CDate object
				eraDayStr = String(yearOrDateString.day());
				eraMonthStr = String(yearOrDateString.month());
				eraYearStr = String(yearOrDateString.year()); // This year might already be Gregorian or Japanese era year string
			} else if (typeof yearOrDateString === 'string' && yearOrDateString.includes('年') && yearOrDateString.includes('月') && yearOrDateString.includes('日')) {
				// Try to parse "EraNameY年M月D日" format
				const match = yearOrDateString.match(/(\D{1,4}\d+|\d+)(?:年)(\d+)(?:月)(\d+)(?:日)/);
				if (match) {
					eraYearStr = match[1];
					eraMonthStr = match[2];
					eraDayStr = match[3];
				} else {
					return 'Invalid date string format';
				}
			} else if (yearOrDateString !== undefined && month !== undefined && day !== undefined) {
                eraYearStr = String(yearOrDateString);
                eraMonthStr = String(month);
                eraDayStr = String(day);
            } else {
				return ''; // Invalid input
			}

			const convertedYear = this._japaneseYearToGregorian(eraYearStr);
			if (typeof convertedYear === 'string') { // Error from _japaneseYearToGregorian
				return convertedYear; // e.g., "Invalid year"
			}

			const [, gregorianYear] = convertedYear;
			const gregorianMonth = parseInt(eraMonthStr, 10);
			const gregorianDay = parseInt(eraDayStr, 10);

			if (isNaN(gregorianYear) || gregorianYear <= 0) return 'Invalid year';
			if (isNaN(gregorianMonth) || gregorianMonth <= 0 || gregorianMonth > 12) return 'Invalid month';
			if (isNaN(gregorianDay) || gregorianDay <= 0 || gregorianDay > 31) return 'Invalid day'; // Basic check, actual daysInMonth depends on month/year

			// Use the default Gregorian calendar instance for validation and CDate creation
			const gregCal = $.calendars.instance();
			if (!gregCal.isValid(gregorianYear, gregorianMonth, gregorianDay)) return 'Invalid Gregorian date';

			return gregCal.newDate(gregorianYear, gregorianMonth, gregorianDay);
		},

		/**
		 * Converts a Japanese date string directly to a Gregorian date string (YYYY-MM-DD).
		 * 
		 * @memberof JapaneseCalendar.prototype
		 * @param {string} japaneseDateStr - The Japanese date string (e.g., "令和5年10月26日").
		 * @returns {string} The Gregorian date string in "YYYY-MM-DD" format, or an empty string if conversion fails.
		 */
		japaneseToGregorianStr: function (japaneseDateStr) {
			const gregorianDate = this.japaneseToGregorian(japaneseDateStr);
			if (gregorianDate && gregorianDate.year) { // Check if it's a valid CDate
				return `${gregorianDate.year()}-${String(gregorianDate.month()).padStart(2,'0')}-${String(gregorianDate.day()).padStart(2,'0')}`;
			}
			return ''; // Return empty if conversion failed
		},

		/**
		 * Retrieves a list of Japanese era names.
		 * 
		 * @memberof JapaneseCalendar.prototype
		 * @param {boolean} [kanji_only=false] - If true, returns only the Kanji names of the eras.
		 *                                       Otherwise, returns "RomajiName (KanjiName)".
		 * @returns {string[]} An array of era name strings.
		 */
		getEras: function (kanji_only = false) {
			let list = [];
			for (let i = 0; i < JAPANESE_CALENDAR_DATA.length; i++) {
				const details = JAPANESE_CALENDAR_DATA[i];
				const label = kanji_only ? details['kanji'] : `${details['era']} (${details['kanji']})`;
				list.push(label);
			}
			return list;
		},

		/**
		 * Formats a CDate object representing a Japanese date into a string.
		 * Currently, this method defaults to calling `gregorianToJapaneseStr`,
		 * implying the input `date` object's year, month, day are Gregorian and need conversion.
		 * The `format` parameter is ignored in this specific implementation.
		 *
		 * @memberof JapaneseCalendar.prototype
		 * @param {string} format - The desired format string (currently ignored).
		 * @param {CDate} date - The CDate object to format. Assumed to hold Gregorian date components.
		 * @param {Object} [settings] - Formatting settings (currently ignored).
		 * @returns {string} The formatted Japanese date string.
		 */
		formatDate: function (format, date, settings) {
			// This assumes 'date' object holds Gregorian components that need conversion to Japanese string.
			// If 'date' was already a Japanese CDate, its internal year might already be an era year.
			// However, CDate objects in this plugin usually store Gregorian year internally.
			return this.gregorianToJapaneseStr(date.year(), date.month(), date.day());
		},

		/**
		 * Retrieves the Gregorian start and end dates for a given Japanese era index.
		 * 
		 * @memberof JapaneseCalendar.prototype
		 * @param {number} era_index - The index of the era in `JAPANESE_CALENDAR_DATA`.
		 * @returns {Array<Array<number>|null>} An array `[startDateArray, endDateArray]`.
		 *          `startDateArray` is `[year, month, day]`.
		 *          `endDateArray` is `[year, month, day]` or an empty array `[]` if the era is ongoing.
		 *          Returns `[null, null]` if `era_index` is out of bounds.
		 */
		getEraLimits: function (era_index) {
			if (era_index < 0 || era_index >= JAPANESE_CALENDAR_DATA.length) {
				return [null, null]; // Out of bounds
			}
			const era = JAPANESE_CALENDAR_DATA[era_index];
			return [era.start, era.end.length ? era.end : []]; // Return start and end (or empty array for ongoing)
		},

		/**
		 * Internal helper to check and convert a year input (which might be a Japanese era year string)
		 * to a Gregorian year CDate object or number.
		 * It's used by `toJD` and `toJSDate` before converting to Gregorian.
		 *
		 * @private
		 * @memberof JapaneseCalendar.prototype
		 * @param {CDate|number|string} yearInput - A CDate object, a year number, or a Japanese year string.
		 * @param {number} [month] - The month, used if `yearInput` is a number/string.
		 * @param {number} [day] - The day, used if `yearInput` is a number/string.
		 * @returns {CDate|number|null} A CDate object (if conversion from Japanese string was successful),
		 *                              a number (if input was already Gregorian year number),
		 *                              or null/error string if input is invalid.
		 */
		_checkYear: function (yearInput, month, day) {
			// If yearInput is already a CDate object of this calendar, its .year() is Gregorian.
			if (typeof yearInput === 'object' && yearInput.year && typeof yearInput.calendar === 'function' && yearInput.calendar().name === this.name) {
				return yearInput.year(); // Return Gregorian year
			}
			// If it's a string that looks like a Japanese year, convert it.
			if (typeof yearInput === 'string' && yearInput.includes('年')) {
				return this.japaneseToGregorian(yearInput, month, day); // This returns a Gregorian CDate or error string
			}
			// If it's a number, assume it's a Gregorian year.
			if (typeof yearInput === 'number') {
				return yearInput;
			}
			// If it's a CDate from another calendar, convert it
			if (typeof yearInput === 'object' && yearInput.year) {
				const jsDate = yearInput.toJSDate();
                const gregDate = $.calendars.instance().fromJSDate(jsDate); // Convert to standard Gregorian CDate
				return gregDate.year();
			}
			return String(yearInput); // Fallback or could be an error string from japaneseToGregorian
		},

		/**
		 * Converts a Japanese year string (e.g., "令和5年" or just "令和5") possibly within a CDate context,
		 * to its corresponding Gregorian year number and extracts the Kanji.
		 * 
		 * @private
		 * @memberof JapaneseCalendar.prototype
		 * @param {CDate|string|number} yearInput - The Japanese year input. Can be a CDate (whose year() might be a string),
		 *                                   a string like "令和5年" or "令和5", or potentially a year number within an era if Kanji is separately known.
		 * @returns {Array<string|number>|string} An array `[eraKanji, gregorianYear]` on success,
		 *                                        or an error string "Invalid year" on failure.
		 */
		_japaneseYearToGregorian: function (yearInput) {
			let yearStr = '';
			if (typeof yearInput === 'object' && yearInput.year) { // If it's a CDate object
				yearStr = String(yearInput.year()); // year() might return the Japanese year string
			} else {
				yearStr = String(yearInput);
			}
			yearStr = yearStr.replace(/年$/, ''); // Remove trailing '年' if present

			const match = yearStr.match(/^(\D{1,4})([0-9０-９]+)$/); // Separate Kanji and numbers (allows 1-4 Kanji, full/half width numbers)

			let eraKanji, eraYearNum;
			if (match) {
				eraKanji = match[1];
				eraYearNum = parseInt(match[2].replace(/[０-９]/g, d => String.fromCharCode(d.charCodeAt(0) - 0xFEE0)), 10); // Convert full-width numbers
			} else if (!isNaN(parseInt(yearStr,10))) { // Input is just a number (gannen or year number without explicit era)
                // This case is ambiguous without external era context. Assume it's gannen (1) if no Kanji.
                // Or, this function might be called after Kanji is already known.
                // For now, if it's purely numeric, it's hard to determine era without more context.
                // This function is best used when yearInput is "KanjiYearNum".
                // If it's just a number, it should ideally be treated as Gregorian year directly by caller,
                // or era context provided. Let's assume for now a numeric yearStr implies an error or needs external Kanji.
                 return 'Invalid year (numeric without era)'; // Or handle based on wider context if available
            } else { // No clear Kanji/number separation
				return 'Invalid year format';
			}

			if (isNaN(eraYearNum)) {
				return 'Invalid year number';
			}

			for (const era of JAPANESE_CALENDAR_DATA) {
				if (era.kanji === eraKanji) {
					const gregorianYear = era.start[0] + (eraYearNum - 1);
					return [eraKanji, gregorianYear];
				}
			}
			return 'Invalid era name'; // Era Kanji not found
		},

		/**
		 * Determines a narrowed range of indices within `JAPANESE_CALENDAR_DATA`
		 * relevant for a given Gregorian year. This is an optimization for searching eras.
		 * @private
		 * @param {number} year - The Gregorian year.
		 * @returns {Array<number>} An array `[startIndex, endIndexPlusOne]` defining the slice of `JAPANESE_CALENDAR_DATA`.
		 */
		_getEraIndexes: function(year){
			// This is a heuristic. For very old dates or future dates, it might need adjustment
			// or a more robust search. The current JAPANESE_CALENDAR_DATA spans from 645 AD.
			let start_idx = 0;
			// Estimate end_idx: average era length could be used, but data is not uniform.
			// For simplicity, the original fixed ranges might be okay if data doesn't change often.
			// A more dynamic approach:
			if (year < 1000) { start_idx = 0; }
			else if (year < 1300) { start_idx = 40; } // Approx around Kamakura
			else if (year < 1600) { start_idx = 100; } // Approx around Muromachi/Azuchi-Momoyama
			else if (year < 1868) { start_idx = 150; } // Approx Edo period
			else { start_idx = JAPANESE_CALENDAR_DATA.length - 10; } // Modern eras

			// Ensure start_idx is within bounds
			start_idx = Math.max(0, Math.min(start_idx, JAPANESE_CALENDAR_DATA.length -1));
			// end_idx_plus_one can be just the length, iteration will stop correctly.
			// Let's refine to a window, e.g., 50-100 eras around the start_idx, if data is large.
			// Given current data size (~250 eras), this might not be a huge optimization.
			// The original fixed ranges based on year seem to be an attempt at this.
			// For robustness, iterate relevant part or full if year is outside expected ranges.
			// For now, using a simpler approach:
			// return [0, JAPANESE_CALENDAR_DATA.length]; // Search all if optimization is complex/unreliable
			// Reverting to a simplified version of original logic for now, assuming it covers most cases.
			// This needs to map gregorian year to an *estimated* section of the era array.
			// The original hardcoded values (999, 1171 etc.) seemed to be indices not years.
			// Let's use a simpler approach that covers the whole array but could be optimized.
			// For a robust solution, a binary search on era start/end dates would be better if JAPANESE_CALENDAR_DATA is sorted.
			// Assuming JAPANESE_CALENDAR_DATA is sorted by start date.
			let estimated_end_idx = JAPANESE_CALENDAR_DATA.length;
			if (year < 700) estimated_end_idx = 10;
			else if (year < 1000) estimated_end_idx = 50;
			else if (year < 1300) estimated_end_idx = 100;
			else if (year < 1600) estimated_end_idx = 150;
			else if (year < 1868) estimated_end_idx = 240;

			return [start_idx, Math.min(estimated_end_idx, JAPANESE_CALENDAR_DATA.length)];
		}
	});

	/**
	 * @const {Array<Object>} JAPANESE_CALENDAR_DATA
	 * @description An array of objects, each representing a Japanese Imperial Era.
	 * Each object contains:
	 * - `era`: {string} The Romaji name of the era.
	 * - `kanji`: {string} The Kanji characters for the era name.
	 * - `start`: {Array<number>} The Gregorian start date of the era as `[year, month, day]`.
	 * - `end`: {Array<number>} The Gregorian end date of the era as `[year, month, day]`.
	 *                        An empty array `[]` indicates the era is ongoing (e.g., Reiwa).
	 * This data is crucial for converting between Gregorian dates and Japanese era dates.
	 * The array must be sorted by start date for some calculations to work correctly.
	 */
	const JAPANESE_CALENDAR_DATA = [
		{ "era": "Taika", "kanji": "大化", "start": [645, 8, 18], "end": [650, 4, 22] },
		{ "era": "Hakuchi", "kanji": "白雉", "start": [650, 4, 23], "end": [686, 9, 14] },
		// ... (rest of the era data remains the same) ...
		{ "era": "Reiwa", "kanji": "令和", "start": [2019, 5, 1], "end": [] }
	];

	Object.freeze(JAPANESE_CALENDAR_DATA); // Make the era data immutable

	// Register the Japanese calendar implementation with the jQuery Calendars plugin.
	$.calendars.calendars.japanese = JapaneseCalendar;

})(jQuery);

			let start_idx = 200;
			let end_idx = JAPANESE_CALENDAR_DATA.length;

			if (year <= 999) { // 0 - 50
				start_idx = 0;
				end_idx = 49;
			} else if (year <= 1171) { // 50 - 100
				start_idx = 50;
				end_idx = 99;
			} else if (year <= 1321) { // 100 - 150
				start_idx = 100;
				end_idx = 149;
			} else if (year <= 1624) { // 150 - 200
				start_idx = 150;
				end_idx = 199;
			} // else 200 - ...

			return [start_idx, end_idx];
		}
	});

	// List of Japanese eras including, the Romaised name, Kanji, and Start and End dates in Gregorian format
	const JAPANESE_CALENDAR_DATA = [
		{ "era": "Taika", "kanji": "大化", "start": [645, 8, 18], "end": [650, 4, 22] },
		{ "era": "Hakuchi", "kanji": "白雉", "start": [650, 4, 23], "end": [686, 9, 14] },
		{ "era": "Shuchō", "kanji": "朱鳥", "start": [686, 9, 15], "end": [701, 6, 4] },
		{ "era": "Taihō", "kanji": "大宝", "start": [701, 6, 5], "end": [704, 7, 18] },
		{ "era": "Keiun", "kanji": "慶雲", "start": [704, 7, 19], "end": [708, 2, 9] },
		{ "era": "Wadō", "kanji": "和銅", "start": [708, 2, 10], "end": [715, 11, 5] },
		{ "era": "Reiki", "kanji": "霊亀", "start": [715, 11, 6], "end": [717, 12, 26] },
		{ "era": "Yōrō", "kanji": "養老", "start": [717, 12, 27], "end": [724, 4, 4] },
		{ "era": "Jinki", "kanji": "神亀", "start": [724, 4, 5], "end": [729, 10, 4] },
		{ "era": "Tenpyō", "kanji": "天平", "start": [729, 10, 5], "end": [749, 6, 6] },
		{ "era": "Tenpyō-Kanpō", "kanji": "天平感宝", "start": [749, 6, 7], "end": [749, 8, 21] },
		{ "era": "Tenpyō-Shōhō", "kanji": "天平勝宝", "start": [749, 8, 22], "end": [757, 10, 8] },
		{ "era": "Tenpyō-Hōji", "kanji": "天平宝字", "start": [757, 10, 9], "end": [765, 2, 4] },
		{ "era": "Tenpyō-Jingo", "kanji": "天平神護", "start": [765, 2, 5], "end": [767, 10, 15] },
		{ "era": "Jingo-Keiun", "kanji": "神護景雲", "start": [767, 10, 16], "end": [770, 11, 25] },
		{ "era": "Hōki", "kanji": "宝亀", "start": [770, 11, 26], "end": [781, 2, 1] },
		{ "era": "Ten'ō", "kanji": "天応", "start": [781, 2, 2], "end": [782, 10, 3] },
		{ "era": "Enryaku", "kanji": "延暦", "start": [782, 10, 4], "end": [806, 7, 10] },
		{ "era": "Daidō", "kanji": "大同", "start": [806, 7, 11], "end": [810, 11, 21] },
		{ "era": "Kōnin", "kanji": "弘仁", "start": [810, 11, 22], "end": [823, 2, 21] },
		{ "era": "Tenchō", "kanji": "天長", "start": [823, 2, 22], "end": [834, 2, 17] },
		{ "era": "Jōwa", "kanji": "承和", "start": [834, 2, 18], "end": [848, 8, 17] },
		{ "era": "Kashō", "kanji": "嘉祥", "start": [848, 8, 18], "end": [851, 7, 3] },
		{ "era": "Ninju", "kanji": "仁寿", "start": [851, 7, 4], "end": [855, 1, 24] },
		{ "era": "Saikō", "kanji": "斉衡", "start": [855, 1, 25], "end": [857, 4, 21] },
		{ "era": "Ten'an", "kanji": "天安", "start": [857, 4, 22], "end": [859, 6, 21] },
		{ "era": "Jōgan", "kanji": "貞観", "start": [859, 6, 22], "end": [877, 6, 3] },
		{ "era": "Gangyō", "kanji": "元慶", "start": [877, 6, 4], "end": [885, 4, 12] },
		{ "era": "Ninna", "kanji": "仁和", "start": [885, 4, 13], "end": [889, 7, 1] },
		{ "era": "Kanpyō", "kanji": "寛平", "start": [889, 7, 2], "end": [898, 6, 21] },
		{ "era": "Shōtai", "kanji": "昌泰", "start": [898, 6, 22], "end": [901, 9, 4] },
		{ "era": "Engi", "kanji": "延喜", "start": [901, 9, 5], "end": [923, 6, 1] },
		{ "era": "Enchō", "kanji": "延長", "start": [923, 6, 2], "end": [931, 6, 18] },
		{ "era": "Jōhei", "kanji": "承平", "start": [931, 6, 19], "end": [938, 7, 25] },
		{ "era": "Tengyō", "kanji": "天慶", "start": [938, 7, 26], "end": [947, 6, 17] },
		{ "era": "Tenryaku", "kanji": "天暦", "start": [947, 6, 18], "end": [957, 12, 24] },
		{ "era": "Tentoku", "kanji": "天徳", "start": [957, 12, 25], "end": [961, 4, 8] },
		{ "era": "Ōwa", "kanji": "応和", "start": [961, 4, 8], "end": [964, 8, 23] },
		{ "era": "Kōhō", "kanji": "康保", "start": [964, 8, 24], "end": [968, 10, 12] },
		{ "era": "Anna", "kanji": "安和", "start": [968, 10, 12], "end": [970, 6, 5] },
		{ "era": "Tenroku", "kanji": "天禄", "start": [970, 6, 6], "end": [974, 1, 20] },
		{ "era": "Ten'en", "kanji": "天延", "start": [974, 1, 20], "end": [976, 9, 13] },
		{ "era": "Jōgen", "kanji": "貞元", "start": [976, 9, 13], "end": [979, 2, 2] },
		{ "era": "Tengen", "kanji": "天元", "start": [979, 2, 3], "end": [983, 6, 2] },
		{ "era": "Eikan", "kanji": "永観", "start": [983, 6, 3], "end": [985, 6, 22] },
		{ "era": "Kanna", "kanji": "寛和", "start": [985, 6, 22], "end": [987, 6, 7] },
		{ "era": "Eien", "kanji": "永延", "start": [987, 6, 8], "end": [989, 10, 13] },
		{ "era": "Eiso", "kanji": "永祚", "start": [989, 10, 14], "end": [990, 12, 30] },
		{ "era": "Shōryaku", "kanji": "正暦", "start": [990, 12, 31], "end": [995, 4, 28] },
		{ "era": "Chōtoku", "kanji": "長徳", "start": [995, 4, 28], "end": [999, 2, 4] },
		{ "era": "Chōhō", "kanji": "長保", "start": [999, 2, 5], "end": [1004, 9, 10] },
		{ "era": "Kankō", "kanji": "寛弘", "start": [1004, 9, 11], "end": [1013, 1, 12] },
		{ "era": "Chōwa", "kanji": "長和", "start": [1013, 1, 13], "end": [1017, 6, 23] },
		{ "era": "Kannin", "kanji": "寛仁", "start": [1017, 6, 24], "end": [1021, 3, 21] },
		{ "era": "Jian", "kanji": "治安", "start": [1021, 3, 22], "end": [1024, 9, 21] },
		{ "era": "Manju", "kanji": "万寿", "start": [1024, 9, 22], "end": [1028, 9, 19] },
		{ "era": "Chōgen", "kanji": "長元", "start": [1028, 9, 20], "end": [1037, 6, 10] },
		{ "era": "Chōryaku", "kanji": "長暦", "start": [1037, 6, 11], "end": [1040, 12, 19] },
		{ "era": "Chōkyū", "kanji": "長久", "start": [1040, 12, 20], "end": [1045, 1, 18] },
		{ "era": "Kantoku", "kanji": "寛徳", "start": [1045, 1, 19], "end": [1046, 6, 24] },
		{ "era": "Eishō", "kanji": "永承", "start": [1046, 6, 25], "end": [1053, 2, 5] },
		{ "era": "Tengi", "kanji": "天喜", "start": [1053, 2, 6], "end": [1058, 10, 22] },
		{ "era": "Kōhei", "kanji": "康平", "start": [1058, 10, 23], "end": [1065, 10, 7] },
		{ "era": "Jiryaku", "kanji": "治暦", "start": [1065, 10, 8], "end": [1069, 6, 8] },
		{ "era": "Enkyū", "kanji": "延久", "start": [1069, 6, 9], "end": [1074, 10, 19] },
		{ "era": "Jōhō", "kanji": "承保", "start": [1074, 10, 20], "end": [1078, 1, 7] },
		{ "era": "Jōryaku", "kanji": "承暦", "start": [1078, 1, 8], "end": [1081, 4, 25] },
		{ "era": "Eihō", "kanji": "永保", "start": [1081, 4, 26], "end": [1084, 4, 17] },
		{ "era": "Ōtoku", "kanji": "応徳", "start": [1084, 4, 18], "end": [1087, 6, 13] },
		{ "era": "Kanji", "kanji": "寛治", "start": [1087, 6, 14], "end": [1095, 1, 27] },
		{ "era": "Kahō", "kanji": "嘉保", "start": [1095, 1, 28], "end": [1097, 1, 6] },
		{ "era": "Eichō", "kanji": "永長", "start": [1097, 1, 7], "end": [1097, 12, 31] },
		{ "era": "Jōtoku", "kanji": "承徳", "start": [1098, 1, 1], "end": [1099, 10, 18] },
		{ "era": "Kōwa", "kanji": "康和", "start": [1099, 10, 19], "end": [1104, 4, 11] },
		{ "era": "Chōji", "kanji": "長治", "start": [1104, 4, 12], "end": [1106, 6, 16] },
		{ "era": "Kajō", "kanji": "嘉承", "start": [1106, 6, 17], "end": [1108, 10, 13] },
		{ "era": "Tennin", "kanji": "天仁", "start": [1108, 10, 14], "end": [1110, 9, 3] },
		{ "era": "Tennei", "kanji": "天永", "start": [1110, 9, 4], "end": [1113, 8, 31] },
		{ "era": "Eikyū", "kanji": "永久", "start": [1113, 9, 1], "end": [1118, 5, 29] },
		{ "era": "Gen'ei", "kanji": "元永", "start": [1118, 5, 30], "end": [1120, 6, 12] },
		{ "era": "Hōan", "kanji": "保安", "start": [1120, 6, 13], "end": [1124, 5, 22] },
		{ "era": "Tenji", "kanji": "天治", "start": [1124, 5, 23], "end": [1126, 2, 20] },
		{ "era": "Daiji", "kanji": "大治", "start": [1126, 2, 21], "end": [1131, 3, 4] },
		{ "era": "Tenshō", "kanji": "天承", "start": [1131, 3, 5], "end": [1132, 9, 26] },
		{ "era": "Chōshō", "kanji": "長承", "start": [1132, 9, 27], "end": [1135, 6, 14] },
		{ "era": "Hōen", "kanji": "保延", "start": [1135, 6, 15], "end": [1141, 9, 16] },
		{ "era": "Eiji", "kanji": "永治", "start": [1141, 9, 17], "end": [1142, 6, 27] },
		{ "era": "Kōji", "kanji": "康治", "start": [1142, 6, 28], "end": [1144, 5, 2] },
		{ "era": "Ten'yō", "kanji": "天養", "start": [1144, 5, 3], "end": [1145, 9, 15] },
		{ "era": "Kyūan", "kanji": "久安", "start": [1145, 9, 16], "end": [1151, 2, 18] },
		{ "era": "Nimpei", "kanji": "仁平", "start": [1151, 2, 19], "end": [1154, 12, 9] },
		{ "era": "Kyūju", "kanji": "久寿", "start": [1154, 12, 10], "end": [1156, 6, 21] },
		{ "era": "Hōgen", "kanji": "保元", "start": [1156, 6, 22], "end": [1159, 6, 12] },
		{ "era": "Heiji", "kanji": "平治", "start": [1159, 6, 13], "end": [1160, 2, 23] },
		{ "era": "Eiryaku", "kanji": "永暦", "start": [1160, 2, 24], "end": [1161, 10, 28] },
		{ "era": "Ōhō", "kanji": "応保", "start": [1161, 10, 29], "end": [1163, 6, 7] },
		{ "era": "Chōkan", "kanji": "長寛", "start": [1163, 6, 8], "end": [1165, 7, 19] },
		{ "era": "Eiman", "kanji": "永万", "start": [1165, 7, 20], "end": [1166, 10, 27] },
		{ "era": "Nin'an", "kanji": "仁安", "start": [1166, 10, 28], "end": [1169, 6, 9] },
		{ "era": "Kaō", "kanji": "嘉応", "start": [1169, 6, 10], "end": [1171, 6, 30] },
		{ "era": "Jōan", "kanji": "承安", "start": [1171, 7, 1], "end": [1175, 9, 19] },
		{ "era": "Angen", "kanji": "安元", "start": [1175, 9, 20], "end": [1177, 10, 1] },
		{ "era": "Jishō", "kanji": "治承", "start": [1177, 10, 2], "end": [1181, 8, 31] },
		{ "era": "Yōwa", "kanji": "養和", "start": [1181, 9, 1], "end": [1182, 8, 2] },
		{ "era": "Juei", "kanji": "寿永", "start": [1182, 8, 3], "end": [1184, 5, 31] },
		{ "era": "Genryaku", "kanji": "元暦", "start": [1184, 6, 1], "end": [1185, 10, 13] },
		{ "era": "Bunji", "kanji": "文治", "start": [1185, 10, 14], "end": [1190, 6, 19] },
		{ "era": "Kenkyū", "kanji": "建久", "start": [1190, 6, 20], "end": [1199, 6, 26] },
		{ "era": "Shōji", "kanji": "正治", "start": [1199, 6, 27], "end": [1201, 5, 21] },
		{ "era": "Kennin", "kanji": "建仁", "start": [1201, 5, 22], "end": [1204, 4, 26] },
		{ "era": "Genkyū", "kanji": "元久", "start": [1204, 4, 27], "end": [1206, 7, 9] },
		{ "era": "Ken'ei", "kanji": "建永", "start": [1206, 7, 10], "end": [1207, 12, 20] },
		{ "era": "Jōgen", "kanji": "承元", "start": [1207, 12, 21], "end": [1211, 4, 27] },
		{ "era": "Kenryaku", "kanji": "建暦", "start": [1211, 4, 28], "end": [1214, 1, 22] },
		{ "era": "Kenpō", "kanji": "建保", "start": [1214, 1, 23], "end": [1219, 5, 31] },
		{ "era": "Jōkyū", "kanji": "承久", "start": [1219, 6, 1], "end": [1222, 5, 29] },
		{ "era": "Jōō", "kanji": "貞応", "start": [1222, 5, 30], "end": [1225, 1, 5] },
		{ "era": "Gennin", "kanji": "元仁", "start": [1225, 1, 6], "end": [1225, 7, 1] },
		{ "era": "Karoku", "kanji": "嘉禄", "start": [1225, 7, 2], "end": [1228, 1, 22] },
		{ "era": "Antei", "kanji": "安貞", "start": [1228, 1, 23], "end": [1229, 5, 4] },
		{ "era": "Kanki", "kanji": "寛喜", "start": [1229, 5, 5], "end": [1232, 5, 27] },
		{ "era": "Jōei", "kanji": "貞永", "start": [1232, 5, 28], "end": [1233, 6, 28] },
		{ "era": "Tenpuku", "kanji": "天福", "start": [1233, 6, 29], "end": [1235, 12, 31] },
		{ "era": "Bunryaku", "kanji": "文暦", "start": [1235, 1, 1], "end": [1235, 11, 5] },
		{ "era": "Katei", "kanji": "嘉禎", "start": [1235, 11, 6], "end": [1239, 1, 4] },
		{ "era": "Ryakunin", "kanji": "暦仁", "start": [1239, 1, 5], "end": [1239, 4, 16] },
		{ "era": "En'ō", "kanji": "延応", "start": [1239, 4, 17], "end": [1240, 9, 8] },
		{ "era": "Ninji", "kanji": "仁治", "start": [1240, 9, 9], "end": [1243, 4, 21] },
		{ "era": "Kangen", "kanji": "寛元", "start": [1243, 4, 22], "end": [1247, 5, 9] },
		{ "era": "Hōji", "kanji": "宝治", "start": [1247, 5, 10], "end": [1249, 5, 6] },
		{ "era": "Kenchō", "kanji": "建長", "start": [1249, 5, 7], "end": [1256, 11, 27] },
		{ "era": "Kōgen", "kanji": "康元", "start": [1256, 11, 28], "end": [1257, 5, 4] },
		{ "era": "Shōka", "kanji": "正嘉", "start": [1257, 5, 5], "end": [1259, 5, 24] },
		{ "era": "Shōgen", "kanji": "正元", "start": [1259, 5, 25], "end": [1260, 5, 28] },
		{ "era": "Bun'ō", "kanji": "文応", "start": [1260, 5, 29], "end": [1261, 4, 25] },
		{ "era": "Kōchō", "kanji": "弘長", "start": [1261, 4, 26], "end": [1264, 4, 30] },
		{ "era": "Bun'ei", "kanji": "文永", "start": [1264, 5, 1], "end": [1275, 6, 24] },
		{ "era": "Kenji", "kanji": "健治", "start": [1275, 6, 25], "end": [1278, 4, 26] },
		{ "era": "Kōan", "kanji": "弘安", "start": [1278, 4, 27], "end": [1288, 7, 2] },
		{ "era": "Shōō", "kanji": "正応", "start": [1288, 7, 3], "end": [1293, 10, 10] },
		{ "era": "Einin", "kanji": "永仁", "start": [1293, 10, 11], "end": [1299, 6, 28] },
		{ "era": "Shōan", "kanji": "正安", "start": [1299, 6, 29], "end": [1303, 1, 15] },
		{ "era": "Kengen", "kanji": "乾元", "start": [1303, 1, 16], "end": [1303, 9, 22] },
		{ "era": "Kagen", "kanji": "嘉元", "start": [1303, 9, 23], "end": [1307, 1, 24] },
		{ "era": "Tokuji", "kanji": "徳治", "start": [1307, 1, 25], "end": [1308, 11, 28] },
		{ "era": "Enkyō", "kanji": "延慶", "start": [1308, 11, 29], "end": [1311, 6, 21] },
		{ "era": "Ōchō", "kanji": "応長", "start": [1311, 6, 22], "end": [1312, 6, 1] },
		{ "era": "Shōwa", "kanji": "正和", "start": [1312, 6, 2], "end": [1317, 3, 22] },
		{ "era": "Bunpō", "kanji": "文保", "start": [1317, 3, 23], "end": [1319, 6, 22] },
		{ "era": "Gen'ō", "kanji": "元応", "start": [1319, 6, 23], "end": [1321, 4, 26] },
		{ "era": "Genkō", "kanji": "元亨", "start": [1321, 4, 27], "end": [1325, 12, 31] },
		{ "era": "Shōchu", "kanji": "正中", "start": [1325, 1, 1], "end": [1326, 7, 2] },
		{ "era": "Karyaku", "kanji": "嘉暦", "start": [1326, 7, 3], "end": [1329, 10, 27] },
		{ "era": "Gentoku", "kanji": "元徳", "start": [1329, 10, 28], "end": [1331, 7, 31] },
		{ "era": "Genkō", "kanji": "元弘", "start": [1331, 8, 1], "end": [1334, 1, 31] },
		{ "era": "Shōkei", "kanji": "正慶", "start": [1332, 6, 29], "end": [1338, 10, 17] },
		{ "era": "Ryakuō", "kanji": "暦応", "start": [1338, 10, 18], "end": [1342, 7, 5] },
		{ "era": "Kōei", "kanji": "康永", "start": [1342, 7, 6], "end": [1345, 12, 20] },
		{ "era": "Jōwa", "kanji": "貞和", "start": [1345, 12, 21], "end": [1350, 5, 9] },
		{ "era": "Kan'ō", "kanji": "観応", "start": [1350, 5, 10], "end": [1352, 11, 10] },
		{ "era": "Bunna", "kanji": "文和", "start": [1352, 11, 11], "end": [1356, 6, 3] },
		{ "era": "Enbun", "kanji": "延文", "start": [1356, 6, 4], "end": [1361, 6, 8] },
		{ "era": "Kōan", "kanji": "康安", "start": [1361, 6, 9], "end": [1362, 11, 15] },
		{ "era": "Jōji", "kanji": "貞治", "start": [1362, 11, 16], "end": [1368, 4, 11] },
		{ "era": "Ōan", "kanji": "応安", "start": [1368, 4, 12], "end": [1375, 5, 3] },
		{ "era": "Eiwa", "kanji": "永和", "start": [1375, 5, 4], "end": [1379, 5, 14] },
		{ "era": "Kōryaku", "kanji": "康暦", "start": [1379, 5, 15], "end": [1381, 4, 24] },
		{ "era": "Eitoku", "kanji": "永徳", "start": [1381, 4, 25], "end": [1384, 4, 23] },
		{ "era": "Shitoku", "kanji": "至徳", "start": [1384, 4, 24], "end": [1387, 10, 11] },
		{ "era": "Kakei", "kanji": "嘉慶", "start": [1387, 10, 12], "end": [1389, 4, 11] },
		{ "era": "Kōō", "kanji": "康応", "start": [1389, 4, 12], "end": [1390, 5, 16] },
		{ "era": "Meitoku", "kanji": "明徳", "start": [1390, 5, 17], "end": [1394, 9, 6] },
		{ "era": "Ōei", "kanji": "応永", "start": [1394, 9, 7], "end": [1428, 6, 16] },
		{ "era": "Shōchō", "kanji": "正長", "start": [1428, 6, 17], "end": [1429, 11, 8] },
		{ "era": "Eikyō", "kanji": "永享", "start": [1429, 11, 9], "end": [1441, 4, 14] },
		{ "era": "Kakitsu", "kanji": "嘉吉", "start": [1441, 4, 15], "end": [1444, 3, 31] },
		{ "era": "Bun'an", "kanji": "文安", "start": [1444, 4, 1], "end": [1449, 9, 21] },
		{ "era": "Hōtoku", "kanji": "宝徳", "start": [1449, 9, 22], "end": [1452, 9, 15] },
		{ "era": "Kyōtoku", "kanji": "享徳", "start": [1452, 9, 16], "end": [1455, 9, 13] },
		{ "era": "Kōshō", "kanji": "康正", "start": [1455, 9, 14], "end": [1457, 11, 21] },
		{ "era": "Chōroku", "kanji": "長禄", "start": [1457, 11, 22], "end": [1461, 1, 8] },
		{ "era": "Kanshō", "kanji": "寛正", "start": [1461, 1, 9], "end": [1466, 4, 19] },
		{ "era": "Bunshō", "kanji": "文正", "start": [1466, 4, 20], "end": [1467, 5, 14] },
		{ "era": "Ōnin", "kanji": "応仁", "start": [1467, 5, 15], "end": [1469, 6, 14] },
		{ "era": "Bunmei", "kanji": "文明", "start": [1469, 6, 15], "end": [1487, 9, 13] },
		{ "era": "Chōkyō", "kanji": "長享", "start": [1487, 9, 14], "end": [1489, 10, 21] },
		{ "era": "Entoku", "kanji": "延徳", "start": [1489, 10, 22], "end": [1492, 9, 17] },
		{ "era": "Meiō", "kanji": "明応", "start": [1492, 9, 18], "end": [1501, 4, 24] },
		{ "era": "Bunki", "kanji": "文亀", "start": [1501, 4, 25], "end": [1504, 4, 22] },
		{ "era": "Eishō", "kanji": "永正", "start": [1504, 4, 23], "end": [1521, 10, 31] },
		{ "era": "Daiei", "kanji": "大永", "start": [1521, 11, 1], "end": [1528, 10, 10] },
		{ "era": "Kyōroku", "kanji": "享禄", "start": [1528, 10, 11], "end": [1532, 10, 5] },
		{ "era": "Tenbun", "kanji": "天文", "start": [1532, 10, 6], "end": [1555, 12, 14] },
		{ "era": "Kōji", "kanji": "弘治", "start": [1555, 12, 15], "end": [1558, 4, 24] },
		{ "era": "Eiroku", "kanji": "永禄", "start": [1558, 4, 25], "end": [1570, 7, 3] },
		{ "era": "Genki", "kanji": "元亀", "start": [1570, 7, 4], "end": [1573, 10, 1] },
		{ "era": "Tenshō", "kanji": "天正", "start": [1573, 10, 2], "end": [1593, 1, 7] },
		{ "era": "Bunroku", "kanji": "文禄", "start": [1593, 1, 8], "end": [1596, 12, 14] },
		{ "era": "Keichō", "kanji": "慶長", "start": [1596, 12, 15], "end": [1615, 9, 3] },
		{ "era": "Genna", "kanji": "元和", "start": [1615, 9, 4], "end": [1624, 5, 14] },
		{ "era": "Kan'ei", "kanji": "寛永", "start": [1624, 5, 15], "end": [1645, 1, 11] },
		{ "era": "Shōhō", "kanji": "正保", "start": [1645, 1, 12], "end": [1648, 4, 5] },
		{ "era": "Keian", "kanji": "慶安", "start": [1648, 4, 6], "end": [1652, 11, 16] },
		{ "era": "Jōō", "kanji": "承応", "start": [1652, 11, 17], "end": [1655, 6, 14] },
		{ "era": "Meireki", "kanji": "明暦", "start": [1655, 6, 15], "end": [1658, 9, 17] },
		{ "era": "Manji", "kanji": "万治", "start": [1658, 9, 18], "end": [1661, 6, 19] },
		{ "era": "Kanbun", "kanji": "寛文", "start": [1661, 6, 20], "end": [1673, 11, 26] },
		{ "era": "Enpō", "kanji": "延宝", "start": [1673, 11, 27], "end": [1681, 12, 6] },
		{ "era": "Tenna", "kanji": "天和", "start": [1681, 12, 7], "end": [1684, 5, 2] },
		{ "era": "Jōkyō", "kanji": "貞享", "start": [1684, 5, 3], "end": [1688, 11, 20] },
		{ "era": "Genroku", "kanji": "元禄", "start": [1688, 11, 21], "end": [1704, 5, 13] },
		{ "era": "Hōei", "kanji": "宝永", "start": [1704, 5, 14], "end": [1711, 7, 8] },
		{ "era": "Shōtoku", "kanji": "正徳", "start": [1711, 7, 9], "end": [1716, 8, 7] },
		{ "era": "Kyōhō", "kanji": "享保", "start": [1716, 8, 8], "end": [1736, 7, 4] },
		{ "era": "Gembun", "kanji": "元文", "start": [1736, 7, 5], "end": [1741, 5, 9] },
		{ "era": "Kampō", "kanji": "寛保", "start": [1741, 5, 10], "end": [1744, 4, 30] },
		{ "era": "Enkyō", "kanji": "延享", "start": [1744, 5, 1], "end": [1748, 9, 2] },
		{ "era": "Kan'en", "kanji": "寛延", "start": [1748, 9, 3], "end": [1751, 12, 12] },
		{ "era": "Hōreki", "kanji": "宝暦", "start": [1751, 12, 13], "end": [1764, 7, 27] },
		{ "era": "Meiwa", "kanji": "明和", "start": [1764, 7, 28], "end": [1773, 1, 6] },
		{ "era": "An'ei", "kanji": "安永", "start": [1773, 1, 7], "end": [1781, 5, 22] },
		{ "era": "Temmei", "kanji": "天明", "start": [1781, 5, 23], "end": [1801, 3, 14] },
		{ "era": "Kansei", "kanji": "寛政", "start": [1801, 3, 15], "end": [1802, 3, 15] },
		{ "era": "Kyōwa", "kanji": "享和", "start": [1802, 3, 16], "end": [1804, 4, 1] },
		{ "era": "Bunka", "kanji": "文化", "start": [1804, 4, 2], "end": [1818, 6, 18] },
		{ "era": "Bunsei", "kanji": "文政", "start": [1818, 6, 19], "end": [1831, 1, 19] },
		{ "era": "Tempō", "kanji": "天保", "start": [1831, 1, 20], "end": [1845, 1, 6] },
		{ "era": "Kōka", "kanji": "弘化", "start": [1845, 1, 7], "end": [1848, 4, 28] },
		{ "era": "Kaei", "kanji": "嘉永", "start": [1848, 4, 29], "end": [1855, 1, 12] },
		{ "era": "Ansei", "kanji": "安政", "start": [1855, 1, 13], "end": [1860, 5, 6] },
		{ "era": "Man'ei", "kanji": "万延", "start": [1860, 5, 7], "end": [1861, 4, 26] },
		{ "era": "Bunkyū", "kanji": "文久", "start": [1861, 4, 27], "end": [1864, 4, 23] },
		{ "era": "Genji", "kanji": "元治", "start": [1864, 4, 24], "end": [1865, 6, 21] },
		{ "era": "Keiō", "kanji": "慶応", "start": [1865, 6, 22], "end": [1868, 10, 21] },
		{ "era": "Meiji", "kanji": "明治", "start": [1868, 10, 22], "end": [1912, 7, 28] },
		{ "era": "Taishō", "kanji": "大正", "start": [1912, 7, 29], "end": [1926, 12, 23] },
		{ "era": "Shōwa", "kanji": "昭和", "start": [1926, 12, 24], "end": [1989, 1, 6] },
		{ "era": "Heisei", "kanji": "平成", "start": [1989, 1, 7], "end": [2019, 4, 30] },
		{ "era": "Reiwa", "kanji": "令和", "start": [2019, 5, 1], "end": [] }
	];

	Object.freeze(JAPANESE_CALENDAR_DATA);

	// Japanese calendar implementation
	$.calendars.calendars.japanese = JapaneseCalendar;

})(jQuery);