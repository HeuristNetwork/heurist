<?php
/**
* SqlTables.php - Table names of the SQL text built by the search compiler
*
* The compilers build one big statement as text, so they cannot call the access
* classes for each table. They write table keys instead of names - "{records}",
* "{details}", "{terms}", ... - and the builders resolve them when the final SQL is
* made (plan 15, Phase 4). The names come from the access classes: record tables
* from RecordSchema, definition tables from DefinitionLookup::TABLES, files from
* FileRepository::TABLES, users and groups from UserGroupRepository::TABLES.
*
* @project     Heurist academic knowledge management system
* @package     Records\Query
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Query\Compiler;

use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\Files\FileRepository;
use Heurist\Records\Store\RecordSchema;
use Heurist\Users\UserGroupRepository;
use InvalidArgumentException;

/** Table keys of compiled SQL and their names. */
final class SqlTables
{
    private RecordSchema $schema;
    /** @var array<string,string> key => table name */
    private array $names;
    /** @var array<string,string> "{key}" => table name */
    private array $tokens = array();

    public function __construct(?RecordSchema $schema = null)
    {
        $this->schema = $schema ?? new RecordSchema();
        $this->names = array_merge(DefinitionLookup::TABLES, FileRepository::TABLES, UserGroupRepository::TABLES,
            $this->schema->tables());
        foreach($this->names as $key => $name){
            $this->tokens['{'.$key.'}'] = $name;
        }
    }

    public function schema(): RecordSchema
    {
        return $this->schema;
    }

    /** Table name of a key (records, details, links, terms, files, groups, ...). */
    public function table(string $key): string
    {
        if(!isset($this->names[$key])){
            throw new InvalidArgumentException('Unknown table key '.$key);
        }
        return $this->names[$key];
    }

    /** SQL text with every "{key}" replaced by its table name. */
    public function resolve(string $sql): string
    {
        return strtr($sql, $this->tokens);
    }
}
