<?php
/**
* DefinitionSnapshotService.php - Compact database-structure snapshot builder
*
* Produces the minimal record-type / field / term / structure snapshot consumed
* by client query builders (HFilterBuilder) and the query-language describer.
* The payload is cached as def-snapshot.json in the database "entity" system
* directory and rebuilt on demand after System::cleanDefCache() removes it.
*
* @project     Heurist academic knowledge management system
* @package     Definitions
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);
namespace Heurist\Definitions;

use Heurist\Database\DatabaseInterface;
use Heurist\Runtime\RuntimeContext;

/** Builds and caches the read-only database-definitions snapshot. */
final class DefinitionSnapshotService
{
    private DatabaseInterface $database;
    private RuntimeContext $runtime;
    private string $cacheDirectory;

    /**
     * Snapshot format; a cached snapshot of an older format is rebuilt
     * (2: structure rows have "max"; 3: terms named as the report field tree -
     * term, code, conceptid, desc - by DefinitionLookup; 4: dbconst DT_DATA_SOURCE;
     * 5: dbconst RT_REGISTERED_FILE, the API domain of record types, plan 21;
     * 6: CMS code field ids and per-response JavaScript authorization;
     * 7: dbconst of the user and group fields, plan 20 Phase 4d;
     * 8: structure createChild flag and dbconst DT_PARENT_ENTITY, plan 26;
     * 9: dbconst has every name of SystemConstants, dbconstVersion, 2026-10-27).
     */
    private const FORMAT = 9;

    /** Languages offered for translations when the database has no own list (legacy default). */
    private const TRANSLATION_LANGUAGES = array('ENG', 'FRE', 'CHI', 'SPA', 'ITA', 'ARA', 'GER', 'POR', 'LAT', 'GRE', 'GRC', 'IND');

    /** @var array<int,string> Languages of the database setting "Languages" (db_languages.json). */
    private array $translationLanguages;
    private bool $javaScriptAllowed;
    /** Whether the legacy translator (DeepL) is configured: meta.translator (plan 25). */
    private bool $translator;

    /**
     * @param string $cacheDirectory Absolute path of the database "entity" dir, or '' to disable caching.
     * @param array<int,string> $translationLanguages The database setting "Languages" (3-letter codes),
     *        offered for translations in the record editor; empty: the legacy default list.
     * @param bool $translator Whether the installation has a DeepL key (the record editor's Translate).
     */
    public function __construct(
        DatabaseInterface $database,
        RuntimeContext $runtime,
        string $cacheDirectory = '',
        array $translationLanguages = array(),
        bool $javaScriptAllowed = false,
        bool $translator = false
    ) {
        $this->database = $database;
        $this->javaScriptAllowed = $javaScriptAllowed;
        $this->translator = $translator;
        $this->runtime = $runtime;
        $this->cacheDirectory = $cacheDirectory === ''
            ? ''
            : rtrim(str_replace('\\', '/', $cacheDirectory), '/').'/';
        $this->translationLanguages = array_values(array_unique(array_filter(array_map(
            static function($code){ return strtoupper(trim((string)$code)); },
            $translationLanguages
        ), static function($code){ return preg_match('/^[A-Z]{3}$/', $code) === 1; })));
    }

    /** Return the snapshot payload, using or refreshing the on-disk cache. */
    public function get(array $params = array()): array
    {
        return $this->withTranslationLanguages($this->snapshot());
    }

    /**
     * The database setting can change without a definition change: added to every
     * response, not cached (2026-10-14, plan 18).
     */
    private function withTranslationLanguages(array $snapshot): array
    {
        $snapshot['meta']['javaScriptAllowed'] = $this->javaScriptAllowed;
        $snapshot['meta']['translationLanguages'] = $this->translationLanguages ?: self::TRANSLATION_LANGUAGES;
        $snapshot['meta']['translator'] = $this->translator;
        return $snapshot;
    }

