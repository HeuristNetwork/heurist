/**
 * @file jquery.calendars.japanese.js
 * @brief Implements the Traditional Japanese Calendar system for jQuery Calendars plugin.
 * @fileOverview This script provides an implementation of the Traditional Japanese Calendar system.
 * It extends the `$.calendars.baseCalendar` provided by the jQuery Calendars plugin (by Keith Wood).
 * The implementation includes definitions for Japanese eras (Nengō), month names, day names,
 * date formatting, and logic for handling leap years and conversions between Japanese calendar dates
 * and Julian Day Numbers (JD) / JavaScript Dates. It relies on a predefined dataset of Japanese eras
 * (`JAPANESE_CALENDAR_DATA`) for its calculations and formatting.
 * It is designed to be used with the jQuery Calendars plugin.
 * @package Heurist academic knowledge management system
 * @subpackage hclient\core
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Heurist Team
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @note Based on the Gregorian calendar implementation by Keith Wood.
 * @since 4.0
 */

/* 
Implementation of the Traditional Japanese Calendar, 
	based on the Gregorian calendar implemented by Keith Wood (wood.keith{at}optusnet.com.au)
*/

(function ($) { // Hide scope, no $ conflict

	/**
     * Constructor for the Japanese calendar system.
     * Initializes a new Japanese calendar instance, potentially with language-specific settings.
     * This calendar handles traditional Japanese eras (Nengō) and date calculations.
     *
     * @constructor JapaneseCalendar
     * @param {string} [language=''] - The language code for localization (e.g., 'ja').
     *                                 Defaults to empty string for base regional settings.
     */
	function JapaneseCalendar(language) {
		this.local = this.regional[language || ''] || this.regional[''];
	}

	JapaneseCalendar.prototype = new $.calendars.baseCalendar;

	$.extend(JapaneseCalendar.prototype, {

		name: 'Japanese', // The calendar name
		jdEpoch: 1721425.5, // Gregorian's Epoch date in Julian notitation

		daysPerMonth: [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31], // Days per month in a common year

		hasYearZero: false, // Calendar has no year zero
		minMonth: 1,
		minDay: 1,

		firstMonth: 1,

		regional: { // translations
			'': {
				name: 'Japanese', // The calendar name
				epochs: ['', ''],
				monthNames: ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月'],
				monthNamesShort: ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'],
				dayNames: ['日曜日', '月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日'],
				dayNamesShort: ['日', '月', '火', '水', '木', '金', '土'],
				dayNamesMin: ['日', '月', '火', '水', '木', '金', '土'],
				dateFormat: 'yyyy/mm/dd', // default date formatting
				firstDay: 0, // list Sunday as the first day of the week
				isRTL: false,
				showMonthAfterYear: true,
				yearSuffix: '年'
			}
		},

		/* Determine whether this date is in a leap year.
		   @param  year  (CDate) the date to examine or
						 (number) the year to examine
		   @return  (boolean) true if this is a leap year, false if not
		   @throws  error if an invalid year or a different calendar used */
		leapYear: function (year) {
			let date = this._validate(year, this.minMonth, this.minDay,
				$.calendars.local.invalidYear || $.calendars.regional[''].invalidYear);
			year = date.year() + (date.year() < 0 ? 1 : 0); // No year zero
			return year % 4 == 0 && (year % 100 != 0 || year % 400 == 0);
		},

		/**
		 * Determine which week of the year the date belongs to
		 * 
		 * @param {CDate|number} year - calendar date or the date's year
		 * @param {number} month - the date's month
		 * @param {number} day - the date's day
		 * @returns {number} week of the year
		 */
		weekOfYear: function (year, month, day) {
			// Find Thursday of this week starting on Monday
			let checkDate = this.newDate(year, month, day);
			checkDate.add(4 - (checkDate.dayOfWeek() || 7), 'd');
			return Math.floor((checkDate.dayOfYear() - 1) / 7) + 1;
		},

		/**
		 * Retrieve the amount of days within the given month
		 * 
		 * @param {CDate|number} year - calendar date or the date's year
		 * @param {number} month - the date's month
		 * @returns {number} count of days within the month
		 */
		daysInMonth: function (year, month) {
			let date = this._validate(year, month, this.minDay,
				$.calendars.local.invalidMonth || $.calendars.regional[''].invalidMonth);
			return this.daysPerMonth[date.month() - 1] +
				(date.month() == 2 && this.leapYear(date.year()) ? 1 : 0);
		},

		/**
		 * Determine whether the given date is a week day
		 * 
		 * @param {CDate|number} year - calendar date or the date's year
		 * @param {number} month - the date's month
		 * @param {number} day - the date's day
		 * @returns {boolean} whether or not the date is a week day
		 */
		weekDay: function (year, month, day) {
			return (this.dayOfWeek(year, month, day) || 7) < 6;
		},

		/**
		 * Generate new calendar date
		 * 
		 * @param {CDate|number|string} [year] - calendar date object, the date's year, or a Japanese date string. If null, today's date is returned.
		 * @param {number} [month] - the date's month
		 * @param {number} [day] - the date's day
		 * @returns {CDate} new calendar date
		 */
		newDate: function (year, month, day) {

			if (year == null) {
				return this.today();
			}

			if (year.year) { // If year is a CDate object
				this._validate(year, month, day, // month and day might be undefined here if year is CDate
					$.calendars.local.invalidDate || $.calendars.regional[''].invalidDate);
				day = year.day();
				month = year.month();
				year = year.year();
			}

			if (typeof year === 'string' && year.indexOf('年') !== -1) {
				[, year] = this._japaneseYearToGregorian(year); // Convert Japanese year string to Gregorian year number
			}

			return new $.calendars.cdate(this, year, month, day);
		},

		/**
		 * 
		 * @param {CDate|number} year - calendar date or the date's year
		 * @param {number} month - the date's month
		 * @param {number} day - the date's day
		 * @returns {boolean} whether the date is valid
		 */
		isValid: function (year, month, day) {
            // Note: _validateFromGregorian is an internal function that returns [year, month, day]
            // We only care about the year part for validity check here.
			let validatedYear;
			[validatedYear, ,] = this._validateFromGregorian(year, month, day, false);

			return Number.isInteger(+validatedYear) && validatedYear !== 0;
		},

		// Internal helper function - JSDoc intentionally omitted
		_validateFromGregorian: function (year, month, day, include_kanji = true) {

			if (year.year) { // If CDate object
				day = year.day();
				month = year.month();
				year = year.year();
			}

			if(!Number.isInteger(+month) || month <= 0){
				month = 1;
			}
			if(!Number.isInteger(+day) || day <= 0){
				day = 1;
			}

			let start_idx = 0;
			let end_idx = JAPANESE_CALENDAR_DATA.length -1; // Corrected end_idx initialization

			[start_idx, end_idx] = this._getEraIndexes(year);

			if (year == 999 || year == 1171 || year == 1321 || year == 1624) { // at intersection cutoff
				let early_end = JAPANESE_CALENDAR_DATA[end_idx]; // This logic seems problematic if end_idx is last element
                let late_start_idx = end_idx + 1;
                if (late_start_idx < JAPANESE_CALENDAR_DATA.length) {
                    let late_start = JAPANESE_CALENDAR_DATA[late_start_idx];
                    if (month > early_end['end'][1] || (month == early_end['end'][1] && day > early_end['end'][2])) { // within next era
                        year = (year - late_start['start'][0]) + 1;
                        year = include_kanji ? `${late_start['kanji']}${year}` : year;
                    } else { // within current era
                        year = (year - early_end['start'][0]) + 1;
                        year = include_kanji ? `${early_end['kanji']}${year}` : year;
                    }
                } else { // At the very last era, no "next" era to check against
                    year = (year - early_end['start'][0]) + 1;
					year = include_kanji ? `${early_end['kanji']}${year}` : year;
                }
			} else {
				let found = false;
				for (let i = start_idx; i <= end_idx; i++) { // Iterate up to and including end_idx
					if (i >= JAPANESE_CALENDAR_DATA.length) break; // Boundary check
					let cur_details = JAPANESE_CALENDAR_DATA[i];
					let cur_start = cur_details['start'];
					let cur_end = cur_details['end'];

                    // Check if year is within cur_details era
                    if (year < cur_start[0] || (year == cur_start[0] && (month < cur_start[1] || (month == cur_start[1] && day < cur_start[2])))) {
                        continue; // Before this era
                    }
                    if (cur_end.length > 0 && (year > cur_end[0] || (year == cur_end[0] && (month > cur_end[1] || (month == cur_end[1] && day > cur_end[2]))))) {
                        continue; // After this era
                    }

					year = (year - cur_start[0]) + 1;
					year = include_kanji ? `${cur_details['kanji']}${year}` : year;
					found = true;
					break;
				}
				if (!found) {
					year = 0; // Indicates invalid or unmappable year
				}
			}
			return [year, month, day];
		},

		/**
		 * Convert value to Gregorian and then find Julian calendar
		 * 
		 * @param {CDate|number|string} yearOrDate - calendar date, the date's year, or Japanese date string
		 * @param {number} [month] - the date's month
		 * @param {number} [day] - the date's day
		 * @returns {number} Julian calendar value
		 */
		toJD: function (yearOrDate, month, day) {
			let gregorian_calendar = $.calendars.instance();
			this._validateLevel++; // Suppress internal validation during conversion
			let checkedYear = this._checkYear(yearOrDate, month, day); // Converts Japanese year to Gregorian if needed
			this._validateLevel--;

            let year, m, d;
            if (checkedYear && checkedYear.year) { // If CDate object or successfully converted
                year = checkedYear.year();
                m = checkedYear.month();
                d = checkedYear.day();
            } else if (typeof checkedYear === 'number') { // If _checkYear returned a Gregorian year number
                year = checkedYear;
                m = month;
                d = day;
            } else { // Should not happen if validation is correct
                 throw $.calendars.local.invalidDate || $.calendars.regional[''].invalidDate;
            }

			gregorian_calendar._validateLevel++;
			let gDate = gregorian_calendar.newDate(year, m, d);
			gregorian_calendar._validateLevel--;

			return gregorian_calendar.toJD(gDate);
		},

		/**
		 * Convert Julian date into this calendar date
		 * 
		 * @param {number} jd - Julian Day Number
		 * @returns {CDate} calendar date in Japanese calendar
		 */
		fromJD: function (jd) {
			let gregorian_calendar = $.calendars.instance();
			let date = gregorian_calendar.fromJD(jd);
			return this.newDate(date.year(), date.month(), date.day());
		},

		/**
		 * Convert value to Gregorian and then into a JavaScript date stamp
		 * 
		 * @param {CDate|number|string} yearOrDate - calendar date, the date's year, or Japanese date string
		 * @param {number} [month] - the date's month
		 * @param {number} [day] - the date's day
		 * @returns {Date} JS Date object
		 */
		toJSDate: function (yearOrDate, month, day) {
			let gregorian_calendar = $.calendars.instance();
			this._validateLevel++;
			let checkedYear = this._checkYear(yearOrDate, month, day);
			this._validateLevel--;

            let year, m, d;
            if (checkedYear && checkedYear.year) {
                year = checkedYear.year();
                m = checkedYear.month();
                d = checkedYear.day();
            } else if (typeof checkedYear === 'number') {
                year = checkedYear;
                m = month;
                d = day;
            } else {
                 throw $.calendars.local.invalidDate || $.calendars.regional[''].invalidDate;
            }

			gregorian_calendar._validateLevel++;
			let gDate = gregorian_calendar.newDate(year, m, d);
			gregorian_calendar._validateLevel--;

			return gregorian_calendar.toJSDate(gDate);
		},

		/**
		 * Convert JavaScript date stamp into this calendar date
		 * 
		 * @param {Date} jsd - JavaScript Date object
		 * @returns {CDate} calendar date in Japanese calendar
		 */
		fromJSDate: function (jsd) {
			let gregorian_calendar = $.calendars.instance();
			let date = gregorian_calendar.fromJSDate(jsd);
			return this.newDate(date.year(), date.month(), date.day());
		},

		/**
		 * Get era index from given Japanese date string
		 * 
		 * @param {string} str - japanese date string (e.g., "令和6年3月15日")
		 * @return {number} era index in JAPANESE_CALENDAR_DATA, or -1 if not found/invalid.
		 */
		getEraFromJapaneseStr: function(str){
			if(typeof str !== 'string' || str.indexOf('年') === -1){
				return -1;
			}
			let yearPart;
			[yearPart, ] = str.split('年'); // Get the part before "年"
			let eraKanji = yearPart.replace(/[0-9０-９]+/, ''); // Remove numbers (full-width and half-width)

			if(eraKanji === ''){
				return -1; // No Kanji found
			}

			for(let i = 0; i < JAPANESE_CALENDAR_DATA.length; i++){
				if(JAPANESE_CALENDAR_DATA[i]['kanji'] === eraKanji){
					return i;
				}
			}
            return -1; // Kanji not found
		},

		/**
		 * Get era index from given Gregorian date components.
		 * 
		 * @param {CDate|number} yearOrDate - Gregorian CDate object or year number.
		 * @param {number} [month] - Gregorian month (1-12).
		 * @param {number} [day] - Gregorian day (1-31).
		 * @return {number} era index in JAPANESE_CALENDAR_DATA, or -1 if not found/invalid.
		 */
		getEraFromGregorian: function(yearOrDate, month, day){
            let year;
			if(yearOrDate.year){ // CDate object
				day = yearOrDate.day();
				month = yearOrDate.month();
				year = yearOrDate.year();
			} else {
                year = yearOrDate;
            }

			if(!Number.isInteger(+month) || month <= 0 || month > 12){
				month = 1; // Default or error
			}
			if(!Number.isInteger(+day) || day <= 0 || day > 31){
				day = 1; // Default or error
			}

			let [start_idx, end_idx] = this._getEraIndexes(year);

            for (let i = start_idx; i <= end_idx; i++) {
                if (i >= JAPANESE_CALENDAR_DATA.length) break;
                const era = JAPANESE_CALENDAR_DATA[i];
                const era_start = era.start;
                const era_end = era.end;

                // Check if date is within era_start
                if (year < era_start[0] || (year === era_start[0] && (month < era_start[1] || (month === era_start[1] && day < era_start[2])))) {
                    continue; // Date is before this era
                }
                // Check if date is within era_end (if era has an end)
                if (era_end.length > 0 && (year > era_end[0] || (year === era_end[0] && (month > era_end[1] || (month === era_end[1] && day > era_end[2]))))) {
                    continue; // Date is after this era
                }
                return i; // Found the era
            }
			return -1; // Not found
		},

		/**
		 * Translate Gregorian date into the Japanese calendar string (e.g., 令和6年3月15日).
		 * 
		 * @param {CDate|number} yearOrDate - Gregorian CDate object or year number.
		 * @param {number} [month] - Gregorian month (1-12).
		 * @param {number} [day] - Gregorian day (1-31).
		 * @returns {string} Formatted Japanese date string, or empty string if invalid.
		 */
		gregorianToJapaneseStr: function (yearOrDate, month, day) {
            let japaneseYear, japaneseMonth, japaneseDay;
			[japaneseYear, japaneseMonth, japaneseDay] = this._validateFromGregorian(yearOrDate, month, day, true);
            if (japaneseYear === 0) return ''; // Invalid conversion
			return `${japaneseYear}${this.local.yearSuffix}${japaneseMonth}${this.local.monthNamesShort[japaneseMonth-1].slice(-1)}${japaneseDay}${this.local.dayNamesMin[0].slice(-1)}`; // Assuming 日 is common suffix for day from dayNamesMin
		},

		/**
		 * Translate from Japanese date string to Gregorian date components.
		 * Handles date strings in the format: Era_KanjiEra_Year年month月day日 (e.g., "令和6年3月15日").
		 * 
		 * @param {string|CDate} yearOrJString - Japanese date string or CDate object in Japanese calendar.
		 * @param {number} [month] - Month, if yearOrJString is a year number.
		 * @param {number} [day] - Day, if yearOrJString is a year number.
		 * @returns {CDate|string} The Gregorian CDate object, or an error string if invalid.
		 */
		japaneseToGregorian: function (yearOrJString, month, day) {
            let eraKanji, eraYear;

			if (yearOrJString && yearOrJString.year) { // If CDate object
                // This case implies it's already a CDate, potentially in Japanese format.
                // We need its components to convert.
                let jpYearStr = yearOrJString.year().toString(); // This might be "令和6"
                if (isNaN(parseInt(jpYearStr.slice(-1)))) { // Contains Kanji
                    [eraKanji, eraYear] = this._japaneseYearToGregorian(jpYearStr);
                } else { // Assume it's already Gregorian if it was a CDate not in full Japanese string format
                    return this.newDate(yearOrJString.year(), yearOrJString.month(), yearOrJString.day());
                }
				month = yearOrJString.month();
				day = yearOrJString.day();
			} else if (typeof yearOrJString === 'string' && yearOrJString.indexOf('年') !== -1) {
                const parts = yearOrJString.match(/(\D{1,4})(\d+)[年](\d+)[月](\d+)[日]/);
                if (parts && parts.length === 5) {
                    eraKanji = parts[1];
                    eraYear = parseInt(parts[2], 10);
                    month = parseInt(parts[3], 10);
                    day = parseInt(parts[4], 10);

                    let gregorianYear = 0;
                    for (const era of JAPANESE_CALENDAR_DATA) {
                        if (era.kanji === eraKanji) {
                            gregorianYear = era.start[0] + eraYear - 1;
                            break;
                        }
                    }
                    if (gregorianYear === 0) return 'Invalid era Kanji';
                    yearOrJString = gregorianYear; // Now yearOrJString is the Gregorian year
                } else {
                    return 'Invalid Japanese date string format';
                }
			} else if (typeof yearOrJString === 'number' && typeof month === 'number' && typeof day === 'number') {
                // This implies Gregorian components were passed directly, but the function name suggests Japanese input
                // This path might be ambiguous or for internal use by other methods.
                // Assuming yearOrJString is Gregorian year here.
            } else {
				return 'Invalid input';
			}
            // At this point, yearOrJString should be a Gregorian year number
			if (isNaN(yearOrJString) || yearOrJString <= 0) return 'Invalid year component';
			if (isNaN(month) || month <= 0 || month > 12) return 'Invalid month component';
			if (isNaN(day) || day <= 0 || day > 31) return 'Invalid day component';

			return this.newDate(yearOrJString, month, day); // Creates a CDate in current (Japanese) calendar context from Gregorian parts
		},

		/**
		 * Convert Japanese date string into a Gregorian date string (YYYY-MM-DD).
		 * 
		 * @param {string} dateStr - Japanese date string (e.g., "令和6年3月15日").
		 * @returns {string} Gregorian date string (YYYY-MM-DD) or empty string if invalid.
		 */
		japaneseToGregorianStr: function (dateStr) {
			let date = this.japaneseToGregorian(dateStr);
			if (typeof date === 'string' || !date.year) { // Check if conversion failed
				return '';
			}
			// The date object from japaneseToGregorian is a CDate in JapaneseCalendar context,
            // but its internal year, month, day are already Gregorian.
			return `${date.year()}-${String(date.month()).padStart(2, '0')}-${String(date.day()).padStart(2, '0')}`;
		},

		/**
		 * Get array of Eras for the Japanese calendar.
		 * 
		 * @param {boolean} [kanji_only=false] - Whether to return only the era's kanjis.
         * @returns {string[]} An array of era names (e.g., "Reiwa (令和)") or just Kanjis (e.g., "令和").
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
		 * Return gregorian date as a Japanese date string.
		 * This overrides the base calendar's formatDate to always output in Japanese era format.
		 * 
		 * @param {string} format - Format string (currently ignored, always uses standard Japanese format).
		 * @param {CDate} date - Calendar date to format.
		 * @param {object} [settings] - Format settings (currently ignored).
		 * @returns {string} The formatted date string in Japanese era style.
		 */
		formatDate: function (format, date, settings) {
			// Ignores 'format' and 'settings', always returns in Japanese standard format
			return this.gregorianToJapaneseStr(date.year(), date.month(), date.day());
		},

		/**
		 * Retrieve the start and end dates for the era.
		 * 
		 * @param {number} era_index - Array index of the japanese era in JAPANESE_CALENDAR_DATA.
		 * @returns {Array<Array<number|undefined>>} `[[startYear, startMonth, startDay], [endYear, endMonth, endDay]]`.
         *                                       End date components can be undefined if the era is ongoing.
		 */
		getEraLimits: function (era_index) {
			if (era_index < 0 || era_index >= JAPANESE_CALENDAR_DATA.length) {
				return [[], []]; // Return empty arrays for invalid index
			}
			let era = JAPANESE_CALENDAR_DATA[era_index];
			return [era['start'], era['end']];
		},

		// Internal helper function - JSDoc intentionally omitted
		_checkYear: function (year, month, day) {
			if (!Number.isInteger(+year) || (year.year !== undefined && !Number.isInteger(year.year()))) {
				year = this.japaneseToGregorian(year, month, day);
			}
			if (typeof year === 'string') { // japaneseToGregorian might return error string
				return null; // Indicate error
			}
			return year; // Should be CDate or Gregorian year number
		},

		// Internal helper function - JSDoc intentionally omitted
		_japaneseYearToGregorian: function (yearStrOrNum) {
            let yearVal = yearStrOrNum;
			if (yearVal && yearVal.year) { // If CDate object
				yearVal = yearVal.year(); // Get the year part, which might be like "令和6"
			}

            let kanji = ('' + yearVal).match(/\D+/); // Match non-digit characters for Kanji
            let eraYearNum;

			if (kanji && kanji[0] !== '') {
				kanji = kanji[0];
				eraYearNum = parseInt(('' + yearVal).replace(kanji, ''), 10);
			} else if (Number.isInteger(+yearVal)) { // If it's purely numeric, assume it's a Gregorian year already
                // This case needs careful handling depending on context.
                // For now, assume it means no conversion is needed from Japanese era.
                return ['', parseInt(yearVal, 10)]; // No Kanji, return number as is
            } else {
                return ['', NaN]; // Invalid format
            }

			for (const era of JAPANESE_CALENDAR_DATA) {
				if (era['kanji'] === kanji) {
					return [kanji, era['start'][0] + (eraYearNum - 1)];
				}
			}
			return [kanji, NaN]; // Kanji not found or invalid year
		},

        // Internal helper function - JSDoc intentionally omitted
		_getEraIndexes: function(year){
			// Simplified: full scan is safer for sparse/irregular data like eras.
            // The original bisection logic might be error-prone with complex era date ranges.
            // For performance, this could be optimized if JAPANESE_CALENDAR_DATA is guaranteed sorted
            // and eras don't have unusual overlaps/gaps not handled by simple bisection.
            // Given the relatively small number of eras (hundreds), a linear scan during specific operations
            // might be acceptable. For now, returning full range for broader search in calling functions.
			return [0, JAPANESE_CALENDAR_DATA.length -1];
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