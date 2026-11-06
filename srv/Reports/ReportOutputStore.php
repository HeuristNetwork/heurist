<?php
/**
* ReportOutputStore.php - Generated files: generated-reports/{output record id}.{format}
*
* The file of an output record (plan 30): it is found by the id of the record, so no name
* given by a user is used. Other files in the folder (of legacy schedules) are not touched.
*
* @project     Heurist academic knowledge management system
* @package     Reports
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Reports;

use InvalidArgumentException;

/** Files of output records. */
final class ReportOutputStore
{
    private string $directory;

    /** @param string $directory The generated-reports folder. */
    public function __construct(string $directory)
    {
        $this->directory = $directory === '' ? '' : rtrim($directory, '/\\').'/';
    }

    /** The folder, with a closing slash ('' when there is none). */
    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * Path of the file of an output.
     *
     * @param int $outputId Output record.
     * @param string $format Key of ReportRepository::FORMATS.
     */
    public function path(int $outputId, string $format): string
    {
        if($outputId < 1 || !isset(ReportRepository::FORMATS[$format])){
            throw new InvalidArgumentException('Invalid output file');
        }
        return $this->directory.$outputId.'.'.$format;
    }

    /**
     * The file of an output.
     *
     * @return array{format:string,size:int,modified:string,path:string,time:int}|null
     */
    public function find(int $outputId): ?array
    {
        if($outputId < 1 || $this->directory === ''){ return null; }
        foreach(array_keys(ReportRepository::FORMATS) as $format){
            $path = $this->directory.$outputId.'.'.$format;
            if(is_file($path)){
                $time = intval(filemtime($path));
                return array('format' => $format, 'size' => intval(filesize($path)),
                    'modified' => gmdate('Y-m-d\TH:i:s\Z', $time), 'path' => $path, 'time' => $time);
            }
        }
        return null;
    }

    /**
     * Delete the files of an output (a changed output type leaves an older one).
     *
     * @param int $outputId Output record.
     * @param string $keep Format that stays.
     * @return bool Whether a file was deleted.
     */
    public function delete(int $outputId, string $keep = ''): bool
    {
        if($outputId < 1 || $this->directory === ''){ return false; }
        $deleted = false;
        foreach(array_keys(ReportRepository::FORMATS) as $format){
            $path = $this->directory.$outputId.'.'.$format;
            if($format !== $keep && is_file($path) && @unlink($path)){ $deleted = true; }
        }
        return $deleted;
    }
}
