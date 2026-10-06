<?php
/**
* XmlStreamWriter.php - Streaming XML output of the export writers (HML, GEXF, KML)
*
* A thin wrapper of PHP XMLWriter writing straight into a file: elements are
* written forward-only and flush() empties the buffer after every batch, so
* memory does not grow with the export. XMLWriter escapes text and attributes and
* keeps track of open elements; this class adds what it lacks: control characters
* that XML 1.0 does not allow are replaced (as flathml replaceIllegalChars), an
* empty text gives an empty element (<name/>), and write errors throw.
*
* @project     Heurist academic knowledge management system
* @package     Records\Export
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Export\Writer;

use RuntimeException;
use XMLWriter;

/** Forward-only XML file writer. */
final class XmlStreamWriter
{
    private XMLWriter $xml;
    /** Open elements (checked by close()). */
    private int $depth = 0;

    /**
     * Open the file and write the XML declaration.
     *
     * @param string $path File to create.
     * @param bool $indent One element per line, indented (readable; a little larger).
     * @throws RuntimeException When the file cannot be created.
     */
    public function __construct(string $path, bool $indent = true)
    {
        $this->xml = new XMLWriter();
        if(!$this->xml->openUri($path)){
            throw new RuntimeException('Cannot create the export file');
        }
        if($indent){
            $this->xml->setIndent(true);
            $this->xml->setIndentString(' ');
        }
        $this->xml->startDocument('1.0', 'UTF-8');
    }

    /**
     * Open an element.
     *
     * @param string $name Element name.
     * @param array<string,scalar|null> $attributes Attributes (null values are left out).
     */
    public function start(string $name, array $attributes = array()): void
    {
        $this->xml->startElement($name);
        $this->attributes($attributes);
        $this->depth++;
    }

    /** Close the last open element. */
    public function end(): void
    {
        if($this->depth < 1){
            throw new RuntimeException('XML export: no open element to close');
        }
        $this->xml->endElement();
        $this->depth--;
    }

    /**
     * Write a complete element with text content (an empty text gives <name/>).
     *
     * @param string $name Element name.
     * @param array<string,scalar|null> $attributes Attributes.
     * @param string|null $text Text content.
     */
    public function element(string $name, array $attributes = array(), ?string $text = null): void
    {
        $this->xml->startElement($name);
        $this->attributes($attributes);
        if($text !== null && $text !== ''){
            $this->xml->text(self::legal($text));
        }
        $this->xml->endElement();
    }

    /**
     * Insert ready-made XML (e.g. KML geometry from geoPHP). The caller is responsible
     * for its well-formedness.
     */
    public function raw(string $xml): void
    {
        $this->xml->writeRaw($xml);
    }

    /** Write the buffered output to the file (after every batch). */
    public function flush(): void
    {
        if($this->xml->flush() === false){
            throw new RuntimeException('Cannot write the export file');
        }
    }

    /** Close every open element, end the document and close the file. */
    public function close(): void
    {
        while($this->depth > 0){
            $this->end();
        }
        $this->xml->endDocument();
        $this->flush();
    }

    /** XML 1.0 does not allow these control characters (flathml replaceIllegalChars). */
    public static function legal(string $text): string
    {
        return (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '[?]', $text);
    }

    private function attributes(array $attributes): void
    {
        foreach($attributes as $name => $value){
            if($value === null){ continue; }
            $this->xml->writeAttribute((string)$name, self::legal((string)$value));
        }
    }
}
