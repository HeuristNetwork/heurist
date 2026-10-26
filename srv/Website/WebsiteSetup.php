<?php
/**
* WebsiteSetup.php - Definitions needed by the website format v4 (plan 27a)
*
* Fields version 2-49, configuration 2-1187 (DT_CONFIGURATION) and page
* translations 2-1202 (DT_CMS_PAGE_TRANSLATIONS) on the website (99-51) and
* page (99-52) record types; the logo of a website made optional (plan 27b).
* Database managers only (POST /api/{db}/website/setup).
*
* @project     Heurist academic knowledge management system
* @package     Website
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Website;

use Heurist\Runtime\RuntimeContext;

/** Installs the fields of the format on the CMS record types. */
final class WebsiteSetup
{
    public const FIELDS = array('2-49', '2-1187', '2-1202');
    public const RECTYPES = array('99-51', '99-52');
    /** Fields made optional: a new v4 website has no logo (plan 27b, Q3); the legacy editor still asks for it. */
    public const OPTIONAL = array('99-51' => array('2-38'));

    private WebsiteDefinitionsInterface $definitions;
    private RuntimeContext $runtime;

    public function __construct(WebsiteDefinitionsInterface $definitions, RuntimeContext $runtime)
    {
        $this->definitions = $definitions;
        $this->runtime = $runtime;
    }

    /**
     * @return array<int,string> Report lines.
     * @throws WebsiteException 403 for users who are not database managers.
     */
    public function install(): array
    {
        if(!$this->runtime->isAdmin){
            throw new WebsiteException(403, 'Only database managers can install the website definitions');
        }
        return array_merge($this->definitions->install(self::FIELDS, self::RECTYPES),
            $this->definitions->makeOptional(self::OPTIONAL));
    }
}
