<?php
/**
* TemporalLocale.php - Words and patterns of the human-readable date text, by language
*
* Kept here and not in the localization files of the client: the server does not read them, and a
* report or a website page gives its own language. The client has the same words in
* heurist-explorer shared/src/utils/temporalLocale.js; keep both in step, the cases in
* heurist-explorer shared/test/fixtures/temporal-locale run on both (tests/TemporalLocaleTest.php).
* Only the server has `types`, `determinations` and `calibrated` (compact text of the reports), and
* `shortMonthYear`: its English text keeps the two spaces of the legacy text before a year after a
* short month, because stored titles (TitleMask) must stay the same.
*
* @project     Heurist academic knowledge management system
* @package     Utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Utilities;

/** Date words by language. */
final class TemporalLocale
{
    /** Other codes of a language in the table. */
    private const ALIASES = array('fra' => 'fre');

    /** Date words by language (3 letters). `firstDay`: the text of day 1 when it differs from the number. */
    private const WORDS = array(
        'eng' => array(
            'months' => array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'),
            'monthsLong' => array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August',
                'September', 'October', 'November', 'December'),
            'firstDay' => null,
            'dayMonthYear' => '{d} {m}  {y}',
            'shortMonthYear' => '{m}  {y}',
            'monthYear' => '{m} {y}',
            'range' => '{from} to {to}',
            'circa' => 'circa',
            'before' => 'before',
            'after' => 'after',
            'bce' => 'BCE',
            'bya' => 'bya',
            'mya' => 'Mya',
            'year' => 'year',
            'years' => 'years',
            'month' => 'month',
            'monthPlural' => 'months',
            'day' => 'day',
            'days' => 'days',
            'gregorian' => 'Gregorian',
            'types' => array('Radiometric' => 'Radiometric', 'Fuzzy date' => 'Fuzzy date', 'Fuzzy Range' => 'Fuzzy Range'),
            'determinations' => array('Unknown', 'Attested', 'Conjecture', 'Measurement'),
            'calibrated' => 'Calibrated'
        ),
        'fre' => array(
            'months' => array('janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'),
            'monthsLong' => array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août',
                'septembre', 'octobre', 'novembre', 'décembre'),
            'firstDay' => '1er',
            'dayMonthYear' => '{d} {m} {y}',
            'shortMonthYear' => '{m} {y}',
            'monthYear' => '{m} {y}',
            'range' => '{from} à {to}',
            'circa' => 'vers',
            'before' => 'avant',
            'after' => 'après',
            'bce' => 'av. J.-C.',
            'bya' => 'Ga',
            'mya' => 'Ma',
            'year' => 'an',
            'years' => 'ans',
            'month' => 'mois',
            'monthPlural' => 'mois',
            'day' => 'jour',
            'days' => 'jours',
            'gregorian' => 'grégorien',
            'types' => array('Radiometric' => 'Radiométrique', 'Fuzzy date' => 'Date floue', 'Fuzzy Range' => 'Intervalle flou'),
            'determinations' => array('Inconnue', 'Attestée', 'Conjecture', 'Mesure'),
            'calibrated' => 'Calibrée'
        )
    );

    /**
     * The date words of a language.
     *
     * @param string|null $language Language code (3 letters, any case).
     * @return array Words of the language; English for a language that has none.
     */
    public static function words($language): array
    {
        $code = strtolower(trim((string)$language));
        return self::WORDS[self::ALIASES[$code] ?? $code] ?? self::WORDS['eng'];
    }

    /**
     * A pattern with its `{name}` parts filled in.
     *
     * @param string $pattern Pattern of the table.
     * @param array $values Text by name.
     * @return string
     */
    public static function fill(string $pattern, array $values): string
    {
        $parts = array();
        foreach($values as $name => $value){
            $parts['{'.$name.'}'] = (string)$value;
        }
        return strtr($pattern, $parts);
    }
}