    /** The cached snapshot, rebuilt when missing or of an older format. */
    private function snapshot(): array
    {
        $file = $this->cacheFile();
        if($file !== '' && is_readable($file)){
            $cached = json_decode((string)file_get_contents($file), true);
            if(is_array($cached) && isset($cached['meta']) && $this->hasCurrentDbconst($cached['meta'])){
                $mtime = @filemtime($file);
                if($mtime !== false){ $cached['meta']['version'] = (string)$mtime; }
                return $cached;
            }
        }

        $snapshot = $this->build();
        if($file !== '' && is_dir($this->cacheDirectory) && is_writable($this->cacheDirectory)){
            $written = @file_put_contents(
                $file,
                json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
            if($written !== false){
                $mtime = @filemtime($file);
                if($mtime !== false){ $snapshot['meta']['version'] = (string)$mtime; }
            }
        }
        return $snapshot;
    }

    /** Cache-file mtime used as the HTTP ETag, or '0' when not yet cached. */
    public function version(): string
    {
        $file = $this->cacheFile();
        if($file !== '' && is_readable($file)){
            $mtime = @filemtime($file);
            if($mtime !== false){ return (string)$mtime; }
        }
        return '0';
    }

    /** Compose the full snapshot from the definitions (DefinitionLookup). */
    public function build(): array
    {
        $lookup = $this->lookup();
        return array(
            'meta'          => $this->meta($lookup->registeredId()),
            'rectypeGroups' => $lookup->rectypeGroups(),
            'fieldGroups'   => $lookup->fieldGroups(),
            'rectypes'      => $this->rectypes(),
            'fields'        => $this->fields(),
            'structure'     => $lookup->structure(),
            'terms'         => $this->terms(),
            'termlinks'     => $lookup->termLinks()
        );
    }

    private function lookup(): DefinitionLookup
    {
        // the resolved constants are cached beside the snapshot (def-snapshot-constants.json)
        return DefinitionLookup::shared($this->database)->useConstantCache($this->cacheDirectory);
    }

    private function cacheFile(): string
    {
        return $this->cacheDirectory === '' ? '' : $this->cacheDirectory.'def-snapshot.json';
    }

    private function meta(int $registeredId): array
    {
        $languages = $this->lookup()->languages();

        return array(
            'db'        => $this->runtime->databaseName,
            'format'    => self::FORMAT,
            'dbId'      => $registeredId,
            'version'   => (string)time(),   // replaced with the cache-file mtime by get()
            'generated' => gmdate('c'),
            'language'  => 'eng',            // translations deferred; see _README
            'languages' => $languages,
            'dbconst'   => $this->dbconst(),
            // a cached snapshot of another table version is rebuilt
            'dbconstVersion' => SystemConstants::version()
        );
    }

    /** Whether a cached snapshot has the current format and the constants of the current table. */
    private function hasCurrentDbconst(array $meta): bool
    {
        return intval($meta['format'] ?? 1) >= self::FORMAT && is_array($meta['dbconst'] ?? null)
            && ($meta['dbconstVersion'] ?? '') === SystemConstants::version();
    }

    /**
     * Local ids of all system constants (Definitions/SystemConstants, 2026-10-27): the client
     * reads every RT_* / DT_* / TRM_* name with HDbDefs.dbconst() and needs no list of its own.
     */
    private function dbconst(): array
    {
        return $this->lookup()->constants();
    }

    private function rectypes(): array
    {
        $out = array();
        foreach($this->lookup()->rectypes() as $id => $rectype){
            $plural = trim($rectype['plural']);
            $entry = array(
                'name'    => $rectype['name'],
                'plural'  => $plural !== '' ? $plural : $rectype['name'],
                'group'   => $rectype['group'],
                'concept' => $rectype['concept'],
                'showInLists' => $rectype['showInLists']
            );
            $description = trim($rectype['description']);
            if($description !== '' && stripos($description, 'Description of this record type') === false){
                $entry['description'] = $description;
            }
            $out[(string)$id] = $entry;
        }
        return $out;
    }

    /** Global field definitions. Layout-only "separator" pseudo-fields are omitted. */
    private function fields(): array
    {
        $out = array();
        foreach($this->lookup()->fields() as $id => $field){
            if($field['type'] === 'separator'){ continue; }
            $entry = array(
                'name'    => $field['name'],
                'type'    => $field['type'],
                'group'   => $field['group'],
                'concept' => $field['concept']
            );
            $vocabulary = $field['vocabulary'][0] ?? 0;
            if($vocabulary > 0){ $entry['vocabulary'] = $vocabulary; }
            if(!empty($field['targetTypes'])){ $entry['targetTypes'] = $field['targetTypes']; }
            $out[(string)$id] = $entry;
        }
        return $out;
    }

    /**
     * Terms with the names of the report field tree and Smarty term subfields
     * (DefinitionLookup): term, conceptid, code, desc; domain and inverse when set.
     * The key of each entry is the internal id.
     */
    private function terms(): array
    {
        $out = array();
        foreach($this->lookup()->allTerms() as $id => $term){
            $entry = array('term' => $term['term'], 'conceptid' => $term['conceptid']);
            if($term['code'] !== ''){ $entry['code'] = $term['code']; }
            if($term['desc'] !== ''){ $entry['desc'] = $term['desc']; }
            if($term['domain'] === 'relation'){ $entry['domain'] = 'relation'; }
            if($term['inverse'] > 0){ $entry['inverse'] = $term['inverse']; }
            $out[(string)$id] = $entry;
        }
        return $out;
    }
}
