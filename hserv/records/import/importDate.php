<?php
/**
 * Strict date interpretation at the CSV import boundary.
 *
 * Unlike PHP's free-form DateTime parser, this never supplies today's month/day,
 * guesses a century, swaps an invalid day/month, or rolls an impossible date
 * into the following month. Unknown syntax returns null, leaving the original
 * CSV cell available for the validation report.
 *
 * @project Heurist academic knowledge management system
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 */
class ImportDate {

    /** The UI and old sessions without a saved preference use day/month/year. */
    public static function order($order) {
        return (string)$order === '2' ? 2 : 1;
    }

    /**
     * Return a canonical simple date or validated Heurist temporal JSON.
     * A null return means invalid/unsupported, not an empty value to be saved.
     * Years retain their precision; 878 becomes 0878, never 0878-01-01.
     */
    public static function normalise($value, $order = 1) {
        if (!is_string($value) && !is_int($value)) { return null; }
        $value = trim((string)$value);
        if ($value === '') { return null; }
        $order = self::order($order);

        // Encoded temporals have their own ISO dates and metadata. Do not strip
        // punctuation, split their pipe tags, or feed their labels to DateTime.
        if ($value[0] === '{' || $value[0] === '[') {
            $data = json_decode($value, true);
            if (is_array($data) && count($data) === 1 && isset($data[0])) { $data = $data[0]; }
            return self::temporal($data);
        }
        if ($value[0] === '|') { return self::legacyTemporal($value); }
        $value = preg_replace('/[\p{Z}\s]+/u', ' ', $value);
        if ($value === null) { return null; }

        // A question mark is evidence of uncertainty, not removable noise.
        // In particular, "1449? 1457?" does not establish a range or alternatives.
        if (strpos($value, '?') !== false) { return null; }

        $simple = self::simple($value, $order);
        if ($simple !== null) { return $simple; }

        // Explicit qualifiers are retained as metadata. Whole-input matches
        // prevent words such as "castle" from accidentally matching "ca".
        if (preg_match('/^(circa|ca\.?|c\.?|about|abt\.?|around|vers|~|before|bef\.?|avant|after|aft\.?|post|après)\s*(.+)$/iu', $value, $m)) {
            $date = self::simple($m[2], $order);
            if ($date === null) { return null; }
            $qualifier = strtolower(rtrim($m[1], '.'));
            $flag = in_array($qualifier, array('before','bef','avant')) ? 'before'
                : (in_array($qualifier, array('after','aft','post','après')) ? 'after' : 'circa');
            return self::temporal(array('timestamp'=>array('in'=>$date, 'type'=>'s', $flag=>true)));
        }

        // An explicit separator is required for a range. Slash-separated full
        // dates were already handled above. A bare pair of numbers is rejected.
        $range = null;
        if (preg_match('/^(.+?)\s+(?:to|à|–|—|-)\s+(.+)$/iu', $value, $m)
            || preg_match('/^(-?\d{3,6})\s*[-–—]\s*(-?\d{3,6})$/u', $value, $m)
            || preg_match('/^(-?\d{3,6}(?:-\d{1,2}-\d{1,2})?)\/(-?\d{3,6}(?:-\d{1,2}-\d{1,2})?)$/D', $value, $m)) {
            $range = array(self::simple($m[1], $order), self::simple($m[2], $order));
        }
        if ($range !== null && $range[0] !== null && $range[1] !== null) {
            return self::temporal(array('start'=>array('earliest'=>$range[0]), 'end'=>array('latest'=>$range[1])));
        }
        return null;
    }

