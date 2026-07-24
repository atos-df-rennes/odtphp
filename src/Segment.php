<?php

namespace Odtphp;

use Odtphp\SegmentIterator;
use Odtphp\Exceptions\SegmentException;
use Odtphp\Exceptions\OdfException;
use Odtphp\Zip\ZipInterface;

/**
 * Class for handling templating segments with odt files
 * You need PHP 5.2 at least
 * You need Zip Extension or PclZip library
 * Encoding : ISO-8859-1
 * Author: neveldo $
 * Modified by: Vikas Mahajan http://vikasmahajan.wordpress.com
 * Date - $Date: 2010-12-09 11:11:57
 * SVN Revision - $Rev: 44 $
 * Id : $Id: Segment.php 44 2009-06-17 10:12:59Z neveldo $
 *
 * @copyright  GPL License 2008 - Julien Pauli - Cyril PIERRE de GEYER - Anaska (http://www.anaska.com)
 * @license    http://www.gnu.org/copyleft/gpl.html  GPL License
 * @version 1.3
 *
 * @implements \IteratorAggregate<string, \Odtphp\Segment>
 */
class Segment implements \IteratorAggregate, \Countable
{
    protected string $xml;
    protected string $xmlParsed = '';
    protected string $name;
    /** @var array<string, Segment> */
    protected array $children = [];
    /** @var array<string, mixed> */
    protected array $vars = [];
    /** @var array<string, mixed> */
    public array $manif_vars = [];
    /** @var array<string, mixed> */
    protected array $images = [];
    /** @var object ODF document object (duck-typed) */
    protected $odf;
    protected ZipInterface $file;

    /**
     * Constructor
     *
     * @param string $name name of the segment to construct
     * @param string $xml XML tree of the segment
     * @param object $odf ODF document object (duck-typed: requires getConfig() and getTmpfile())
     */
    public function __construct(string $name, string $xml, $odf)
    {
        $this->name = $name;
        $this->xml = $xml;
        $this->odf = $odf;
        $zipHandler = $this->odf->getConfig('ZIP_PROXY');
        $this->file = new $zipHandler();
        $this->_analyseChildren($this->xml);
    }

    /**
     * Returns the name of the segment
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Does the segment have children ?
     */
    public function hasChildren(): bool
    {
        return [] !== $this->children;
    }

    /**
     * Get children segments
     *
     * @return array<string, Segment>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Countable interface
     */
    public function count(): int
    {
        return count($this->children);
    }

    /**
     * IteratorAggregate interface
     *
     * @return \Iterator<string, \Odtphp\Segment>
     */
    public function getIterator(): \Iterator
    {
        return new \RecursiveIteratorIterator(new SegmentIterator($this->children), 1);
    }

    /**
     * Replace variables of the template in the XML code
     * All the children are also called
     */
    public function merge(): string
    {
        $this->xmlParsed .= str_replace(array_keys($this->vars), array_values($this->vars), $this->xml);
        if ($this->hasChildren()) {
            foreach ($this->children as $child) {
                $this->xmlParsed = str_replace($child->xml, ($child->xmlParsed == "") ? $child->merge() : $child->xmlParsed, $this->xmlParsed);
                $child->xmlParsed = '';
                //Store all image names used in child segments in current segment array
                foreach ($child->manif_vars as $file) {
                    $this->manif_vars[] = $file;
                }

                $child->manif_vars = [];
            }
        }
        $reg = "/\[!--\sBEGIN\s$this->name\s--\](.*)\[!--\sEND\s$this->name\s--\]/smU";
        $this->xmlParsed = preg_replace($reg, '$1', $this->xmlParsed);
        $this->file->open($this->odf->getTmpfile());
        foreach ($this->images as $imageKey => $imageValue) {
            if ($this->file->getFromName('Pictures/' . $imageValue) === false) {
                $this->file->addFile($imageKey, 'Pictures/' . $imageValue);
            }
        }

        $this->file->close();
        return $this->xmlParsed;
    }

