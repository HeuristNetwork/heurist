<?php

namespace hserv\synchronisation;

/** Write new bytes under a fresh name; an interrupted write cannot truncate an existing upload. */
final class SyncFileStorage
{
    public static function store(string $directory, string $prefix, string $extension, string $bytes): string
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $name = $prefix.'_'.bin2hex(random_bytes(16)).($extension !== '' ? '.'.$extension : '');
        $handle = fopen($directory.$name, 'xb');
        if (!$handle) throw new \RuntimeException('Unable to create a synchronised file.');
        try {
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $written = fwrite($handle, substr($bytes, $offset));
                if ($written === false || $written === 0) throw new \RuntimeException('Incomplete synchronised file write.');
                $offset += $written;
            }
            if (!fflush($handle)) throw new \RuntimeException('Unable to flush a synchronised file.');
            if (function_exists('fsync') && !fsync($handle)) throw new \RuntimeException('Unable to persist a synchronised file.');
        } finally { fclose($handle); }
        return $name;
    }
}