    /** Parse only explicit components, without any system clock or locale. */
    private static function simple($value, $order) {
        $value = trim($value);
        $bce = false;
        if (preg_match('/^(.+?)\s+(BCE|BC|CE|AD)$/iD', $value, $era)) {
            $value = $era[1];
            $bce = in_array(strtoupper($era[2]), array('BCE', 'BC'));
            if ($value[0] === '-' || $value[0] === '+') { return null; }
        }
        // Explicit separated dates and years only: an eight-digit integer may
        // be a year or YYYYMMDD, so it must not be guessed to be either.
        if (preg_match('/^-?\d{1,6}$/D', $value)) {
            // The shared storage parser treats positive numbers >9999 as
            // compact dates. Do not pass an extended CE year to that path.
            if (!$bce && (int)$value > 9999) { return null; }
            return self::year($bce ? -intval($value) : intval($value));
        }

        $time = '';
        if (preg_match('/^(.+?)[T ](\d{1,2}):(\d{2})(?::(\d{2}))?$/D', $value, $t)) {
            if ((int)$t[2] > 23 || (int)$t[3] > 59 || (isset($t[4]) && (int)$t[4] > 59)) { return null; }
            $time = sprintf(' %02d:%02d', $t[2], $t[3]).(isset($t[4]) ? ':'.$t[4] : '');
            $value = $t[1];
        }
        // Zoned times/fractions are deliberately not reduced to local wall
        // time: the legacy storage parser would discard that information.
        $year = $month = $day = null;
        if (preg_match('/^(-?\d{3,6})([-\/.])(\d{1,2})(?:\2(\d{1,2}))?$/D', $value, $m)) {
            $year = (int)$m[1]; $month = (int)$m[3]; $day = isset($m[4]) ? (int)$m[4] : null;
        } elseif (preg_match('/^(\d{1,2})([-\/.])(\d{1,2})\2(\d{1,4})$/D', $value, $m)) {
            // Two-digit years are ambiguous (e.g. 17 vs 1917 vs 2017).
            // Enter 0017 explicitly if the first century is intended.
            if (strlen($m[4]) < 3) { return null; }
            $year = (int)$m[4];
            $month = (int)$m[$order === 1 ? 3 : 1]; $day = (int)$m[$order === 1 ? 1 : 3];
        } elseif (preg_match('/^(\d{1,2})[\/.](\d{3,4})$/D', $value, $m)) {
            $year = (int)$m[2]; $month = (int)$m[1];
        } else {
            // Month names are recognised explicitly, in English and French.
            // No weekday, missing year, trailing text or relative-date parser.
            $months = array('jan'=>1,'january'=>1,'janv'=>1,'janvier'=>1,
                'feb'=>2,'february'=>2,'fév'=>2,'févr'=>2,'février'=>2,'fev'=>2,'fevr'=>2,'fevrier'=>2,
                'mar'=>3,'march'=>3,'mars'=>3,'apr'=>4,'april'=>4,'avr'=>4,'avril'=>4,
                'may'=>5,'mai'=>5,'jun'=>6,'june'=>6,'juin'=>6,'jul'=>7,'july'=>7,'juil'=>7,'juillet'=>7,
                'aug'=>8,'august'=>8,'août'=>8,'aout'=>8,'sep'=>9,'sept'=>9,'september'=>9,'septembre'=>9,
                'oct'=>10,'october'=>10,'octobre'=>10,'nov'=>11,'november'=>11,'novembre'=>11,
                'dec'=>12,'december'=>12,'déc'=>12,'décembre'=>12,'decembre'=>12);
            $text = strtolower(strtr($value, array('É'=>'é','Û'=>'û')));
            if (preg_match('/^(\d{1,2})(?:st|nd|rd|th|er)?[ -]([\p{L}]+)\.?[ -](\d{3,4})$/uD', $text, $m)) {
                $day = (int)$m[1]; $month = $months[$m[2]] ?? null; $year = (int)$m[3];
            } elseif (preg_match('/^([\p{L}]+)\.? (\d{1,2})(?:st|nd|rd|th)?,? (\d{3,4})$/uD', $text, $m)) {
                $month = $months[$m[1]] ?? null; $day = (int)$m[2]; $year = (int)$m[3];
            } elseif (preg_match('/^([\p{L}]+)\.? (\d{3,4})$/uD', $text, $m)) {
                $month = $months[$m[1]] ?? null; $year = (int)$m[2];
            } else { return null; }
        }
        if ($month === null || $month < 1 || $month > 12 || ($time !== '' && $day === null)
            || $year > 9999 || $year < -9999) { return null; }
        if ($bce) { $year = -$year; }
        // checkdate does not support year zero/BCE; the Gregorian 400-year
        // cycle provides the same month lengths without losing the year sign.
        $calendarYear = (($year % 400) + 400) % 400 + 400;
        if ($day !== null && !checkdate($month, $day, $calendarYear)) { return null; }
        return self::year($year).sprintf('-%02d', $month).($day === null ? '' : sprintf('-%02d', $day)).$time;
    }