    /**
     * Analyse the XML code in order to find children
     */
    protected function _analyseChildren(string $xml): self
    {
        // $reg2 = "#\[!--\sBEGIN\s([\S]*)\s--\](?:<\/text:p>)?(.*)(?:<text:p\s.*>)?\[!--\sEND\s(\\1)\s--\]#sm";
        $reg2 = "#\[!--\sBEGIN\s([\S]*)\s\--\](.*)\[!--\sEND\s(\\1)\s\--\]#smU";
        preg_match_all($reg2, $xml, $matches);
        for ($i = 0, $size = count($matches[0]); $i < $size; $i++) {
            if ($matches[1][$i] != $this->name) {
                $this->children[$matches[1][$i]] = new self($matches[1][$i], $matches[0][$i], $this->odf);
            } else {
                $this->_analyseChildren($matches[2][$i]);
            }
        }
        return $this;
    }

    /**
     * Assign a template variable to replace
     *
     * @throws SegmentException
     */
    public function setVars(string $key, string $value, bool $encode = true, string $charset = 'ISO-8859'): self
    {
        if (strpos($this->xml, $this->odf->getConfig('DELIMITER_LEFT') . $key . $this->odf->getConfig('DELIMITER_RIGHT')) === false) {
            throw new SegmentException("var $key not found in {$this->getName()}");
        }
        $value = $encode ? htmlspecialchars($value) : $value;
        $value = ($charset === 'ISO-8859') ? utf8_encode($value) : $value;
        $this->vars[$this->odf->getConfig('DELIMITER_LEFT') . $key . $this->odf->getConfig('DELIMITER_RIGHT')] = str_replace("\n", "<text:line-break/>", $value);
        return $this;
    }

    /**
     * Assign a template variable as a picture
     *
     * @param string $key name of the variable within the template
     * @param string $value path to the picture
     * @param int|null $page anchor to page number (or -1 if anchor-type is aschar)
     * @param int|null $width width of picture (keep original if null)
     * @param int|null $height height of picture (keep original if null)
     * @param string|null $offsetX offset by horizontal (not used if $page = -1)
     * @param string|null $offsetY offset by vertical (not used if $page = -1)
     * @throws OdfException
     */
    public function setImage(string $key, string $value, ?int $page = null, ?int $width = null, ?int $height = null, ?string $offsetX = null, ?string $offsetY = null): self
    {
        $filename = strtok(strrchr($value, '/'), '/.');
        $file = substr(strrchr($value, '/'), 1);
        $size = @getimagesize($value);
        if ($size === false) {
            throw new OdfException("Invalid image");
        }
        if (!$width && !$height) {
            [$width, $height] = $size;
            $width *= Odf::PIXEL_TO_CM;
            $height *= Odf::PIXEL_TO_CM;
        }
        $anchor = $page == -1 ? 'text:anchor-type="aschar"' : "text:anchor-type=\"page\" text:anchor-page-number=\"{$page}\" svg:x=\"{$offsetX}cm\" svg:y=\"{$offsetY}cm\"";
        $xml = <<<IMG
            <draw:frame draw:style-name="fr1" draw:name="$filename" {$anchor} svg:width="{$width}cm" svg:height="{$height}cm" draw:z-index="3"><draw:image xlink:href="Pictures/$file" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad"/></draw:frame>
            IMG;
        $this->images[$value] = $file;
        $this->manif_vars[] = $file;    //save image name as array element
        $this->setVars($key, $xml, false);
        return $this;
    }

    /**
     * Shortcut to retrieve a child
     *
     * @throws SegmentException
     */
    public function __get(string $prop): self
    {
        if (array_key_exists($prop, $this->children)) {
            return $this->children[$prop];
        } else {
            throw new SegmentException('child ' . $prop . ' does not exist');
        }
    }

    /**
     * Proxy for setVars
     *
     * @param string $meth method name
     * @param array<int, mixed> $args method arguments
     * @return Segment
     */
    public function __call(string $meth, array $args)
    {
        try {
            array_unshift($args, $meth);
            return call_user_func_array([$this, 'setVars'], $args);
        } catch (SegmentException $e) {
            throw new SegmentException("method $meth nor var $meth exist", $e->getCode(), $e);
        }
    }

    /**
     * Returns the parsed XML
     */
    public function getXmlParsed(): string
    {
        return $this->xmlParsed;
    }
}
