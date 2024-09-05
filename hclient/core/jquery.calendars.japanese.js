/* 
Implementation of the Traditional Japanese Calendar, 
	based on the Gregorian calendar implemented by Keith Wood (wood.keith{at}optusnet.com.au)
*/

(function ($) { // Hide scope, no $ conflict

	/**
	 * 
	 * @param {string} language the language code for localisation (optional) 
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
		 * @param {CDate} year - calendar date
		 * @param {int} year - the date's year 
		 * @param {int} month - the date's month
		 * @param {int} day - the date's day
		 * @returns {int} week of the year
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
		 * @param {CDate} year - calendar date
		 * @param {int} year - the date's year 
		 * @param {int} month - the date's month
		 * @returns {int} count of days within the month
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
		 * @param {CDate} year - calendar date
		 * @param {int} year - the date's year 
		 * @param {int} month - the date's month
		 * @param {int} day - the date's day
		 * @returns {boolean} whether or not the date is a week day
		 */
		weekDay: function (year, month, day) {
			return (this.dayOfWeek(year, month, day) || 7) < 6;
		},

		/**
		 * Generate new calendar date
		 * 
		 * @param {CDate} year - calendar date
		 * @param {int} year - the date's year 
		 * @param {int} month - the date's month
		 * @param {int} day - the date's day
		 * @returns {CDate} new calendar date
		 */
		newDate: function (year, month, day) {

			if (year == null) {
				return this.today();
			}

			if (year.year) {
				this._validate(year, month, day,
					$.calendars.local.invalidDate || $.calendars.regional[''].invalidDate);
				day = year.day();
				month = year.month();
				year = year.year();
			}

			if (typeof year === 'string' && year.indexOf('年') !== -1) {
				[, year] = this._japaneseYearToGregorian(year);
			}

			return new $.calendars.cdate(this, year, month, day);
		},

		/**
		 * 
		 * @param {CDate} year - calendar date
		 * @param {int} year - the date's year 
		 * @param {int} month - the date's month
		 * @param {int} day - the date's day
		 * @returns {boolean} whether the date is valid
		 */
		isValid: function (year, month, day) {

			[year, ,] = this._validateFromGregorian(year, month, day, false);

			return Number.isInteger(+year) && year !== 0;
		},

		/**
		 * Translate date object to string in the Japanese calendar format
		 * 
		 * @param {CDate} year - calendar date
		 * @param {int} year - the date's year 
		 * @param {int} month - the date's month
		 * @param {int} day - the date's day
		 * @param {boolean} include_kanji - whether to include the era's kanji at the string's start
		 * @returns {string} return date as a string
		 */
		_validateFromGregorian: function (year, month, day, include_kanji = true) {

			if (year.year) {
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
			let end_idx = 49;

			[start_idx, end_idx] = this._getEraIndexes(year);

			if (year == 999 || year == 1171 || year == 1321 || year == 1624) { // at intersection cutoff

				let early_end = JAPANESE_CALENDAR_DATA[end_idx];
				let late_start = JAPANESE_CALENDAR_DATA[end_idx + 1];

				if (month > early_end['end'][1] || day > early_end['end'][2]) { // within next era
					year = (year - late_start['start'][0]) + 1;
					year = include_kanji ? `${late_start['kanji']}${year}` : year;
				} else { // within current era
					year = (year - early_end['start'][0]) + 1;
					year = include_kanji ? `${early_end['kanji']} ${year}` : year;
				}
			} else {

				let found = false;

				for (let i = start_idx; i < end_idx; i++) {

					let cur_details = JAPANESE_CALENDAR_DATA[i];
					let cur_start = cur_details['start'];
					let cur_end = cur_details['end'];

					if (year > cur_end[0] ||
						(year == cur_end[0] && (month > cur_end[1] ||
							(month == cur_end[1] && day > cur_end[2])))) {

						continue;
					}

					year = (year - cur_start[0]) + 1;
					year = include_kanji ? `${cur_details['kanji']}${year}` : year;
					found = true;
					break;
				}

				if (!found) {
					year = 0;
				}
			}

			return [year, month, day];
		},

		/**
		 * Convert value to Gregorian and then find Julian calendar
		 * 
		 * @param {CDate} year - calendar date
		 * @param {int} year - the date's year 
		 * @param {int} month - the date's month
		 * @param {int} day - the date's day
		 * @returns {number} Julian calendar value
		 */
		toJD: function (year, month, day) {

			let gregorian_calendar = $.calendars.instance();

			this._validateLevel++;
			year = this._checkYear(year, month, day);
			this._validateLevel--;

			if (year.year) { // Change to Gregorian

				gregorian_calendar._validateLevel++;
				year = gregorian_calendar.newDate(year.year(), year.month(), year.day());
				gregorian_calendar._validateLevel--;
			}

			let jd = gregorian_calendar.toJD(year, month, day);

			return jd;
		},

		/**
		 * Convert Julian date into this calendar date
		 * 
		 * @param {number} jd 
		 * @returns {CDate} calendar date
		 */
		fromJD: function (jd) {

			let gregorian_calendar = $.calendars.instance();
			let date = gregorian_calendar.fromJD(jd);

			return this.newDate(date.year(), date.month(), date.day());
		},

		/**
		 * Convert value to Gregorian and then into a JavaScript date stamp
		 * 
		 * @param {CDate} year - calendar date
		 * @param {int} year - the date's year 
		 * @param {int} month - the date's month
		 * @param {int} day - the date's day
		 * @returns {string} JS date string
		 */
		toJSDate: function (year, month, day) {

			let gregorian_calendar = $.calendars.instance();

			this._validateLevel++;
			year = this._checkYear(year, month, day);
			this._validateLevel--;

			if (year.year) { // Change to Gregorian

				gregorian_calendar._validateLevel++;
				year = gregorian_calendar.newDate(year.year(), year.month(), year.day());
				gregorian_calendar._validateLevel--;
			}

			return gregorian_calendar.toJSDate(year, month, day);
		},

		/**
		 * Convert JavaScript date stamp into this calendar date
		 * 
		 * @param {string} jsd 
		 * @returns {CDate} calendar date
		 */
		fromJSDate: function (jsd) {

			let gregorian_calendar = $.calendars.instance();
			let date = gregorian_calendar.fromJSDate(jsd);

			return this.newDate(date.year(), date.month(), date.day());
		},

		/**
		 * Get era index from given Japanese date string
		 * 
		 * @param {string} str - japanese date
		 * @return {int} era index
		 */
		getEraFromJapaneseStr: function(str){

			if(str.indexOf('年') === -1){
				return -1;
			}
			let year;
			[year, ] = str.split('年');
			let era = year.replace(/[0-9]+/, '');

			if(era === ''){
				return -1;
			}

			for(let i = 0; i < JAPANESE_CALENDAR_DATA.length; i++){

				if(JAPANESE_CALENDAR_DATA[i]['kanji'] !== era){
					continue;
				}

				return i;
			}
		},

		/**
		 * Get era index from given Japanese date string
		 * 
		 * @param {CDate} year - calendar date
		 * @param {int} year - date's year
		 * @param {int} month - date's month
		 * @param {int} day - date's day
		 * @return {int} era index
		 */
		getEraFromGregorian: function(year, month, day){

			if(year.year){
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

			let index = 0;
			let start_idx = 0;
			let end_idx = 49;

			[start_idx, end_idx] = this._getEraIndexes(year);

			if (year == 999 || year == 1171 || year == 1321 || year == 1624) { // at intersection cutoff

				let early_end = JAPANESE_CALENDAR_DATA[end_idx];

				index = month > early_end['end'][1] || day > early_end['end'][2] ? (end_idx + 1) : end_idx;

			} else {

				for (let i = start_idx; i < end_idx; i++) {

					let cur_details = JAPANESE_CALENDAR_DATA[i];
					let cur_end = cur_details['end'];

					if (year > cur_end[0] ||
						(year == cur_end[0] && (month > cur_end[1] ||
							(month == cur_end[1] && day > cur_end[2])))) {

						continue;
					}

					return i;
				}
			}

			return -1;
		},

		/**
		 * Translate Gregorian date into the Japanese calendar string
		 * 
		 * @param {int} year - date's year
		 * @param {int} month - date's month
		 * @param {int} day - date's day 
		 * @returns {string} formatted string - Era_Kanji Era_Year年 month月 day日 [no spaces]
		 */
		gregorianToJapaneseStr: function (year, month, day) {

			[year, month, day] = this._validateFromGregorian(year, month, day);

			return `${year}年${month}月${day}日`;
		},

		/**
		 * Translate from Japanese date string to Gregorian date
		 *  Handles date strings in the format: Era_Kanji Era_Year年 month月 day日 [no spaces]
		 * 
		 * @param {string} year - string in format of Era_Kanji Era_Year年 month月 day日 [no spaces] format
		 * @param {CDate} year - Japanese date object
		 * @param {int} year - date's year
		 * @param {int} month - date's month
		 * @param {int} day - date's day 
		 * @returns {CDate} the gregorian date 
		 */
		japaneseToGregorian: function (year, month, day) {

			if (year.year) {
				day = year.day();
				month = year.month();
				year = year.year();
			} else if (typeof year === 'string' && year.indexOf('年') !== -1 && year.match(/\D{2,4}\d*年\d*月\d*日/)) { // Ey年m月d日

				let matches = [...year.matchAll(/(\D{2,4}\d*年)(\d*月)(\d*日)/g)];

				if (matches.length == 1 && matches[0].length == 4) {
					year = matches[0][1];
					month = matches[0][2];
					day = matches[0][3];
				}
			} else if (!year && !month && !day) {
				return '';
			}

			// Get Era's Kanji - for retrieving the gregorian year
			let kanji = null;
			[kanji, year] = this._japaneseYearToGregorian(year);

			if (isNaN(year) || year <= 0) {
				return 'Invalid year';
			}

			// Remove remaining kanji
			month = parseInt(month);
			if (isNaN(month) || month <= 0) {
				return 'Invalid month';
			}

			day = parseInt(day);
			if (isNaN(day) || day <= 0) {
				return 'Invalid day';
			}

			let date = this.newDate(year, month, day);

			return date;
		},

		/**
		 * Convert Japanese date string into a Gregorian date string
		 * 
		 * @param {string} date - date string to format
		 * @returns {string} Gregorian date string
		 */
		japaneseToGregorianStr: function (date) {

			date = this.japaneseToGregorian(date);

			if (!date.year) {
				return '';
			}

			return `${date.year()}-${date.month()}-${date.day()}`;
		},

		/**
		 * Get array of Eras for the Japanese calendar
		 * 
		 * @param {boolean} kanji_only - whether to return only the era's kanjis
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
		 * Return gregorian date as a Japanese date string
		 * 
		 * @param {string} format format string
		 * @param {CDate} date calendar date to format
		 * @param {array} settings format settings
		 * @returns 
		 */
		formatDate: function (format, date, settings) {
			return this.gregorianToJapaneseStr(date);
		},

		/**
		 * Retrieve the start and end dates for the era
		 * 
		 * @param {int} era_index - array index of the japanese era
		 * @returns {array} [start date, end date] dates in ISO format '/' dividers
		 */
		getEraLimits: function (era_index) {

			if (era_index >= JAPANESE_CALENDAR_DATA.length) {
				return ['', ''];
			}

			let era = JAPANESE_CALENDAR_DATA[era_index];

			return [era['start'], era['end']];
		},

		/**
		 * Check whether the year is valid
		 * 
		 * @param {CDate} year - calendar date
		 * @param {int} year - the date's year 
		 * @param {int} month - the date's month
		 * @param {int} day - the date's day
		 * @returns {}
		 */
		_checkYear: function (year, month, day) {

			if (!Number.isInteger(+year) || (year.year !== undefined && !Number.isInteger(year.year()))) { // year possibly contains kanji
				year = this.japaneseToGregorian(year, month, day);
			}

			if (typeof year === 'string') {
				return null;
			}

			return year;
		},

		/**
		 * Converts Japanese year string into a Gregorian year
		 * 
		 * @param {CDate} year - calendar date
		 * @param {int} year - the date's year 
		 * @returns {array} [era's kanji, Gregorian year]
		 */
		_japaneseYearToGregorian: function (year) {

			if (year.year) {
				year = year.year();
			}

			let kanji = ('' + year).match(/\D*/);
			if ((!kanji || kanji[0] == '') && !Number.isInteger(+year)) {
				return 'Invalid year';
			} else if (Number.isInteger(+year)) {
				year = parseInt(year);
			} else {
				kanji = kanji[0];
				year = parseInt(year.replace(kanji, '')); // remove kanji from date string
			}

			// Get gregorian year
			for (const era of JAPANESE_CALENDAR_DATA) {

				if (era['kanji'] != kanji) {
					continue;
				}

				year = era['start'][0] + (year - 1);
			}

			return [kanji, year];
		},

		_getEraIndexes: function(year){

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