    private static function year($year) {
        return $year < 0 ? '-'.str_pad((string)abs($year), 6, '0', STR_PAD_LEFT)
            : str_pad((string)$year, 4, '0', STR_PAD_LEFT);
    }

    /** Validate temporal endpoints before the permissive shared parser sees them. */
    private static function temporal($data) {
        if (!is_array($data) || isset($data['timestamp']) === isset($data['start'])) { return null; }
        $topKeys = array('timestamp','start','end','type','profile','determination','calendar',
            'comment','labcode','calibrated','native','estMinDate','estMaxDate');
        foreach ($data as $key=>$value) {
            if (!in_array($key, $topKeys, true)) { return null; }
            if (!in_array($key, array('timestamp','start','end'), true) && !is_scalar($value)) { return null; }
        }
        if (isset($data['timestamp'])) {
            if (!is_array($data['timestamp']) || !isset($data['timestamp']['in']) || isset($data['end'])) { return null; }
            $parts = array('timestamp'=>array('in'));
        } else {
            if (!isset($data['start']['earliest'], $data['end']['latest'])) { return null; }
            $parts = array('start'=>array('earliest','latest'), 'end'=>array('earliest','latest'));
        }
        foreach ($parts as $part=>$keys) {
            if (!is_array($data[$part])) { return null; }
            $allowed = $part === 'timestamp'
                ? array('in','type','circa','before','after','bp','deviation','deviation_negative','deviation_positive')
                : array('earliest','latest','profile');
            foreach ($data[$part] as $key=>$value) {
                if (!in_array($key, $allowed, true) || !is_scalar($value)) { return null; }
            }
            if ($part === 'timestamp') {
                $data[$part]['type'] = $data[$part]['type'] ?? 's';
                if (!in_array($data[$part]['type'], array('s','f','c'), true)) { return null; }
                $flags = 0;
                foreach (array('circa','before','after','bp') as $flag) {
                    if (isset($data[$part][$flag])) {
                        if (!in_array($data[$part][$flag], array(true,false,0,1), true)) { return null; }
                        if ($flag !== 'bp' && $data[$part][$flag]) { $flags++; }
                    }
                }
                if ($flags > 1) { return null; }
            }
            foreach ($keys as $key) {
                if (!array_key_exists($key, $data[$part])) { continue; }
                $raw = $data[$part][$key];
                if (!is_string($raw) && !is_int($raw)) { return null; }
                // Encoded temporals must contain ISO endpoints, not regional dates.
                if (!preg_match('/^(?:-?\d{1,6}|-?\d{3,6}-\d{1,2}(?:-\d{1,2}(?:[T ]\d{1,2}:\d{2}(?::\d{2})?)?)?)$/D', (string)$raw)) { return null; }
                $date = self::simple((string)$raw, 2);
                if ($date === null) { return null; }
                $data[$part][$key] = $date;
            }
            foreach (array('deviation','deviation_negative','deviation_positive') as $key) {
                if (isset($data[$part][$key]) && (!is_scalar($data[$part][$key])
                    || !preg_match('/^(?:\d+(?:\.\d+)?|P(?=\d)(?:\d+Y)?(?:\d+M)?(?:\d+D)?)$/D', (string)$data[$part][$key]))) { return null; }
                if (isset($data[$part][$key]) && $part === 'timestamp' && $data[$part]['type'] === 's') { return null; }
            }
        }
        // Compare date components numerically (including BCE), without Unix
        // timestamps. Equal/overlapping partial bounds are allowed.
        foreach (array('start','end') as $part) {
            if (isset($data[$part]['earliest'], $data[$part]['latest'])
                && self::bound($data[$part]['earliest'], false) > self::bound($data[$part]['latest'], true)) { return null; }
        }
        if (isset($data['start'], $data['end'])
            && self::bound($data['start']['earliest'], false) > self::bound($data['end']['latest'], true)) { return null; }
        // Computed indexes from exported data must be recalculated on import.
        unset($data['estMinDate'], $data['estMaxDate']);
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function bound($date, $latest) {
        preg_match('/^(-?\d+)(?:-(\d+)(?:-(\d+)(?: (\d+):(\d+)(?::(\d+))?)?)?)?$/D', $date, $m);
        return array((int)$m[1], isset($m[2]) ? (int)$m[2] : ($latest ? 12 : 1),
            isset($m[3]) ? (int)$m[3] : ($latest ? 31 : 1),
            isset($m[4]) ? (int)$m[4] : ($latest ? 23 : 0),
            isset($m[5]) ? (int)$m[5] : ($latest ? 59 : 0),
            isset($m[6]) ? (int)$m[6] : ($latest ? 59 : 0));
    }

    /** Validate legacy tags, then retain supported temporal metadata via Temporal. */
    private static function legacyTemporal($value) {
        $tags = array();
        foreach (explode('|', substr($value, 1)) as $item) {
            if (!preg_match('/^([A-Z]{3})=(.*)$/sD', $item, $m) || isset($tags[$m[1]])) { return null; }
            $tags[$m[1]] = $m[2];
        }
        $known = array('VER','TYP','DAT','CIR','RNG','BPD','BCE','DEV','DVN','DVP',
            'TPQ','TAQ','PDB','PDE','SPF','EPF','PRF','DET','CLD','COM','COD','CAL','CL2');
        if (array_diff(array_keys($tags), $known)) { return null; }
        $type = $tags['TYP'] ?? '';
        if (!in_array($type, array('s','f','c','p'), true)) { return null; }
        $required = $type === 'p' ? array('TPQ','TAQ') : ($type === 'c' ? array() : array('DAT'));
        foreach ($required as $key) { if (!isset($tags[$key]) || $tags[$key] === '') { return null; } }
        foreach (array('DAT','TPQ','TAQ','PDB','PDE') as $key) {
            if (isset($tags[$key])) {
                $date = self::simple($tags[$key], 2);
                if ($date === null || !preg_match('/^-?\d+(?:-\d{1,2}(?:-\d{1,2}(?: \d{2}:\d{2}(?::\d{2})?)?)?)?$/D', $tags[$key])) { return null; }
                $tags[$key] = $date;
            }
        }
        if ($type === 'f' && (!isset($tags['RNG']) || !preg_match('/^(?:\d+(?:\.\d+)?|P(?=\d)(?:\d+Y)?(?:\d+M)?(?:\d+D)?)$/D', $tags['RNG']))) { return null; }
        if (isset($tags['CIR']) && !in_array($tags['CIR'], array('0','1','2','3'), true)) { return null; }
        if ($type === 'c' && (!isset($tags['BPD']) && !isset($tags['BCE']))) { return null; }
        foreach (array('BPD','BCE','DEV','DVN','DVP') as $key) {
            if (isset($tags[$key]) && !preg_match('/^\d+(?:\.\d+)?$/D', $tags[$key])) { return null; }
        }
        // The temporal validator above checks the resulting endpoints and deviations.
        try {
            $value = '';
            foreach ($tags as $tag=>$content) { $value .= '|'.$tag.'='.$content; }
            $temporal = new \hserv\utilities\Temporal($value);
            return $temporal->isValid() ? self::temporal(json_decode($temporal->toJSON(), true)) : null;
        } catch (\Throwable $e) { return null; }
    }
}
